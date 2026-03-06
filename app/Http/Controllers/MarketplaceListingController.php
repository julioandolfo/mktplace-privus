<?php

namespace App\Http\Controllers;

use App\Enums\ProductStatus;
use App\Models\MarketplaceAccount;
use App\Models\MarketplaceListing;
use App\Models\Product;
use App\Services\Marketplaces\MercadoLivreService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MarketplaceListingController extends Controller
{
    public function index(Request $request)
    {
        $query = MarketplaceListing::with(['marketplaceAccount', 'product']);

        if ($accountId = $request->input('account')) {
            $query->where('marketplace_account_id', $accountId);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($request->input('linked') === '1') {
            $query->linked();
        } elseif ($request->input('linked') === '0') {
            $query->unlinked();
        }

        if ($search = $request->input('search')) {
            $query->search($search);
        }

        $listings  = $query->latest()->paginate(25)->withQueryString();
        $accounts  = MarketplaceAccount::orderBy('account_name')->get();

        $totalListings  = MarketplaceListing::count();
        $activeCount    = MarketplaceListing::where('status', 'active')->count();
        $pausedCount    = MarketplaceListing::where('status', 'paused')->count();
        $unlinkedCount  = MarketplaceListing::unlinked()->count();
        $perAccount     = MarketplaceListing::with('marketplaceAccount')
            ->selectRaw("marketplace_account_id, count(*) as total, sum(case when status = 'active' then 1 else 0 end) as active_count")
            ->groupBy('marketplace_account_id')
            ->get();

        return view('marketplace-listings.index', compact(
            'listings', 'accounts',
            'totalListings', 'activeCount', 'pausedCount', 'unlinkedCount', 'perAccount'
        ));
    }

    public function show(MarketplaceListing $listing)
    {
        $step = 'init';
        try {
            $step = 'load-relations';
            $listing->load(['marketplaceAccount', 'product']);

            $step     = 'load-products';
            $products = Product::active()->orderBy('name')->get(['id', 'name', 'sku', 'price']);

            $liveData           = null;
            $quality            = null;
            $description        = null;
            $categoryAttributes = [];
            $apiError           = null;

            $step    = 'check-account';
            $account = $listing->marketplaceAccount;

            if ($account && $account->credentials) {
                try {
                    $step     = 'api-item';
                    $service  = new MercadoLivreService($account);
                    $liveData = $service->getItemWithVariations($listing->external_id);

                    $step    = 'api-quality';
                    $quality = $service->getItemQuality($listing->external_id);

                    $step        = 'api-description';
                    $description = $service->getItemDescription($listing->external_id);

                    $step = 'sync-meta';
                    $metaPatch = [];
                    if (! empty($liveData['variations'])) {
                        $metaPatch['has_variations'] = true;
                    }
                    if (! empty($liveData['family_name'])) {
                        $metaPatch['family_name'] = $liveData['family_name'];
                    }
                    if (! empty($liveData['catalog_product_id'])) {
                        $metaPatch['catalog_product_id'] = $liveData['catalog_product_id'];
                    }
                    if (in_array('fulfillment', $liveData['tags'] ?? [])) {
                        $metaPatch['is_fulfillment'] = true;
                    }
                    if (! empty($metaPatch)) {
                        $listing->update(['meta' => array_merge($listing->meta ?? [], $metaPatch)]);
                    }

                    $step = 'api-attributes';
                    if (! empty($liveData['category_id'])) {
                        $categoryAttributes = $service->getCategoryAttributes($liveData['category_id']);
                    }
                } catch (\Throwable $e) {
                    Log::warning("ListingController show() API error [{$listing->external_id}] step={$step}: " . $e->getMessage());
                    $apiError = 'Não foi possível carregar dados em tempo real. Exibindo dados locais.';
                }
            }

            // Sales stats — local DB, last 12 months (driver-agnostic)
            $step = 'sales-stats';
            try {
                $salesRaw = DB::table('order_items')
                    ->join('orders', 'orders.id', '=', 'order_items.order_id')
                    ->where('orders.created_at', '>=', now()->subMonths(12))
                    ->select('orders.created_at', 'order_items.total', 'order_items.meta')
                    ->get()
                    ->filter(function ($row) use ($listing) {
                        $meta = $row->meta;
                        if (is_string($meta)) {
                            $meta = json_decode($meta, true);
                        }
                        return ($meta['ml_item_id'] ?? null) === $listing->external_id;
                    });

                $salesStats = $salesRaw
                    ->groupBy(fn ($row) => date('Y-m', strtotime($row->created_at)))
                    ->map(fn ($group, $month) => (object) [
                        'month'   => $month,
                        'qty'     => $group->count(),
                        'revenue' => $group->sum('total'),
                    ])
                    ->values()
                    ->sortBy('month')
                    ->values();
            } catch (\Throwable $e) {
                Log::warning("ListingController sales stats error [{$listing->external_id}]: " . $e->getMessage());
                $salesStats = collect();
            }

            $step         = 'compute-totals';
            $totalQty     = $salesStats->sum('qty');
            $totalRevenue = $salesStats->sum('revenue');
            $avgTicket    = $totalQty > 0 ? $totalRevenue / $totalQty : 0;

            $step = 'render-view';
            $viewData = compact(
                'listing', 'products',
                'liveData', 'quality', 'description', 'categoryAttributes', 'apiError',
                'salesStats', 'totalQty', 'totalRevenue', 'avgTicket'
            );

            // Force render inside try-catch so Blade errors are captured
            $html = view('marketplace-listings.show', $viewData)->render();
            return response($html);

        } catch (\Throwable $e) {
            $context = [
                'listing_id'  => $listing->id ?? null,
                'external_id' => $listing->external_id ?? null,
                'step'        => $step,
                'error'       => $e->getMessage(),
                'file'        => basename($e->getFile()) . ':' . $e->getLine(),
                'trace'       => collect(explode("\n", $e->getTraceAsString()))->take(15)->implode("\n"),
            ];

            Log::error("ListingController show() FALHOU step=[{$step}]: " . $e->getMessage(), $context);

            activity('listings')
                ->withProperties($context)
                ->log("Erro 500 em listings/{$listing->id} (step: {$step}): " . $e->getMessage());

            return redirect()->route('listings.index')
                ->with('error', "Erro ao carregar anúncio (step: {$step}): " . $e->getMessage());
        }
    }

    public function update(Request $request, MarketplaceListing $listing)
    {
        $meta = $listing->meta ?? [];

        $isCatalogItem    = ! empty($meta['family_name']) || ! empty($meta['catalog_product_id']);
        $isHandlingLocked = ! empty($meta['handling_time_locked']) || ! empty($meta['is_fulfillment']);
        $hasVariations    = ! empty($meta['has_variations']);

        $validated = $request->validate([
            'title'              => $isCatalogItem ? 'nullable|string|max:60' : 'required|string|max:60',
            'price'              => 'required|numeric|min:0',
            'available_quantity' => 'required|integer|min:0',
            'handling_time'      => 'required|integer|min:0|max:20',
            'attributes'         => 'nullable|array',
            'shipping_width'     => 'nullable|numeric|min:0',
            'shipping_height'    => 'nullable|numeric|min:0',
            'shipping_length'    => 'nullable|numeric|min:0',
            'shipping_weight'    => 'nullable|numeric|min:0',
        ]);

        $account = $listing->marketplaceAccount;
        if (! $account || ! $account->credentials) {
            return back()->with('error', 'Conta do marketplace não encontrada ou sem credenciais.');
        }

        $payload = [];
        $skippedReasons = [];

        // Items with variations: price & stock are managed per-variation
        if ($hasVariations) {
            $skippedReasons[] = 'Preço e Estoque são gerenciados por variação — edite cada variação individualmente abaixo.';
        } else {
            $payload['price']              = (float) $validated['price'];
            $payload['available_quantity'] = (int) $validated['available_quantity'];
        }

        if (! $isHandlingLocked) {
            $payload['shipping'] = ['handling_time' => (int) $validated['handling_time']];
        }

        if (! $isCatalogItem && ! empty($validated['title'])) {
            $payload['title'] = $validated['title'];
        }

        if (! empty($validated['shipping_width'])) {
            $payload['shipping'] = array_merge($payload['shipping'] ?? [], [
                'dimensions' => [
                    'width'  => $validated['shipping_width'],
                    'height' => $validated['shipping_height'] ?? 0,
                    'length' => $validated['shipping_length'] ?? 0,
                    'weight' => $validated['shipping_weight'] ?? 0,
                ],
            ]);
        }

        if (! empty($validated['attributes'])) {
            $payload['attributes'] = collect($validated['attributes'])
                ->filter(fn ($v) => $v !== '' && $v !== null)
                ->map(fn ($value, $id) => ['id' => $id, 'value_name' => $value])
                ->values()
                ->all();
        }

        if (empty($payload)) {
            $info = ! empty($skippedReasons) ? implode(' ', $skippedReasons) : 'Todos os campos deste anúncio são gerenciados pelo ML.';
            return redirect()->route('listings.show', $listing)->with('info', $info);
        }

        try {
            $service = new MercadoLivreService($account);
            $service->updateItem($listing->external_id, $payload);
        } catch (\Throwable $e) {
            $errorBody = $e->getMessage();

            if (str_contains($errorBody, 'field_not_updatable') || str_contains($errorBody, 'not_modifiable') || str_contains($errorBody, 'family_name')) {
                $removedFields = $this->removeRejectedFields($payload, $errorBody, $listing);

                if (! empty($removedFields) && ! empty($payload)) {
                    try {
                        $service->updateItem($listing->external_id, $payload);

                        $fieldLabels = array_map(fn ($f) => self::FIELD_LABELS[$f] ?? $f, $removedFields);
                        $infoMsg = 'Alguns campos não puderam ser alterados: ' . implode(', ', $fieldLabels) . '.';
                        if (in_array('price', $removedFields) || in_array('available_quantity', $removedFields)) {
                            $infoMsg .= ' Para anúncios com variações, edite Preço e Estoque em cada variação.';
                            $listing->update(['meta' => array_merge($listing->meta ?? [], ['has_variations' => true])]);
                        }
                        return redirect()->route('listings.show', $listing)
                            ->with('success', 'Anúncio atualizado com sucesso.')
                            ->with('info', $infoMsg);
                    } catch (\Throwable $e2) {
                        return back()->with('error', self::friendlyMlError($e2->getMessage()));
                    }
                }

                if (! empty($removedFields) && empty($payload)) {
                    $fieldLabels = array_map(fn ($f) => self::FIELD_LABELS[$f] ?? $f, $removedFields);
                    $infoMsg = 'Nenhum campo pôde ser alterado no nível do anúncio: ' . implode(', ', $fieldLabels) . '.';
                    if (in_array('price', $removedFields) || in_array('available_quantity', $removedFields)) {
                        $infoMsg .= ' Para anúncios com variações, edite Preço e Estoque em cada variação.';
                        $listing->update(['meta' => array_merge($listing->meta ?? [], ['has_variations' => true])]);
                    }
                    return redirect()->route('listings.show', $listing)->with('info', $infoMsg);
                }
            }

            return back()->with('error', self::friendlyMlError($errorBody));
        }

        if (! $hasVariations) {
            $listing->update([
                'title'              => $validated['title'] ?? $listing->title,
                'price'              => $validated['price'],
                'available_quantity' => $validated['available_quantity'],
            ]);
        } else {
            if (! empty($validated['title']) && ! $isCatalogItem) {
                $listing->update(['title' => $validated['title']]);
            }
        }

        $successMsg = 'Anúncio atualizado com sucesso.';
        $redirect   = redirect()->route('listings.show', $listing)->with('success', $successMsg);

        if (! empty($skippedReasons)) {
            $redirect = $redirect->with('info', implode(' ', $skippedReasons));
        }

        return $redirect;
    }

    private const FIELD_LABELS = [
        'title'                  => 'Título',
        'price'                  => 'Preço',
        'available_quantity'     => 'Estoque',
        'shipping.handling_time' => 'Prazo de disponibilidade',
        'shipping'               => 'Frete',
    ];

    private function removeRejectedFields(array &$payload, string $errorBody, MarketplaceListing $listing): array
    {
        $removedFields = [];
        $metaUpdates   = [];

        $fieldsToCheck = [
            'title'                  => fn () => isset($payload['title']),
            'price'                  => fn () => isset($payload['price']),
            'available_quantity'     => fn () => isset($payload['available_quantity']),
            'shipping.handling_time' => fn () => isset($payload['shipping']['handling_time']),
        ];

        foreach ($fieldsToCheck as $field => $exists) {
            if (str_contains($errorBody, $field) && $exists()) {
                if ($field === 'shipping.handling_time') {
                    unset($payload['shipping']['handling_time']);
                    if (empty($payload['shipping'])) {
                        unset($payload['shipping']);
                    }
                    $metaUpdates['handling_time_locked'] = true;
                } else {
                    unset($payload[$field]);
                }

                $removedFields[] = $field;
            }
        }

        if (str_contains($errorBody, 'family_name') || str_contains($errorBody, 'cannot modify the title')) {
            unset($payload['title']);
            $metaUpdates['family_name'] = 'catalog';
            if (! in_array('title', $removedFields)) {
                $removedFields[] = 'title';
            }
        }

        if (! empty($metaUpdates)) {
            $listing->update(['meta' => array_merge($listing->meta ?? [], $metaUpdates)]);
        }

        return $removedFields;
    }

    private static function friendlyMlError(string $rawError): string
    {
        // Try to extract the JSON from the raw error message
        if (preg_match('/\{.*\}/s', $rawError, $m)) {
            $data = json_decode($m[0], true);
            if ($data) {
                $causes = $data['cause'] ?? [];
                if (! empty($causes)) {
                    $messages = [];
                    foreach ($causes as $cause) {
                        $refs = implode(', ', array_map(
                            fn ($r) => self::FIELD_LABELS[$r] ?? $r,
                            $cause['references'] ?? []
                        ));
                        $msg = match ($cause['code'] ?? '') {
                            'field_not_updatable'       => "O campo \"{$refs}\" não pode ser alterado neste anúncio (gerenciado pelo ML).",
                            'item.price.not_modifiable' => "O preço não pode ser alterado neste anúncio (gerenciado pelo ML).",
                            default                     => $cause['message'] ?? 'Erro desconhecido',
                        };
                        $messages[] = $msg;
                    }
                    return implode(' ', $messages);
                }

                return $data['error'] ?? $data['message'] ?? $rawError;
            }
        }

        return 'Erro ao atualizar no Mercado Livre. Tente novamente em alguns instantes.';
    }

    public function updateVariation(Request $request, MarketplaceListing $listing, string $variationId)
    {
        $validated = $request->validate([
            'price'              => 'nullable|numeric|min:0',
            'available_quantity' => 'required|integer|min:0',
            'seller_custom_field' => 'nullable|string|max:100',
            'picture_ids'        => 'nullable|array',
            'picture_ids.*'      => 'string',
        ]);

        $account = $listing->marketplaceAccount;
        if (! $account || ! $account->credentials) {
            return back()->with('error', 'Conta não encontrada ou sem credenciais.');
        }

        try {
            $service  = new MercadoLivreService($account);
            $liveData = $service->getItem($listing->external_id);

            $variations = collect($liveData['variations'] ?? [])
                ->map(function ($v) use ($variationId, $validated) {
                    if ((string) $v['id'] !== $variationId) {
                        return $v;
                    }

                    $v['available_quantity'] = (int) $validated['available_quantity'];

                    if (! empty($validated['price'])) {
                        $v['price'] = (float) $validated['price'];
                    }

                    if (array_key_exists('seller_custom_field', $validated)) {
                        $v['seller_custom_field'] = $validated['seller_custom_field'] ?: null;
                    }

                    if (! empty($validated['picture_ids'])) {
                        $v['picture_ids'] = $validated['picture_ids'];
                    }

                    return $v;
                })
                ->values()
                ->all();

            $service->updateItem($listing->external_id, ['variations' => $variations]);

        } catch (\Throwable $e) {
            return back()->with('error', 'Erro ao atualizar variação: ' . self::friendlyMlError($e->getMessage()));
        }

        return redirect()->route('listings.show', $listing)
            ->with('success', 'Variação atualizada com sucesso.');
    }

    public function deleteVariation(Request $request, MarketplaceListing $listing, string $variationId)
    {
        $account = $listing->marketplaceAccount;
        if (! $account || ! $account->credentials) {
            return back()->with('error', 'Conta não encontrada ou sem credenciais.');
        }

        try {
            $service  = new MercadoLivreService($account);
            $liveData = $service->getItem($listing->external_id);

            $variations = collect($liveData['variations'] ?? [])
                ->reject(fn ($v) => (string) $v['id'] === $variationId)
                ->values()
                ->all();

            if (empty($variations)) {
                return back()->with('error', 'Não é possível remover a última variação. O anúncio precisa ter pelo menos uma.');
            }

            $service->updateItem($listing->external_id, ['variations' => $variations]);
        } catch (\Throwable $e) {
            return back()->with('error', 'Erro ao remover variação: ' . self::friendlyMlError($e->getMessage()));
        }

        return redirect()->route('listings.show', $listing)
            ->with('success', 'Variação removida com sucesso.');
    }

    public function addVariation(Request $request, MarketplaceListing $listing)
    {
        $validated = $request->validate([
            'price'              => 'nullable|numeric|min:0',
            'available_quantity' => 'required|integer|min:0',
            'seller_custom_field' => 'nullable|string|max:100',
            'picture_ids'        => 'nullable|array',
            'picture_ids.*'      => 'string',
            'combinations'       => 'required|array|min:1',
            'combinations.*.id'         => 'required|string',
            'combinations.*.value_name' => 'required|string|max:255',
        ]);

        $account = $listing->marketplaceAccount;
        if (! $account || ! $account->credentials) {
            return back()->with('error', 'Conta não encontrada ou sem credenciais.');
        }

        try {
            $service  = new MercadoLivreService($account);
            $liveData = $service->getItem($listing->external_id);

            $existingVariations = $liveData['variations'] ?? [];

            $newVariation = [
                'attribute_combinations' => collect($validated['combinations'])
                    ->map(fn ($c) => ['id' => $c['id'], 'value_name' => $c['value_name']])
                    ->values()
                    ->all(),
                'available_quantity' => (int) $validated['available_quantity'],
            ];

            if (! empty($validated['price'])) {
                $newVariation['price'] = (float) $validated['price'];
            }
            if (! empty($validated['seller_custom_field'])) {
                $newVariation['seller_custom_field'] = $validated['seller_custom_field'];
            }
            if (! empty($validated['picture_ids'])) {
                $newVariation['picture_ids'] = $validated['picture_ids'];
            }

            $existingVariations[] = $newVariation;

            $service->updateItem($listing->external_id, ['variations' => $existingVariations]);

        } catch (\Throwable $e) {
            return back()->with('error', 'Erro ao adicionar variação: ' . self::friendlyMlError($e->getMessage()));
        }

        return redirect()->route('listings.show', $listing)
            ->with('success', 'Nova variação adicionada com sucesso.');
    }

    public function updateDescription(Request $request, MarketplaceListing $listing)
    {
        $text = $request->validate(['description' => 'required|string'])['description'];

        $account = $listing->marketplaceAccount;
        if (! $account || ! $account->credentials) {
            return back()->with('error', 'Conta não encontrada ou sem credenciais.');
        }

        try {
            $service = new MercadoLivreService($account);
            $service->updateDescription($listing->external_id, $text);
        } catch (\Throwable $e) {
            return back()->with('error', 'Erro ao salvar descrição: ' . self::friendlyMlError($e->getMessage()));
        }

        return redirect()->route('listings.show', $listing)
            ->with('success', 'Descrição atualizada com sucesso.');
    }

    public function addPicture(Request $request, MarketplaceListing $listing)
    {
        $request->validate([
            'picture_url'  => 'nullable|url',
            'picture_file' => 'nullable|file|mimes:jpg,jpeg,png|max:10240',
        ]);

        if (! $request->hasFile('picture_file') && ! $request->input('picture_url')) {
            return back()->with('error', 'Informe uma URL ou faça upload de um arquivo.');
        }

        $account = $listing->marketplaceAccount;
        if (! $account || ! $account->credentials) {
            return back()->with('error', 'Conta não encontrada ou sem credenciais.');
        }

        try {
            $service  = new MercadoLivreService($account);
            $item     = $service->getItem($listing->external_id);
            $existing = collect($item['pictures'] ?? [])
                ->map(fn ($p) => ['id' => $p['id']])
                ->all();

            if ($request->hasFile('picture_file')) {
                // Upload file directly to ML
                $pictureId = $service->uploadPicture($request->file('picture_file'));
                $pictures  = array_merge($existing, [['id' => $pictureId]]);
            } else {
                // Add by URL
                $pictures = array_merge($existing, [['source' => $request->input('picture_url')]]);
            }

            $service->updateItem($listing->external_id, ['pictures' => $pictures]);

        } catch (\Throwable $e) {
            return back()->with('error', 'Erro ao adicionar imagem: ' . self::friendlyMlError($e->getMessage()));
        }

        return redirect()->route('listings.show', $listing)
            ->with('success', 'Imagem adicionada com sucesso.');
    }

    public function removePicture(Request $request, MarketplaceListing $listing, string $pictureId)
    {
        $account = $listing->marketplaceAccount;
        if (! $account || ! $account->credentials) {
            return back()->with('error', 'Conta não encontrada ou sem credenciais.');
        }

        try {
            $service  = new MercadoLivreService($account);
            $item     = $service->getItem($listing->external_id);
            $pictures = collect($item['pictures'] ?? [])
                ->reject(fn ($p) => $p['id'] === $pictureId)
                ->map(fn ($p) => ['id' => $p['id']])
                ->values()
                ->all();
            $service->updateItem($listing->external_id, ['pictures' => $pictures]);
        } catch (\Throwable $e) {
            return back()->with('error', 'Erro ao remover imagem: ' . self::friendlyMlError($e->getMessage()));
        }

        return redirect()->route('listings.show', $listing)
            ->with('success', 'Imagem removida com sucesso.');
    }

    public function bulkAction(Request $request)
    {
        $validated = $request->validate([
            'ids'    => 'required|array|min:1',
            'ids.*'  => 'integer|exists:marketplace_listings,id',
            'action' => 'required|in:pause,activate',
        ]);

        $listings   = MarketplaceListing::whereIn('id', $validated['ids'])->get();
        $newStatus  = $validated['action'] === 'pause' ? 'paused' : 'active';
        $successCnt = 0;
        $errorCnt   = 0;

        foreach ($listings as $listing) {
            $account = $listing->marketplaceAccount;
            if (! $account || ! $account->credentials) {
                $errorCnt++;
                continue;
            }

            try {
                $service = new MercadoLivreService($account);
                $service->updateItem($listing->external_id, ['status' => $newStatus]);
                $listing->update(['status' => $newStatus]);
                $successCnt++;
            } catch (\Throwable $e) {
                Log::warning("BulkAction {$newStatus} listing {$listing->external_id}: " . $e->getMessage());
                $errorCnt++;
            }
        }

        $label = $newStatus === 'active' ? 'ativados' : 'pausados';
        $msg   = "{$successCnt} anúncio(s) {$label} com sucesso.";
        if ($errorCnt > 0) {
            $msg .= " {$errorCnt} erro(s).";
        }

        return redirect()->route('listings.index')->with('success', $msg);
    }

    public function toggleStatus(MarketplaceListing $listing)
    {
        $account = $listing->marketplaceAccount;
        if (! $account || ! $account->credentials) {
            return back()->with('error', 'Conta do marketplace não encontrada ou sem credenciais.');
        }

        $newStatus = $listing->status === 'active' ? 'paused' : 'active';

        try {
            $service = new MercadoLivreService($account);
            $service->updateItem($listing->external_id, ['status' => $newStatus]);
        } catch (\Throwable $e) {
            return back()->with('error', 'Erro ao alterar status: ' . self::friendlyMlError($e->getMessage()));
        }

        $listing->update(['status' => $newStatus]);

        $label = $newStatus === 'active' ? 'ativado' : 'pausado';
        return redirect()->route('listings.show', $listing)
            ->with('success', "Anúncio {$label} com sucesso.");
    }

    public function linkProduct(Request $request, MarketplaceListing $listing)
    {
        $validated = $request->validate([
            'product_id'       => 'required|exists:products,id',
            'product_quantity' => 'required|integer|min:1',
        ]);

        $listing->update([
            'product_id'       => $validated['product_id'],
            'product_quantity' => $validated['product_quantity'],
        ]);

        return redirect()->route('listings.show', $listing)
            ->with('success', 'Produto vinculado com sucesso.');
    }

    public function unlinkProduct(MarketplaceListing $listing)
    {
        $listing->update(['product_id' => null, 'product_quantity' => 1]);

        return redirect()->route('listings.show', $listing)
            ->with('success', 'Vínculo removido.');
    }

    public function createProduct(Request $request, MarketplaceListing $listing)
    {
        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'price'            => 'required|numeric|min:0',
            'sku'              => 'nullable|string|max:100',
            'product_quantity' => 'required|integer|min:1',
        ]);

        $product = Product::create([
            'name'   => $validated['name'],
            'price'  => $validated['price'],
            'sku'    => $validated['sku'] ?: ($listing->meta['seller_sku'] ?? $listing->external_id),
            'status' => ProductStatus::Active,
            'meta'   => ['created_from_listing' => $listing->external_id],
        ]);

        $listing->update([
            'product_id'       => $product->id,
            'product_quantity' => $validated['product_quantity'],
        ]);

        return redirect()->route('listings.show', $listing)
            ->with('success', "Produto \"{$product->name}\" criado e vinculado.");
    }

    public function publish(Request $request)
    {
        $validated = $request->validate([
            'marketplace_account_id' => 'required|exists:marketplace_accounts,id',
            'product_id'             => 'required|exists:products,id',
            'title'                  => 'required|string|max:60',
            'category_id'            => 'required|string',
            'listing_type_id'        => 'required|string|in:gold_special,gold_pro,free',
            'price'                  => 'required|numeric|min:0',
            'available_quantity'     => 'required|integer|min:1',
            'description'            => 'nullable|string',
            'handling_time'          => 'required|integer|min:0|max:20',
            'condition'              => 'required|string|in:new,used,not_specified',
            'attributes'             => 'nullable|array',
            'pictures'               => 'nullable|array',
            'pictures.*'             => 'url',
        ]);

        $account = MarketplaceAccount::findOrFail($validated['marketplace_account_id']);

        if (! $account->credentials) {
            return back()->with('error', 'Conta sem credenciais configuradas.');
        }

        try {
            $service = new MercadoLivreService($account);

            $payload = [
                'title'              => $validated['title'],
                'category_id'        => $validated['category_id'],
                'price'              => (float) $validated['price'],
                'available_quantity' => (int) $validated['available_quantity'],
                'buying_mode'        => 'buy_it_now',
                'listing_type_id'    => $validated['listing_type_id'],
                'condition'          => $validated['condition'],
                'currency_id'        => 'BRL',
                'shipping'           => [
                    'mode'          => 'me2',
                    'handling_time' => (int) $validated['handling_time'],
                ],
            ];

            if (! empty($validated['attributes'])) {
                $payload['attributes'] = collect($validated['attributes'])
                    ->map(fn ($value, $id) => ['id' => $id, 'value_name' => $value])
                    ->values()
                    ->all();
            }

            if (! empty($validated['pictures'])) {
                $payload['pictures'] = array_map(fn ($url) => ['source' => $url], $validated['pictures']);
            }

            $item = $service->publishItem($payload);

            if (empty($item['id'])) {
                return back()->with('error', 'Erro ao publicar: resposta inválida do ML.');
            }

            // Update description if provided
            if (! empty($validated['description'])) {
                try {
                    $service->updateDescription($item['id'], $validated['description']);
                } catch (\Throwable $e) {
                    Log::warning("Publish: erro ao salvar descrição: " . $e->getMessage());
                }
            }

            // Create local listing record
            $listing = MarketplaceListing::create([
                'marketplace_account_id' => $account->id,
                'external_id'            => $item['id'],
                'product_id'             => $validated['product_id'],
                'product_quantity'       => 1,
                'title'                  => $validated['title'],
                'price'                  => $validated['price'],
                'available_quantity'     => $validated['available_quantity'],
                'status'                 => 'active',
                'meta'                   => [
                    'ml_item_id'      => $item['id'],
                    'ml_status'       => 'active',
                    'ml_permalink'    => $item['permalink'] ?? null,
                    'category_id'     => $validated['category_id'],
                    'listing_type_id' => $validated['listing_type_id'],
                ],
            ]);

        } catch (\Throwable $e) {
            Log::error("Publish listing error: " . $e->getMessage());
            return back()->with('error', 'Erro ao publicar no Mercado Livre: ' . self::friendlyMlError($e->getMessage()));
        }

        return redirect()->route('listings.show', $listing)
            ->with('success', 'Anúncio publicado com sucesso no Mercado Livre!');
    }

    public function publishForm(Request $request)
    {
        $accounts = MarketplaceAccount::active()->orderBy('account_name')->get();
        $products = Product::active()->orderBy('name')->get(['id', 'name', 'sku', 'price']);
        $preselectedProduct = $request->input('product_id')
            ? Product::find($request->input('product_id'))
            : null;

        return view('marketplace-listings.publish', compact('accounts', 'products', 'preselectedProduct'));
    }

    public function searchCategories(Request $request)
    {
        $query      = $request->validate(['q' => 'required|string|min:2'])['q'];
        $accountId  = $request->input('account_id');
        $account    = MarketplaceAccount::findOrFail($accountId);

        try {
            $service    = new MercadoLivreService($account);
            $categories = $service->searchCategories($query);
        } catch (\Throwable $e) {
            $categories = [];
        }

        return response()->json($categories);
    }

    public function getCategoryAttributes(Request $request)
    {
        $categoryId = $request->validate(['category_id' => 'required|string'])['category_id'];
        $accountId  = $request->input('account_id');
        $account    = MarketplaceAccount::findOrFail($accountId);

        try {
            $service    = new MercadoLivreService($account);
            $attributes = $service->getCategoryAttributes($categoryId);
        } catch (\Throwable $e) {
            $attributes = [];
        }

        return response()->json($attributes);
    }
}
