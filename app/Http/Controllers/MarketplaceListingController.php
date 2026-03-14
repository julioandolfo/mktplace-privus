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
            $query = MarketplaceListing::with(['marketplaceAccount.company', 'product']);

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

            // Fetch visits for current page listings (batch call per account)
            $visitsMap = [];
            try {
                $listingsByAccount = $listings->getCollection()->groupBy('marketplace_account_id');
                foreach ($listingsByAccount as $accountId => $accountListings) {
                    $account = $accounts->firstWhere('id', $accountId);
                    if ($account && $account->credentials) {
                        $service = new MercadoLivreService($account);
                        $externalIds = $accountListings->pluck('external_id')->filter()->values()->all();
                        if (! empty($externalIds)) {
                            $visitsMap = array_merge($visitsMap, $service->getItemVisitsBatch($externalIds));
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::info('listings.index: visits fetch failed: ' . $e->getMessage());
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
                'totalListings', 'activeCount', 'pausedCount', 'unlinkedCount', 'perAccount',
                'visitsMap'
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

            $liveData          = null;
            $quality           = null;
            $purchaseExperience = null;
            $description       = null;
            $categoryAttributes    = [];
            $availableListingTypes = [];
            $fiscalData            = [];
            $canInvoice            = [];
            $taxRules              = [];
            $visits                = [];
            $familyMembers         = [];
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

                    $step               = 'api-purchase-experience';
                    $purchaseExperience = $service->getPurchaseExperience($listing->external_id);

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

                    // Fetch family members for family items (grouped items that look like variations in ML)
                    $step = 'api-family';
                    if (! empty($liveData['family_name']) || ! empty($liveData['family_id'])) {
                        try {
                            $familyMembers = $service->getFamilyMembers($listing->external_id, $liveData);
                            if (! empty($familyMembers)) {
                                $metaPatch['has_family'] = true;
                                $metaPatch['family_count'] = count($familyMembers) + 1; // +1 for self
                            }
                        } catch (\Throwable $e) {
                            Log::info("ListingController family members [{$listing->external_id}]: " . $e->getMessage());
                        }
                    }

                    $step = 'api-fiscal';
                    try {
                        $fiscalData    = $service->getItemFiscalData($listing->external_id);
                        $canInvoice    = $service->canInvoiceItem($listing->external_id);
                    } catch (\Throwable $e) {
                        Log::info("ListingController fiscal data [{$listing->external_id}]: " . $e->getMessage());
                    }

                    $step = 'api-tax-rules';
                    try {
                        $rulesResponse = $service->getTaxRules();
                        $taxRules = collect($rulesResponse['results'] ?? $rulesResponse)
                            ->map(fn ($r) => [
                                'id'          => $r['id'] ?? '',
                                'description' => $r['description'] ?? $r['name'] ?? ('Regra #' . ($r['id'] ?? '?')),
                            ])
                            ->values()
                            ->toArray();
                    } catch (\Throwable $e) {
                        Log::info("ListingController getTaxRules [{$listing->external_id}]: " . $e->getMessage());
                    }

                    $step = 'api-visits';
                    try {
                        $visits = $service->getItemVisits($listing->external_id, 30);
                    } catch (\Throwable $e) {
                        Log::info("ListingController getItemVisits [{$listing->external_id}]: " . $e->getMessage());
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
            $aiConfigured = (new AiService())->isConfigured();

            $viewData = compact(
                'listing', 'products',
                'liveData', 'quality', 'purchaseExperience', 'description', 'categoryAttributes', 'availableListingTypes', 'apiError',
                'fiscalData', 'canInvoice', 'taxRules', 'visits', 'familyMembers',
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
            $liveData = $service->getItemWithVariations($listing->external_id);

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
            $liveData = $service->getItemWithVariations($listing->external_id);

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
            $liveData = $service->getItemWithVariations($listing->external_id);

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
     * Cria ou atualiza os dados fiscais de um anúncio no ML.
     * Se o SKU já tem dados fiscais, atualiza (PUT). Senão, cria (POST) e vincula ao item.
     *
     * @see https://developers.mercadolivre.com.br/pt_br/envio-dos-dados-fiscais
     */
    public function updateFiscalData(Request $request, MarketplaceListing $listing)
    {
        $validated = $request->validate([
            'fiscal_sku'     => 'required|string|max:100',
            'fiscal_title'   => 'required|string|max:200',
            'fiscal_cost'    => 'required|numeric|min:0',
            'ncm'            => 'required|string|size:8',
            'origin_type'    => 'required|in:manufacturer,reseller,imported',
            'origin_detail'  => 'required|string|max:10',
            'cest'           => 'nullable|string|max:10',
            'ean'            => 'nullable|string|max:20',
            'measurement_unit' => 'nullable|string|max:10',
            'tax_rule_id'    => 'nullable|integer',
        ]);

        $account = $listing->marketplaceAccount;
        if (! $account || ! $account->credentials) {
            return back()->with('error', 'Conta nao encontrada ou sem credenciais.');
        }

        $service = new MercadoLivreService($account);

        $taxInfo = [
            'ncm'           => $validated['ncm'],
            'origin_type'   => $validated['origin_type'],
            'origin_detail' => $validated['origin_detail'],
        ];

        if (! empty($validated['cest'])) {
            $taxInfo['cest'] = $validated['cest'];
        }
        if (! empty($validated['ean'])) {
            $taxInfo['ean'] = $validated['ean'];
        }
        if (! empty($validated['tax_rule_id'])) {
            $taxInfo['tax_rule_id'] = (int) $validated['tax_rule_id'];
        }

        $payload = [
            'sku'              => $validated['fiscal_sku'],
            'title'            => $validated['fiscal_title'],
            'type'             => 'single',
            'cost'             => (float) $validated['fiscal_cost'],
            'measurement_unit' => $validated['measurement_unit'] ?? 'UN',
            'tax_information'  => $taxInfo,
        ];

        try {
            // Tenta consultar se já existe dados fiscais para este SKU
            $existing = [];
            try {
                $existing = $service->getItemFiscalData($listing->external_id);
            } catch (\Throwable $e) {
                // Sem dados fiscais ainda
            }

            if (! empty($existing) && ! empty($existing['sku'])) {
                // Atualiza (PUT)
                $service->updateFiscalData($existing['sku'], $payload);
                $msg = 'Dados fiscais atualizados com sucesso.';
            } else {
                // Cria e vincula
                $service->createFiscalData($payload);
                $service->linkFiscalDataToItem($validated['fiscal_sku'], $listing->external_id);
                $msg = 'Dados fiscais criados e vinculados ao anuncio com sucesso.';
            }

            return redirect()->route('listings.show', $listing)->with('success', $msg);
        } catch (\Throwable $e) {
            Log::error("updateFiscalData [{$listing->external_id}]: " . $e->getMessage());
            return back()->with('error', 'Erro ao salvar dados fiscais: ' . self::friendlyMlError($e->getMessage()));
        }
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
            $result  = $service->updateDescription($listing->external_id, $text);

            // After updating, immediately fetch back to verify
            $verify = $service->getItemDescription($listing->external_id);
            $savedText = $verify['plain_text'] ?? '';

            if (! empty($savedText) && trim($savedText) === trim($text)) {
                return redirect()->route('listings.show', $listing)
                    ->with('success', 'Descrição atualizada com sucesso no Mercado Livre.');
            }

            // API returned success but description didn't change
            Log::warning("updateDescription [{$listing->external_id}]: API returned success but description did NOT change. Result: " . json_encode($result));
            return redirect()->route('listings.show', $listing)
                ->with('info', 'A API do ML retornou sucesso, mas a descrição pode não ter sido alterada. Verifique no painel do ML. (Detalhes nos logs)');

        } catch (\Throwable $e) {
            Log::warning("updateDescription error [{$listing->external_id}]: " . $e->getMessage());
            return back()->with('error', 'Erro ao salvar descrição: ' . self::friendlyMlError($e->getMessage()));
        }
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
            'pictures.*'             => 'nullable|url',
            'picture_files'          => 'nullable|array',
            'picture_files.*'        => 'image|mimes:jpeg,png,jpg|max:10240',
            'family_name'                         => 'nullable|string|max:255',
            'variations'                          => 'nullable|array',
            'variations.*.attributes'             => 'nullable|array',
            'variations.*.price'                  => 'nullable|numeric|min:0',
            'variations.*.available_quantity'     => 'nullable|integer|min:1',
            'variations.*.seller_custom_field'    => 'nullable|string|max:100',
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

            if (! empty($validated['family_name'])) {
                $payload['family_name'] = $validated['family_name'];
            }

            if (! empty($validated['attributes'])) {
                $payload['attributes'] = collect($validated['attributes'])
                    ->map(fn ($value, $id) => ['id' => $id, 'value_name' => $value])
                    ->values()
                    ->all();
            }

            // Upload files first, then combine with URL pictures
            $allPictures = [];

            if (! empty($validated['picture_files'])) {
                foreach ($validated['picture_files'] as $file) {
                    $pictureId = $service->uploadPicture($file);
                    $allPictures[] = ['id' => $pictureId];
                }
            }

            if (! empty($validated['pictures'])) {
                foreach ($validated['pictures'] as $url) {
                    if (! empty($url)) {
                        $allPictures[] = ['source' => $url];
                    }
                }
            }

            if (! empty($allPictures)) {
                $payload['pictures'] = $allPictures;
            }

            // Variations
            if (! empty($validated['variations'])) {
                $mlVariations = [];
                foreach ($validated['variations'] as $v) {
                    if (empty($v['attributes'])) continue;

                    $combos = [];
                    foreach ($v['attributes'] as $attrId => $valueName) {
                        if (! empty($valueName)) {
                            $combos[] = ['id' => $attrId, 'value_name' => $valueName];
                        }
                    }
                    if (empty($combos)) continue;

                    $variation = [
                        'attribute_combinations' => $combos,
                        'available_quantity'     => (int) ($v['available_quantity'] ?? 1),
                    ];
                    if (! empty($v['price'])) {
                        $variation['price'] = (float) $v['price'];
                    }
                    if (! empty($v['seller_custom_field'])) {
                        $variation['seller_custom_field'] = $v['seller_custom_field'];
                    }
                    if (! empty($allPictures)) {
                        $variation['picture_ids'] = collect($allPictures)
                            ->pluck('id')
                            ->filter()
                            ->values()
                            ->all();
                    }

                    $mlVariations[] = $variation;
                }

                if (! empty($mlVariations)) {
                    $payload['variations'] = $mlVariations;
                    // When using variations, remove top-level available_quantity
                    unset($payload['available_quantity']);
                }
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
                'meta'                   => array_filter([
                    'ml_item_id'      => $item['id'],
                    'ml_status'       => 'active',
                    'ml_permalink'    => $item['permalink'] ?? null,
                    'category_id'     => $validated['category_id'],
                    'listing_type_id' => $validated['listing_type_id'],
                    'family_name'     => $item['family_name'] ?? ($validated['family_name'] ?? null),
                ]),
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
        $validated = $request->validate([
            'q'          => 'required|string|min:2',
            'account_id' => 'required|integer|exists:marketplace_accounts,id',
        ]);

        $account = MarketplaceAccount::findOrFail($validated['account_id']);

        if (! $account->credentials) {
            return response()->json(['error' => 'Conta sem credenciais'], 422);
        }

        try {
            $service    = new MercadoLivreService($account);
            $categories = $service->searchCategories($validated['q']);
        } catch (\Throwable $e) {
            Log::warning("searchCategories failed: " . $e->getMessage());
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

    /**
     * AI: fill category attributes based on product title.
     */
    public function aiFillAttributes(Request $request)
    {
        $data = $request->validate([
            'title'         => 'required|string|max:200',
            'category_name' => 'required|string|max:200',
            'attributes'    => 'required|array',
        ]);

        $ai = new AiService();
        if (! $ai->isConfigured()) {
            return response()->json(['error' => 'IA não configurada'], 422);
        }

        $attrDescriptions = collect($data['attributes'])->map(function ($attr) {
            $desc = $attr['id'] . ' (' . $attr['name'] . ')';
            if (! empty($attr['allowed_values'])) {
                $desc .= ' - valores permitidos: ' . implode(', ', array_slice($attr['allowed_values'], 0, 30));
            } else {
                $desc .= ' - tipo: ' . ($attr['value_type'] ?? 'text');
            }
            return $desc;
        })->implode("\n");

        $system = <<<'PROMPT'
Você é um especialista em e-commerce brasileiro, especializado em preencher fichas técnicas de produtos para o Mercado Livre.

Regras:
- Responda APENAS com um JSON válido, sem markdown, sem explicações
- O JSON deve ter como chaves os IDs dos atributos e como valores os valores sugeridos
- Para atributos com valores permitidos, SEMPRE escolha um dos valores da lista fornecida (exatamente como escrito)
- Para atributos de texto livre, preencha com valores realistas baseados no título do produto
- Para atributos numéricos, use apenas números
- Se não souber o valor de um atributo, use string vazia ""
- Preencha o máximo possível de atributos
PROMPT;

        $user = "Produto: {$data['title']}\nCategoria: {$data['category_name']}\n\nAtributos para preencher:\n{$attrDescriptions}";

        try {
            $result = $ai->generateText($system, $user, 2000);
            // Clean potential markdown wrapping
            $result = preg_replace('/^```(?:json)?\s*/s', '', $result);
            $result = preg_replace('/\s*```$/s', '', $result);
            $parsed = json_decode($result, true);

            if (! is_array($parsed)) {
                return response()->json(['error' => 'Resposta da IA inválida'], 422);
            }

            return response()->json($parsed);
        } catch (\Throwable $e) {
            Log::warning('AI fill attributes error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * AI: generate listing description.
     */
    public function aiGenerateDescription(Request $request)
    {
        $data = $request->validate([
            'title'                => 'required|string|max:200',
            'category_name'        => 'nullable|string|max:200',
            'attributes'           => 'nullable|array',
            'existing_description' => 'nullable|string|max:5000',
        ]);

        $ai = new AiService();
        if (! $ai->isConfigured()) {
            return response()->json(['error' => 'IA não configurada'], 422);
        }

        try {
            $prompts = AiService::buildDescriptionPrompt(
                $data['title'],
                $data['category_name'] ?? '',
                $data['existing_description'] ?? '',
                $data['attributes'] ?? [],
            );

            $description = $ai->generateText($prompts['system'], $prompts['user'], 2000);

            return response()->json(['description' => $description]);
        } catch (\Throwable $e) {
            Log::warning('AI generate description error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  KITS
    // ═══════════════════════════════════════════════════════════════════════

    public function kitForm(MarketplaceListing $listing)
    {
        $listing->load('marketplaceAccount');
        $account  = $listing->marketplaceAccount;

        $liveData = null;
        if ($account && $account->credentials) {
            try {
                $service  = new MercadoLivreService($account);
                $liveData = $service->getItemWithVariations($listing->external_id);
            } catch (\Throwable $e) {
                Log::warning("kitForm: error fetching liveData for {$listing->external_id}: " . $e->getMessage());
            }
        }

        // Get other ML listings from same account for combo selector
        $otherListings = MarketplaceListing::where('marketplace_account_id', $listing->marketplace_account_id)
            ->where('id', '!=', $listing->id)
            ->where('status', 'active')
            ->orderBy('title')
            ->get(['id', 'external_id', 'title', 'price', 'meta']);

        return view('marketplace-listings.kit', compact('listing', 'liveData', 'otherListings', 'account'));
    }

    /**
     * Create multipack listings (Kit 2x, Kit 3x, …) from the base listing.
     * Uses standard POST /items — works for all ML listings regardless of User Products model.
     */
    public function storeMultipack(Request $request, MarketplaceListing $listing)
    {
        $validated = $request->validate([
            'qty_min'       => 'required|integer|min:2|max:10',
            'qty_max'       => 'required|integer|min:2|max:10|gte:qty_min',
            'qty_step'      => 'required|integer|min:1|max:5',
            'discount_pct'  => 'nullable|numeric|min:0|max:80',
            'listing_type'  => 'required|string|in:gold_special,gold_pro,free',
            'free_shipping' => 'nullable|boolean',
        ]);

        $account = $listing->marketplaceAccount;
        if (! $account || ! $account->credentials) {
            return back()->with('error', 'Conta sem credenciais configuradas.');
        }

        try {
            $service  = new MercadoLivreService($account);
            $liveData = $service->getItemWithVariations($listing->external_id);
        } catch (\Throwable $e) {
            return back()->with('error', 'Não foi possível buscar dados do anúncio: ' . $e->getMessage());
        }

        $unitPrice   = (float) ($liveData['price'] ?? $listing->price);
        $discountPct = (float) ($validated['discount_pct'] ?? 0) / 100;
        $created     = 0;
        $errors      = [];

        $pictures = array_map(fn($p) => ['id' => $p['id']], array_slice($liveData['pictures'] ?? [], 0, 10));

        for ($qty = $validated['qty_min']; $qty <= $validated['qty_max']; $qty += $validated['qty_step']) {
            $kitPrice = round($unitPrice * $qty * (1 - $discountPct), 2);
            $kitTitle = "Kit {$qty}x " . $listing->title;
            if (mb_strlen($kitTitle) > 60) {
                $kitTitle = "Kit {$qty}x " . mb_substr($listing->title, 0, 55 - mb_strlen("Kit {$qty}x "));
            }

            $payload = [
                'title'              => $kitTitle,
                'category_id'        => $liveData['category_id'] ?? null,
                'price'              => $kitPrice,
                'currency_id'        => 'BRL',
                'available_quantity' => max(1, (int) floor(($liveData['available_quantity'] ?? 1) / $qty)),
                'buying_mode'        => 'buy_it_now',
                'listing_type_id'    => $validated['listing_type'],
                'condition'          => $liveData['condition'] ?? 'new',
                'pictures'           => $pictures,
                'shipping'           => [
                    'mode'          => $liveData['shipping']['mode'] ?? 'me2',
                    'free_shipping' => (bool) ($validated['free_shipping'] ?? ($liveData['shipping']['free_shipping'] ?? false)),
                ],
            ];

            // Attach base item description mentioning kit
            try {
                $desc = $service->getItemDescription($listing->external_id);
                $baseDesc = $desc['plain_text'] ?? '';
                $payload['description'] = ['plain_text' => "Kit com {$qty} unidades.\n\n" . $baseDesc];
            } catch (\Throwable) {}

            try {
                $item = $service->publishItem($payload);

                if (! empty($item['id'])) {
                    MarketplaceListing::create([
                        'marketplace_account_id' => $listing->marketplace_account_id,
                        'external_id'            => $item['id'],
                        'title'                  => $kitTitle,
                        'price'                  => $kitPrice,
                        'available_quantity'      => $payload['available_quantity'],
                        'status'                 => 'active',
                        'meta'                   => [
                            'ml_item_id'      => $item['id'],
                            'ml_permalink'    => $item['permalink'] ?? null,
                            'listing_type_id' => $validated['listing_type'],
                            'kit_base_id'     => $listing->external_id,
                            'kit_quantity'    => $qty,
                            'thumbnail'       => $pictures[0]['url'] ?? ($liveData['thumbnail'] ?? null),
                        ],
                    ]);
                    $created++;
                }
            } catch (\Throwable $e) {
                Log::warning("storeMultipack kit {$qty}x for {$listing->external_id}: " . $e->getMessage());
                $errors[] = "Kit {$qty}x: " . self::friendlyMlError($e->getMessage());
            }
        }

        $msg = "Kit(s) criado(s): {$created}";
        if ($errors) {
            return redirect()->route('listings.show', $listing)->with('info', $msg . '. Erros: ' . implode('; ', $errors));
        }
        return redirect()->route('listings.show', $listing)->with('success', "{$created} kit(s) multipack criado(s) com sucesso no Mercado Livre!");
    }

    /**
     * Create a Virtual Kit (combo of 2+ different listings) via POST /items/kits.
     * Requires user_product_id (MLBU...) — only available for User Products listings.
     */
    public function storeCombo(Request $request, MarketplaceListing $listing)
    {
        $validated = $request->validate([
            'components'                      => 'required|array|min:2|max:6',
            'components.*.user_product_id'    => 'required|string|starts_with:MLB',
            'components.*.quantity'           => 'required|integer|min:1|max:10',
            'family_name'                     => 'required|string|max:255',
            'listing_type'                    => 'required|string|in:gold_special,gold_pro',
            'price_mode'                      => 'required|in:manual,auto',
            'price'                           => 'nullable|numeric|min:1',
            'auto_discount'                   => 'nullable|numeric|min:0|max:80',
        ]);

        $account = $listing->marketplaceAccount;
        if (! $account || ! $account->credentials) {
            return back()->with('error', 'Conta sem credenciais configuradas.');
        }

        $autoPrice    = $validated['price_mode'] === 'auto';
        $autoDiscount = ($validated['auto_discount'] ?? 0) / 100;
        $manualPrice  = $autoPrice ? null : (float) ($validated['price'] ?? 0);

        try {
            $service = new MercadoLivreService($account);
            $item    = $service->createVirtualKit(
                familyName:    $validated['family_name'],
                components:    $validated['components'],
                price:         $manualPrice,
                listingTypeId: $validated['listing_type'],
                autoPrice:     $autoPrice,
                autoDiscount:  $autoDiscount,
            );

            if (! empty($item['id'])) {
                MarketplaceListing::create([
                    'marketplace_account_id' => $listing->marketplace_account_id,
                    'external_id'            => $item['id'],
                    'title'                  => $item['title'] ?? $validated['family_name'],
                    'price'                  => $item['price'] ?? 0,
                    'available_quantity'     => $item['available_quantity'] ?? 0,
                    'status'                 => 'active',
                    'meta'                   => [
                        'ml_item_id'      => $item['id'],
                        'ml_permalink'    => $item['permalink'] ?? null,
                        'listing_type_id' => $validated['listing_type'],
                        'is_kit'          => true,
                        'thumbnail'       => $item['thumbnail'] ?? null,
                    ],
                ]);
            }
        } catch (\Throwable $e) {
            Log::error("storeCombo for {$listing->external_id}: " . $e->getMessage());
            return back()->with('error', 'Erro ao criar Kit Combo: ' . self::friendlyMlError($e->getMessage()));
        }

        return redirect()->route('listings.index')
            ->with('success', 'Kit combo criado com sucesso no Mercado Livre!');
    }

    /** Search kit components (User Products) for combo creation (AJAX). */
    public function searchKitComponents(Request $request, MarketplaceListing $listing)
    {
        $query          = $request->input('q', '');
        $addedIds       = $request->input('added', []);
        $mainProductId  = $request->input('main_product_id');

        $account = $listing->marketplaceAccount;
        if (! $account || ! $account->credentials) {
            return response()->json(['error' => 'Sem credenciais'], 422);
        }

        try {
            $service = new MercadoLivreService($account);
            $result  = $service->searchKitComponents($query, (array) $addedIds, $mainProductId);
            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  PROMOTIONS
    // ═══════════════════════════════════════════════════════════════════════

    public function getPromotions(MarketplaceListing $listing)
    {
        $account = $listing->marketplaceAccount;
        if (! $account || ! $account->credentials) {
            return response()->json([]);
        }
        try {
            $service    = new MercadoLivreService($account);
            $promotions = $service->getItemPromotions($listing->external_id);
            return response()->json($promotions);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function storePromotion(Request $request, MarketplaceListing $listing)
    {
        $validated = $request->validate([
            'deal_price'  => 'required|numeric|min:0.01',
            'start_date'  => 'required|date',
            'finish_date' => 'required|date|after:start_date',
        ]);

        $account = $listing->marketplaceAccount;
        if (! $account || ! $account->credentials) {
            return back()->with('error', 'Conta sem credenciais.');
        }

        $payload = [
            'deal_price'     => (float) $validated['deal_price'],
            'start_date'     => \Carbon\Carbon::parse($validated['start_date'])->format('Y-m-d\TH:i:s'),
            'finish_date'    => \Carbon\Carbon::parse($validated['finish_date'])->format('Y-m-d\TH:i:s'),
            'promotion_type' => 'PRICE_DISCOUNT',
        ];

        try {
            $service = new MercadoLivreService($account);
            $service->createPromotion($listing->external_id, $payload);
        } catch (\Throwable $e) {
            return back()->with('error', 'Erro ao criar promoção: ' . self::friendlyMlError($e->getMessage()));
        }

        return back()->with('success', 'Promoção de desconto criada com sucesso!');
    }

    public function deletePromotion(Request $request, MarketplaceListing $listing)
    {
        $account = $listing->marketplaceAccount;
        if (! $account || ! $account->credentials) {
            return back()->with('error', 'Conta sem credenciais.');
        }
        try {
            $service = new MercadoLivreService($account);
            $service->deletePromotion($listing->external_id, $request->input('promotion_type', 'PRICE_DISCOUNT'));
        } catch (\Throwable $e) {
            return back()->with('error', 'Erro ao remover promoção: ' . self::friendlyMlError($e->getMessage()));
        }
        return back()->with('success', 'Promoção removida com sucesso.');
    }
}
