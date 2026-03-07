<?php

namespace App\Http\Controllers;

use App\Enums\ProductStatus;
use App\Jobs\SyncListingQualityScore;
use App\Models\MarketplaceAccount;
use App\Models\MarketplaceListing;
use App\Models\Product;
use App\Services\AiService;
use App\Services\Marketplaces\MercadoLivreService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MarketplaceListingController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = MarketplaceListing::with(['marketplaceAccount', 'product']);

            // ── Basic filters ──────────────────────────────────────────────────────
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

            // Quality
            if ($quality = $request->input('quality')) {
                match ($quality) {
                    'none'   => $query->whereRaw("(meta->>'quality_score') IS NULL"),
                    'low'    => $query->whereRaw("(meta->>'quality_score')::int < 50"),
                    'medium' => $query->whereRaw("(meta->>'quality_score')::int >= 50 AND (meta->>'quality_score')::int < 66"),
                    'high'   => $query->whereRaw("(meta->>'quality_score')::int >= 66"),
                    default  => null,
                };
            }

            // ── Advanced filters ───────────────────────────────────────────────────

            // Price range
            if ($priceMin = $request->input('price_min')) {
                $query->where('price', '>=', (float) $priceMin);
            }
            if ($priceMax = $request->input('price_max')) {
                $query->where('price', '<=', (float) $priceMax);
            }

            // Stock presets
            if ($stock = $request->input('stock')) {
                match ($stock) {
                    'zero'  => $query->where('available_quantity', 0),
                    'low'   => $query->whereBetween('available_quantity', [1, 5]),
                    'ok'    => $query->where('available_quantity', '>', 5),
                    default => null,
                };
            }

            // Marketplace platform (join on marketplace_accounts)
            if ($platform = $request->input('platform')) {
                $query->whereHas('marketplaceAccount', fn ($q) => $q->where('marketplace_type', $platform));
            }

            // Company filter
            if ($companyId = $request->input('company')) {
                $query->whereHas('marketplaceAccount', fn ($q) => $q->where('company_id', $companyId));
            }

            // Condition (new/used)
            if ($condition = $request->input('condition')) {
                $query->whereRaw("meta->>'condition' = ?", [$condition]);
            }

            // Listing type
            if ($listingType = $request->input('listing_type')) {
                $query->whereRaw("meta->>'listing_type_id' = ?", [$listingType]);
            }

            // Free shipping — use JSON boolean check without risky ::boolean cast
            if ($request->input('free_shipping') === '1') {
                $query->whereRaw("meta->>'is_free_shipping' = 'true'");
            }

            // Fulfillment
            if ($request->input('fulfillment') === '1') {
                $query->whereRaw("meta->>'is_fulfillment' = 'true'");
            }

            // Kit (listing requires multiple product units)
            if ($request->input('kit') === '1') {
                $query->where('product_quantity', '>', 1);
            }

            // Category
            if ($category = $request->input('category')) {
                $query->whereRaw("meta->>'category_name' = ?", [$category]);
            }

            // ── Sorting ────────────────────────────────────────────────────────────
            match ($request->input('sort', 'newest')) {
                'oldest'      => $query->oldest(),
                'price_asc'   => $query->orderBy('price'),
                'price_desc'  => $query->orderByDesc('price'),
                'stock_asc'   => $query->orderBy('available_quantity'),
                'stock_desc'  => $query->orderByDesc('available_quantity'),
                'title_asc'   => $query->orderBy('title'),
                'sold_desc'   => $query->orderByRaw("(meta->>'sold_quantity')::int DESC NULLS LAST"),
                default       => $query->latest(),
            };

            $listings = $query->paginate(25)->withQueryString();
            $accounts = MarketplaceAccount::orderBy('account_name')->get();

            // Data for advanced filter selects — wrapped in try-catch so filter
            // data failure never blocks the main listing
            try {
                $companies = \App\Models\Company::orderBy('name')->get(['id', 'name']);
            } catch (\Throwable $e) {
                Log::warning('listings.index: could not load companies', ['error' => $e->getMessage()]);
                $companies = collect();
            }

            try {
                $categoryOptions = DB::table('marketplace_listings')
                    ->whereRaw("meta->>'category_name' IS NOT NULL AND meta->>'category_name' != ''")
                    ->selectRaw("meta->>'category_name' as category_name")
                    ->distinct()
                    ->orderByRaw("meta->>'category_name'")
                    ->pluck('category_name');
            } catch (\Throwable $e) {
                Log::warning('listings.index: could not load categories', ['error' => $e->getMessage()]);
                $categoryOptions = collect();
            }

            $totalListings = MarketplaceListing::count();
            $activeCount   = MarketplaceListing::where('status', 'active')->count();
            $pausedCount   = MarketplaceListing::where('status', 'paused')->count();
            $unlinkedCount = MarketplaceListing::unlinked()->count();
            $perAccount    = MarketplaceListing::with('marketplaceAccount')
                ->selectRaw("marketplace_account_id, count(*) as total, sum(case when status = 'active' then 1 else 0 end) as active_count")
                ->groupBy('marketplace_account_id')
                ->get();

            return view('marketplace-listings.index', compact(
                'listings', 'accounts', 'companies', 'categoryOptions',
                'totalListings', 'activeCount', 'pausedCount', 'unlinkedCount', 'perAccount'
            ));

        } catch (\Throwable $e) {
            Log::error('listings.index 500', [
                'error'   => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'trace'   => collect($e->getTrace())->take(8)->toArray(),
            ]);
            throw $e;
        }
    }

    public function show(MarketplaceListing $listing)
    {
        $step = 'init';
        try {
            $step = 'load-relations';
            $listing->load(['marketplaceAccount', 'product']);

            $step     = 'load-products';
            $products = Product::active()->orderBy('name')->get(['id', 'name', 'sku', 'price']);

            $liveData    = null;
            $quality     = null;
            $description = null;
            $categoryAttributes    = [];
            $availableListingTypes = [];
            $apiError              = null;

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

                    $step = 'api-listing-types';
                    $availableListingTypes = $service->getAvailableListingTypes($listing->external_id);

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
                    } else {
                        $metaPatch['is_fulfillment'] = false;
                    }

                    // Cache quality score so it shows up in the listing index
                    if (! empty($quality) && isset($quality['score'])) {
                        $metaPatch['quality_score']     = (int) $quality['score'];
                        $metaPatch['quality_level']     = $quality['level_wording'] ?? ($quality['level'] ?? null);
                        $metaPatch['quality_synced_at'] = now()->toDateTimeString();
                    }

                    // Clean up legacy lock flags that should not persist
                    $cleanMeta = $listing->meta ?? [];
                    unset($cleanMeta['handling_time_locked'], $cleanMeta['locked_fields']);
                    $listing->update(['meta' => array_merge($cleanMeta, $metaPatch)]);

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
            $aiConfigured = (new AiService())->isConfigured();

            $viewData = compact(
                'listing', 'products',
                'liveData', 'quality', 'description', 'categoryAttributes', 'availableListingTypes', 'apiError',
                'salesStats', 'totalQty', 'totalRevenue', 'avgTicket',
                'aiConfigured'
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

        $isCatalogItem = ! empty($meta['family_name']) || ! empty($meta['catalog_product_id']);
        $isFulfillment = ! empty($meta['is_fulfillment']);
        $hasVariations = ! empty($meta['has_variations']);

        $validated = $request->validate([
            'title'              => $isCatalogItem ? 'nullable|string|max:60' : 'required|string|max:60',
            'price'              => 'required|numeric|min:0',
            'available_quantity' => 'required|integer|min:0',
            'handling_time'      => 'nullable|integer|min:0|max:20', // read-only, kept for reference
            'attributes'         => 'nullable|array',
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

        // handling_time is NOT in ML's list of updatable fields for active items — skip it

        if (! $isCatalogItem && ! empty($validated['title'])) {
            $payload['title'] = $validated['title'];
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

        // Only persist truly permanent locks (catalog items), not temporary API rejections
        if (in_array('price', $removedFields) || in_array('available_quantity', $removedFields)) {
            $metaUpdates['has_variations'] = true;
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

    public function updateListingType(Request $request, MarketplaceListing $listing)
    {
        // MLB (Brasil): free, gold_special (Clássico), gold_pro (Premium)
        // Other sites may use gold_premium — accept all and let ML validate
        $validated = $request->validate([
            'listing_type_id' => 'required|string|in:free,gold_special,gold_pro,gold_premium,gold,silver,bronze',
        ]);

        $account = $listing->marketplaceAccount;
        if (! $account || ! $account->credentials) {
            return back()->with('error', 'Conta não encontrada ou sem credenciais.');
        }

        try {
            $service = new MercadoLivreService($account);
            $service->updateListingType($listing->external_id, $validated['listing_type_id']);

            $listing->update([
                'meta' => array_merge($listing->meta ?? [], [
                    'listing_type_id' => $validated['listing_type_id'],
                ]),
            ]);
        } catch (\Throwable $e) {
            return back()->with('error', 'Erro ao alterar tipo de anúncio: ' . self::friendlyMlError($e->getMessage()));
        }

        $typeLabels = [
            'gold_pro'     => 'Premium',
            'gold_premium' => 'Premium',
            'gold_special' => 'Clássico',
            'gold'         => 'Ouro',
            'silver'       => 'Prata',
            'bronze'       => 'Bronze',
            'free'         => 'Grátis',
        ];
        $label = $typeLabels[$validated['listing_type_id']] ?? $validated['listing_type_id'];

        return redirect()->route('listings.show', $listing)
            ->with('success', "Tipo de anúncio alterado para {$label} com sucesso.");
    }

    public function updateShipping(Request $request, MarketplaceListing $listing)
    {
        $validated = $request->validate([
            'free_shipping'   => 'nullable|boolean',
            'local_pick_up'   => 'nullable|boolean',
            'shipping_mode'   => 'nullable|string|in:me1,me2,custom,not_specified',
            'shipping_height' => 'nullable|numeric|min:0',
            'shipping_width'  => 'nullable|numeric|min:0',
            'shipping_length' => 'nullable|numeric|min:0',
            'shipping_weight' => 'nullable|numeric|min:0',
        ]);

        $account = $listing->marketplaceAccount;
        if (! $account || ! $account->credentials) {
            return back()->with('error', 'Conta não encontrada ou sem credenciais.');
        }

        $shippingMode = $validated['shipping_mode'] ?? ($listing->meta['shipping_mode'] ?? 'me2');
        $shippingPayload = [];

        if (isset($validated['free_shipping'])) {
            $shippingPayload['free_shipping'] = (bool) $validated['free_shipping'];
        }
        if (isset($validated['local_pick_up'])) {
            $shippingPayload['local_pick_up'] = (bool) $validated['local_pick_up'];
        }

        // Dimensions: only ME1 accepts them via API. ME2 dimensions are managed by ML.
        // ME1 format: "HEIGHTxWIDTHxLENGTH,WEIGHT_IN_GRAMS" (string)
        if ($shippingMode === 'me1' && ! empty($validated['shipping_height'])) {
            $shippingPayload['dimensions'] = sprintf(
                '%dx%dx%d,%d',
                (int) round($validated['shipping_height']),
                (int) round($validated['shipping_width'] ?? 0),
                (int) round($validated['shipping_length'] ?? 0),
                (int) round(($validated['shipping_weight'] ?? 0) * 1000) // kg → grams
            );
        }

        if (empty($shippingPayload)) {
            return back()->with('info', 'Nenhuma configuração de envio para atualizar.');
        }

        try {
            $service = new MercadoLivreService($account);
            $service->updateShipping($listing->external_id, $shippingPayload);
        } catch (\Throwable $e) {
            return back()->with('error', 'Erro ao atualizar configurações de envio: ' . self::friendlyMlError($e->getMessage()));
        }

        return redirect()->route('listings.show', $listing)
            ->with('success', 'Configurações de envio atualizadas com sucesso.');
    }

    /**
     * Analyze pending quality variables and automatically improve the listing using AI.
     * Handles: description, title, and technical attribute suggestions.
     * Returns a JSON stream of completed improvements and any errors.
     */
    public function improveWithAi(MarketplaceListing $listing): \Illuminate\Http\JsonResponse
    {
        $account = $listing->marketplaceAccount;
        if (! $account || ! $account->credentials) {
            return response()->json(['error' => 'Conta não encontrada ou sem credenciais.'], 422);
        }

        try {
            $ai = new AiService();
            if (! $ai->isConfigured()) {
                return response()->json([
                    'error' => 'IA não configurada. Acesse Configurações → Inteligência Artificial.',
                ], 422);
            }

            $service     = new MercadoLivreService($account);
            $quality     = $service->getItemQuality($listing->external_id);
            $liveData    = $service->getItemWithVariations($listing->external_id);
            $description = $service->getItemDescription($listing->external_id);
            $categoryAttributes = ! empty($liveData['category_id'])
                ? $service->getCategoryAttributes($liveData['category_id'])
                : [];

            $improvements = [];
            $skipped      = [];
            $errors       = [];

            $isCatalog    = ! empty($liveData['family_name']) || ! empty($liveData['catalog_product_id']);
            $hasVariations = ! empty($liveData['variations']);
            $title        = $liveData['title'] ?? $listing->title;
            $category     = $liveData['category_id'] ?? '';

            // Collect all pending quality variables across all buckets
            $pendingVars = collect($quality['buckets'] ?? [])
                ->flatMap(fn ($b) => $b['variables'] ?? [])
                ->where('status', 'PENDING')
                ->values();

            if ($pendingVars->isEmpty()) {
                return response()->json([
                    'improvements' => [],
                    'skipped'      => [],
                    'errors'       => [],
                    'message'      => 'Parabéns! Não há itens pendentes de melhoria na qualidade do anúncio.',
                ]);
            }

            foreach ($pendingVars as $variable) {
                $varKey = $variable['key'] ?? '';

                // ── Description ─────────────────────────────────────────────
                if (str_contains($varKey, 'DESCRIPTION') || str_contains($varKey, 'DESCRI')) {
                    try {
                        $existingDesc = $description['plain_text'] ?? '';

                        // Build rich context from live attributes
                        $attrContext = collect($liveData['attributes'] ?? [])
                            ->filter(fn ($a) => ! empty($a['value_name']))
                            ->mapWithKeys(fn ($a) => [$a['name'] ?? $a['id'] => $a['value_name']])
                            ->toArray();

                        $prompts = AiService::buildDescriptionPrompt(
                            title: $title,
                            category: $category,
                            existingDescription: $existingDesc,
                            attributes: $attrContext,
                        );

                        $newDesc = $ai->generateText($prompts['system'], $prompts['user'], 2000);

                        if (! empty($newDesc)) {
                            $service->updateDescription($listing->external_id, $newDesc);
                            $improvements[] = [
                                'icon'    => '📝',
                                'label'   => 'Descrição',
                                'detail'  => 'Descrição profissional gerada e salva com sucesso.',
                            ];
                        }
                    } catch (\Throwable $e) {
                        $errors[] = 'Descrição: ' . $e->getMessage();
                    }
                }

                // ── Title ────────────────────────────────────────────────────
                elseif (str_contains($varKey, 'TITLE')) {
                    if ($isCatalog) {
                        $skipped[] = ['label' => 'Título', 'reason' => 'Item vinculado ao catálogo ML — título gerenciado pelo ML.'];
                        continue;
                    }
                    try {
                        $system = <<<'SYS'
Você é especialista em SEO e copywriting para Mercado Livre Brasil.
Crie um título otimizado para máxima visibilidade nos resultados de busca.
Regras:
- Máximo 60 caracteres
- Inclua: produto + característica principal + marca (se conhecida) + aplicação/compatibilidade
- Sem pontuação desnecessária, sem emojis, sem repetições
- Retorne APENAS o título, sem explicações ou aspas
SYS;
                        $user = "Produto: {$title}\nCrie um título otimizado (máx 60 chars):";

                        $newTitle = trim($ai->generateText($system, $user, 80));
                        $newTitle = mb_substr(preg_replace('/^["\']|["\']$/', '', $newTitle), 0, 60);

                        if (! empty($newTitle) && $newTitle !== $title) {
                            $service->updateItem($listing->external_id, ['title' => $newTitle]);
                            $listing->update(['title' => $newTitle]);
                            $improvements[] = [
                                'icon'   => '✏️',
                                'label'  => 'Título',
                                'detail' => "Atualizado para: \"{$newTitle}\"",
                            ];
                        }
                    } catch (\Throwable $e) {
                        $errors[] = 'Título: ' . $e->getMessage();
                    }
                }

                // ── Technical Specifications / Attributes ─────────────────
                elseif (str_contains($varKey, 'TECHNICAL_SPEC') || str_contains($varKey, 'TS_MAIN')) {
                    try {
                        // Find attributes that are required and missing
                        $currentAttrs = collect($liveData['attributes'] ?? [])->keyBy('id');
                        $missingAttrs = collect($categoryAttributes)
                            ->filter(fn ($a) =>
                                in_array('required', $a['tags'] ?? [])
                                && ! in_array('read_only', $a['tags'] ?? [])
                                && (
                                    empty($currentAttrs->get($a['id'] ?? '')['value_name'])
                                    || (string)($currentAttrs->get($a['id'] ?? '')['value_id'] ?? '') === '-1'
                                )
                            )
                            ->take(15); // limit to avoid huge prompts

                        if ($missingAttrs->isEmpty()) {
                            $skipped[] = ['label' => 'Atributos técnicos', 'reason' => 'Nenhum atributo obrigatório faltando.'];
                            continue;
                        }

                        // Build prompt asking AI to fill in missing attributes
                        $attrList = $missingAttrs->map(function ($a) {
                            $allowed = collect($a['allowed_values'] ?? [])->pluck('name')->implode(', ');
                            $unit    = collect($a['allowed_units'] ?? [])->pluck('name')->implode('/');
                            $hint    = $allowed ? "Opções: [{$allowed}]" : ($unit ? "Unidade: {$unit}" : '');
                            return "- {$a['name']}" . ($hint ? " ({$hint})" : '');
                        })->implode("\n");

                        $system = <<<'SYS'
Você é especialista em especificações técnicas de produtos para e-commerce brasileiro.
Com base no título do produto, preencha os atributos técnicos solicitados.
Responda SOMENTE em JSON puro, sem markdown, no formato:
{"ATTR_ID": "valor", "ATTR_ID2": "valor2"}
Use exatamente os valores das opções fornecidas quando disponíveis.
Se não souber o valor com certeza, não inclua o atributo na resposta.
SYS;
                        $attrIds = $missingAttrs->mapWithKeys(fn ($a) => [$a['id'] => $a['name']]);
                        $user = "Produto: {$title}\n\nAtributos para preencher:\n{$attrList}\n\nResponda em JSON usando os IDs: " . $attrIds->keys()->implode(', ');

                        $raw = $ai->generateText($system, $user, 800);
                        // Extract JSON from response (AI sometimes wraps it in text)
                        if (preg_match('/\{[^{}]+\}/s', $raw, $m)) {
                            $suggested = json_decode($m[0], true) ?? [];
                        } else {
                            $suggested = json_decode($raw, true) ?? [];
                        }

                        if (empty($suggested)) {
                            $skipped[] = ['label' => 'Atributos técnicos', 'reason' => 'IA não conseguiu sugerir valores para os atributos.'];
                            continue;
                        }

                        // Build the full attributes payload (existing + AI suggestions)
                        $existingPayload = collect($liveData['attributes'] ?? [])
                            ->filter(fn ($a) => ! empty($a['value_name']) && (string)($a['value_id'] ?? '') !== '-1')
                            ->map(fn ($a) => ['id' => $a['id'], 'value_name' => $a['value_name']])
                            ->values()
                            ->toArray();

                        $aiPayload = collect($suggested)
                            ->map(fn ($val, $id) => ['id' => $id, 'value_name' => (string)$val])
                            ->values()
                            ->toArray();

                        // Merge: existing attrs + AI suggestions (AI takes precedence for missing ones)
                        $existingIds = collect($existingPayload)->pluck('id')->toArray();
                        $newOnes     = array_filter($aiPayload, fn ($a) => ! in_array($a['id'], $existingIds));
                        $fullPayload = array_merge($existingPayload, array_values($newOnes));

                        $service->updateItem($listing->external_id, ['attributes' => $fullPayload]);

                        $filledCount = count($suggested);
                        $improvements[] = [
                            'icon'   => '📋',
                            'label'  => 'Atributos técnicos',
                            'detail' => "{$filledCount} atributo(s) preenchido(s) automaticamente: " .
                                collect($suggested)->map(fn ($v, $id) => ($attrIds[$id] ?? $id) . ': ' . $v)->implode(', '),
                        ];
                    } catch (\Throwable $e) {
                        $errors[] = 'Atributos: ' . $e->getMessage();
                    }
                }

                // ── Free Shipping, Financing, GTIN, Stock, Pictures — not auto-fixable ──
                elseif (str_contains($varKey, 'FREE_SHIPPING') || str_contains($varKey, 'SHIPPING')) {
                    $skipped[] = ['label' => 'Frete grátis', 'reason' => 'Decisão comercial — configure na seção Frete.'];
                } elseif (str_contains($varKey, 'FINANCING')) {
                    $skipped[] = ['label' => 'Parcelamento', 'reason' => 'Requer configuração no Mercado Pago.'];
                } elseif (str_contains($varKey, 'GTIN')) {
                    $skipped[] = ['label' => 'Código universal (GTIN/EAN)', 'reason' => 'Informe manualmente no painel ML.'];
                } elseif (str_contains($varKey, 'PICTURE')) {
                    $skipped[] = ['label' => 'Fotos do produto', 'reason' => 'Use "Gerar com IA" na seção de imagens ou faça upload manualmente.'];
                } elseif (str_contains($varKey, 'STOCK')) {
                    $skipped[] = ['label' => 'Estoque', 'reason' => 'Configure o estoque no formulário do anúncio.'];
                } elseif (str_contains($varKey, 'VERIFICATION')) {
                    $skipped[] = ['label' => 'Verificação de dados', 'reason' => 'Complete no painel do Mercado Livre.'];
                } elseif (str_contains($varKey, 'VIDEO')) {
                    $skipped[] = ['label' => 'Vídeo do produto', 'reason' => 'Adicione um vídeo manualmente no ML.'];
                }
            }

            $totalDone = count($improvements);
            $message   = $totalDone > 0
                ? "{$totalDone} melhoria(s) aplicada(s) com sucesso! Recarregue a página para ver as mudanças."
                : 'Nenhuma melhoria automática pôde ser aplicada. Verifique os itens ignorados.';

            return response()->json([
                'improvements' => $improvements,
                'skipped'      => $skipped,
                'errors'       => $errors,
                'message'      => $message,
            ]);

        } catch (\Throwable $e) {
            Log::error("improveWithAi [{$listing->external_id}]: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Generate a product description using the configured AI provider (OpenRouter/OpenAI/Anthropic).
     * Returns JSON: { description: string } or { error: string }
     */
    public function generateDescriptionAi(Request $request, MarketplaceListing $listing): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'existing_description' => 'nullable|string|max:5000',
        ]);

        try {
            $ai = new AiService();

            if (! $ai->isConfigured()) {
                return response()->json([
                    'error' => 'IA não configurada. Acesse Configurações → Inteligência Artificial e adicione sua chave API.',
                ], 422);
            }

            $meta       = $listing->meta ?? [];
            $category   = $meta['category_id'] ?? '';
            $attributes = [];

            // Build a clean attribute list from meta if available
            if (! empty($meta['attributes'])) {
                foreach ($meta['attributes'] as $attr) {
                    if (! empty($attr['id']) && ! empty($attr['value_name'])) {
                        $attributes[$attr['id']] = $attr['value_name'];
                    }
                }
            }

            $prompts = AiService::buildDescriptionPrompt(
                title: $listing->title,
                category: $category,
                existingDescription: $validated['existing_description'] ?? '',
                attributes: $attributes,
            );

            $text = $ai->generateText($prompts['system'], $prompts['user']);

            return response()->json(['description' => $text]);

        } catch (\Throwable $e) {
            Log::warning("generateDescriptionAi [{$listing->external_id}]: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Generate a product image using AI (DALL-E or compatible model).
     * Downloads the generated image and uploads it directly to ML.
     * Returns JSON: { url: string, uploaded: bool } or { error: string }
     */
    public function generateImageAi(Request $request, MarketplaceListing $listing): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'prompt'       => 'nullable|string|max:1000',
            'upload_to_ml' => 'nullable|boolean',
        ]);

        try {
            $ai = new AiService();

            if (! $ai->isConfigured()) {
                return response()->json([
                    'error' => 'IA não configurada. Acesse Configurações → Inteligência Artificial e adicione sua chave API.',
                ], 422);
            }

            $prompt = $validated['prompt']
                ?? "Foto profissional de produto para e-commerce do Brasil: {$listing->title}. Fundo branco puro, iluminação de estúdio, alta resolução, vista frontal, sem texto, estilo comercial moderno.";

            $imageUrl = $ai->generateImage($prompt);

            // If upload_to_ml is true, download and upload to ML automatically
            if (! empty($validated['upload_to_ml'])) {
                $account = $listing->marketplaceAccount;
                if ($account && $account->credentials) {
                    try {
                        $imageContent = \Illuminate\Support\Facades\Http::timeout(60)->get($imageUrl)->body();
                        $tmpFile = tempnam(sys_get_temp_dir(), 'ai_img_') . '.jpg';
                        file_put_contents($tmpFile, $imageContent);

                        $uploadedFile = new \Illuminate\Http\UploadedFile(
                            $tmpFile, 'ai_generated.jpg', 'image/jpeg', null, true
                        );

                        $mlService   = new MercadoLivreService($account);
                        $pictureId   = $mlService->uploadPicture($uploadedFile);
                        $item        = $mlService->getItem($listing->external_id);
                        $existing    = collect($item['pictures'] ?? [])->map(fn ($p) => ['id' => $p['id']])->all();
                        $mlService->updateItem($listing->external_id, ['pictures' => array_merge($existing, [['id' => $pictureId]])]);

                        @unlink($tmpFile);

                        return response()->json(['url' => $imageUrl, 'uploaded' => true, 'picture_id' => $pictureId]);
                    } catch (\Throwable $uploadErr) {
                        Log::warning("generateImageAi upload ML error [{$listing->external_id}]: " . $uploadErr->getMessage());
                        // Return the URL anyway so user can save it manually
                        return response()->json(['url' => $imageUrl, 'uploaded' => false, 'upload_error' => $uploadErr->getMessage()]);
                    }
                }
            }

            return response()->json(['url' => $imageUrl, 'uploaded' => false]);

        } catch (\Throwable $e) {
            Log::warning("generateImageAi [{$listing->external_id}]: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
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

    /**
     * Dispatches background jobs to sync quality scores for all ML listings
     * that have credentials. Optionally filtered by account.
     */
    public function syncQuality(Request $request)
    {
        $accountId = $request->input('account');

        $query = MarketplaceListing::with('marketplaceAccount')
            ->whereHas('marketplaceAccount', fn ($q) => $q->whereNotNull('credentials'));

        if ($accountId) {
            $query->where('marketplace_account_id', $accountId);
        }

        $listings = $query->select('id', 'marketplace_account_id', 'external_id')->get();

        $dispatched = 0;
        foreach ($listings as $listing) {
            SyncListingQualityScore::dispatch($listing->id)->onQueue('default');
            $dispatched++;
        }

        $msg = $dispatched > 0
            ? "Sincronização de qualidade iniciada para {$dispatched} anúncio(s). Aguarde alguns minutos e recarregue a página."
            : 'Nenhum anúncio encontrado para sincronizar.';

        return redirect()->back()->with('info', $msg);
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
