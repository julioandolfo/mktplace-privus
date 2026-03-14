<?php

namespace App\Services\Marketplaces;

use App\Models\MarketplaceAccount;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MercadoLivreService
{
    private const BASE_URL = 'https://api.mercadolibre.com';

    /**
     * Agent ID for Brazil (MLB) — all seller→buyer messages must target this agent.
     * See: https://developers.mercadolivre.com.br/pt_br/mensagens-post-venda
     */
    private const MLB_AGENT_ID = 3037675074;

    /**
     * Minimum interval (microseconds) between API calls to avoid rate limiting.
     * 200ms = max ~5 requests/second per account.
     */
    private const THROTTLE_US = 200_000;

    private static float $lastRequestAt = 0;

    public function __construct(private MarketplaceAccount $account) {}

    // ─── HTTP Helpers ────────────────────────────────────────────────────────

    private function throttle(): void
    {
        $now  = microtime(true);
        $diff = ($now - self::$lastRequestAt) * 1_000_000; // to microseconds

        if (self::$lastRequestAt > 0 && $diff < self::THROTTLE_US) {
            usleep((int) (self::THROTTLE_US - $diff));
        }

        self::$lastRequestAt = microtime(true);
    }

    /**
     * Execute an HTTP request with throttling and retry on transient errors.
     */
    private function requestWithRetry(string $method, string $path, array $params = [], array $body = []): array
    {
        $maxAttempts = 3;
        $attempt     = 0;

        while (true) {
            $attempt++;
            $this->throttle();

            try {
                $request = Http::withToken($this->token())
                    ->withHeaders(['x-format-new' => 'true'])
                    ->timeout(15);

                $url = self::BASE_URL . $path;

                $response = match (strtoupper($method)) {
                    'GET'   => $request->get($url, $params),
                    'POST'  => $request->post($url, $body),
                    'PUT'   => $request->put($url, $body),
                    'PATCH' => $request->patch($url, $body),
                    default => throw new \RuntimeException("Unsupported method: {$method}"),
                };

                // Rate limited by ML — retry with backoff
                if ($response->status() === 429 && $attempt < $maxAttempts) {
                    $wait = min($attempt * 2, 10);
                    Log::warning("ML API rate limited on {$method} {$path}, retrying in {$wait}s (attempt {$attempt}/{$maxAttempts})");
                    sleep($wait);
                    continue;
                }

                // Server error — retry with backoff
                if ($response->serverError() && $attempt < $maxAttempts) {
                    $wait = $attempt * 2;
                    Log::warning("ML API server error [{$response->status()}] on {$method} {$path}, retrying in {$wait}s (attempt {$attempt}/{$maxAttempts})");
                    sleep($wait);
                    continue;
                }

                if ($response->failed()) {
                    throw new \RuntimeException(
                        "ML API error [{$response->status()}] {$method} {$path}: " . $response->body()
                    );
                }

                return $response->json() ?? [];
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                if ($attempt >= $maxAttempts) {
                    throw $e;
                }
                $wait = $attempt * 2;
                Log::warning("ML API connection error on {$method} {$path}, retrying in {$wait}s: " . $e->getMessage());
                sleep($wait);
            }
        }
    }

    private function token(): string
    {
        $creds = $this->account->credentials ?? [];
        $token = $creds['access_token'] ?? null;

        if (! $token) {
            throw new \RuntimeException("Conta {$this->account->id} não possui access_token.");
        }

        return $token;
    }

    private function get(string $path, array $params = []): array
    {
        return $this->requestWithRetry('GET', $path, $params);
    }

    private function put(string $path, array $data): array
    {
        return $this->requestWithRetry('PUT', $path, body: $data);
    }

    private function post(string $path, array $data, array $query = []): array
    {
        $fullPath = ! empty($query) ? $path . '?' . http_build_query($query) : $path;
        return $this->requestWithRetry('POST', $fullPath, body: $data);
    }

    private function patch(string $path, array $data): array
    {
        return $this->requestWithRetry('PATCH', $path, body: $data);
    }

    // ─── Diagnostics ────────────────────────────────────────────────────────

    /**
     * Validate the token and return the authenticated user info from ML.
     * Used to diagnose shop_id mismatches and token issues.
     */
    public function getAuthenticatedUser(): array
    {
        return $this->get('/users/me');
    }

    // ─── Orders ─────────────────────────────────────────────────────────────

    /**
     * Yield pages of orders from ML API.
     */
    public function getOrders(?Carbon $since = null): \Generator
    {
        $shopId = $this->requireShopId();

        $offset = 0;
        $limit  = 50;
        $params = [
            'seller' => $shopId,
            'sort'   => 'date_asc',
            'limit'  => $limit,
        ];

        if ($since) {
            $params['order.date_created.from'] = $since->toIso8601String();
        }

        Log::info("ML getOrders: seller={$shopId}, desde=" . ($since?->toIso8601String() ?? 'início'));

        $firstPage = true;

        do {
            $params['offset'] = $offset;
            $data = $this->get('/orders/search', $params);

            $results = $data['results'] ?? [];
            $total   = $data['paging']['total'] ?? 0;

            if ($firstPage) {
                Log::info("ML getOrders: API retornou total={$total} pedidos para seller={$shopId}");
                $firstPage = false;
            }

            if (empty($results)) {
                break;
            }

            yield $results;

            $offset += count($results);
        } while ($offset < $total);
    }

    /**
     * Fetch a single order by ID.
     */
    public function getOrder(string $orderId): array
    {
        return $this->get("/orders/{$orderId}");
    }

    /**
     * Fetch shipping/tracking details for a shipment.
     */
    public function getShipping(string $shippingId): array
    {
        try {
            return $this->get("/shipments/{$shippingId}");
        } catch (\Throwable $e) {
            Log::warning("ML getShipping({$shippingId}) failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Fetch lead time details for a shipment and extract the seller dispatch deadline.
     *
     * The API may return the deadline in different fields depending on the version:
     *  - estimated_handling_limit.date (documented, older format)
     *  - estimated_schedule_limit.date (current BR format)
     *  - Fallback: calculated from paid_at + handling hours
     */
    public function getShippingLeadTime(string $shippingId): array
    {
        try {
            return $this->get("/shipments/{$shippingId}/lead_time");
        } catch (\Throwable $e) {
            Log::warning("ML getShippingLeadTime({$shippingId}) failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Extract the dispatch deadline from lead_time data.
     * Tries multiple fields and falls back to calculating from paid_at + handling hours.
     *
     * Priority:
     *  1. estimated_handling_limit.date (documented, older API)
     *  2. estimated_schedule_limit.date (current BR API, populated for pending shipments)
     *  3. buffering.date (sometimes present)
     *  4. Calculated: paid_at + handling hours (min 24h for "same day" dispatch)
     */
    public static function extractDispatchDeadline(array $leadTime, ?\Carbon\Carbon $paidAt = null): ?string
    {
        $deadline = $leadTime['estimated_handling_limit']['date'] ?? null;

        if (! $deadline) {
            $deadline = $leadTime['estimated_schedule_limit']['date'] ?? null;
        }

        if (! $deadline) {
            $deadline = $leadTime['buffering']['date'] ?? null;
        }

        if (! $deadline && $paidAt) {
            $handlingHours = $leadTime['estimated_delivery_time']['handling'] ?? null;
            if ($handlingHours !== null && is_numeric($handlingHours)) {
                // handling=0 means "same day dispatch" — ML gives until end of next business day
                $hours = max((int) $handlingHours, 24);
                $deadline = $paidAt->copy()->addHours($hours)->toIso8601String();
            }
        }

        return $deadline;
    }

    // ─── Listings ────────────────────────────────────────────────────────────

    /**
     * Yield batches of listing details (up to 20 per batch).
     */
    public function getListings(): \Generator
    {
        $shopId = $this->requireShopId();

        $offset = 0;
        $limit  = 100;
        $allIds = [];

        do {
            $data = $this->get("/users/{$shopId}/items/search", [
                'limit'  => $limit,
                'offset' => $offset,
            ]);

            $ids   = $data['results'] ?? [];
            $total = $data['paging']['total'] ?? 0;

            $allIds = array_merge($allIds, $ids);
            $offset += count($ids);
        } while ($offset < $total && ! empty($ids));

        foreach (array_chunk($allIds, 20) as $batch) {
            $data  = $this->get('/items', ['ids' => implode(',', $batch)]);
            $items = array_filter(
                array_map(fn ($r) => $r['body'] ?? null, $data),
                fn ($item) => $item !== null && ($item['id'] ?? null) !== null
            );
            yield array_values($items);
        }
    }

    /**
     * Get full item details from ML API.
     */
    public function getItem(string $itemId): array
    {
        return $this->get("/items/{$itemId}");
    }

    /**
     * Get item with variations included.
     * ML API with x-format-new header may omit variations from the main response,
     * so we always fetch them separately to ensure completeness.
     */
    public function getItemWithVariations(string $itemId): array
    {
        try {
            $item = $this->get("/items/{$itemId}");

            // x-format-new header may strip variations and item_relations
            // Fetch raw item without the header to get full data
            if (empty($item['variations']) || empty($item['item_relations'])) {
                try {
                    $rawItem = $this->getWithoutFormatHeaderRaw("/items/{$itemId}")->json() ?? [];

                    if (empty($item['item_relations']) && ! empty($rawItem['item_relations'])) {
                        $item['item_relations'] = $rawItem['item_relations'];
                    }
                    if (empty($item['variations']) && ! empty($rawItem['variations'])) {
                        $item['variations'] = $rawItem['variations'];
                    }
                } catch (\Throwable $e) {
                    // Fallback: try dedicated variations endpoint
                    if (empty($item['variations'])) {
                        try {
                            $varResponse = $this->getWithoutFormatHeaderRaw("/items/{$itemId}/variations");
                            $variations  = $varResponse->json();
                            if (! empty($variations) && is_array($variations)) {
                                $item['variations'] = $variations;
                            }
                        } catch (\Throwable $e2) {
                            // No variations available
                        }
                    }
                }
            }

            return $item;
        } catch (\Throwable $e) {
            Log::warning("ML getItemWithVariations({$itemId}) TOTAL FAIL: " . $e->getMessage());
            return $this->getItem($itemId);
        }
    }

    /**
     * GET request without x-format-new header — returns raw Response for debugging.
     */
    private function getWithoutFormatHeaderRaw(string $path, array $params = []): \Illuminate\Http\Client\Response
    {
        $this->throttle();

        $response = Http::withToken($this->token())
            ->timeout(15)
            ->get(self::BASE_URL . $path, $params);

        if ($response->failed()) {
            throw new \RuntimeException(
                "ML API error [{$response->status()}] GET {$path}: " . $response->body()
            );
        }

        return $response;
    }

    /**
     * Get family members for an item that belongs to a family (grouped items).
     * Uses item_relations to find siblings, then filters by exact family_name match.
     * Returns array of item summaries.
     */
    public function getFamilyMembers(string $itemId, array $itemData = []): array
    {
        $familyName = $itemData['family_name'] ?? null;
        $itemRelations = $itemData['item_relations'] ?? [];
        $sellerId = $itemData['seller_id'] ?? null;

        if (! $familyName) {
            return [];
        }

        // 1. Try item_relations first
        $relatedIds = collect($itemRelations)
            ->pluck('item_id')
            ->filter()
            ->unique()
            ->reject(fn ($id) => $id === $itemId)
            ->values()
            ->all();

        // 2. If no item_relations, search seller items by family_name text
        if (empty($relatedIds) && $sellerId) {
            try {
                $searchResult = $this->get('/users/' . $sellerId . '/items/search', [
                    'q'     => $familyName,
                    'limit' => 50,
                ]);
                $relatedIds = collect($searchResult['results'] ?? [])
                    ->reject(fn ($id) => $id === $itemId)
                    ->unique()
                    ->values()
                    ->all();
            } catch (\Throwable $e) {
                Log::info("ML getFamilyMembers search failed for {$itemId}: " . $e->getMessage());
            }
        }

        if (empty($relatedIds)) {
            return [];
        }

        // Fetch details for related items (max 20 via multiget)
        $relatedIds = array_slice($relatedIds, 0, 20);
        try {
            $response = $this->get('/items', ['ids' => implode(',', $relatedIds)]);

            return collect($response)
                ->filter(fn ($r) => ($r['code'] ?? 200) === 200)
                ->map(fn ($r) => $r['body'] ?? $r)
                ->filter(fn ($item) => ($item['family_name'] ?? null) === $familyName)
                ->values()
                ->all();
        } catch (\Throwable $e) {
            Log::info("ML getFamilyMembers multiget failed for {$itemId}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get item description (plain_text) from ML API.
     */
    public function getItemDescription(string $itemId): array
    {
        try {
            return $this->get("/items/{$itemId}/description");
        } catch (\Throwable $e) {
            Log::warning("ML getItemDescription({$itemId}) failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get buyer purchase experience for an item.
     *
     * Endpoint: GET /reputation/items/{itemId}/purchase_experience/integrators?locale=pt_BR
     * Returns reputation color/value, problems, distribution.
     * Returns [] if item has no sales (score = -1) or on error.
     *
     * @see https://developers.mercadolivre.com.br/pt_br/experiencia-de-compra
     */
    public function getPurchaseExperience(string $itemId): array
    {
        try {
            $response = Http::withToken($this->token())
                ->timeout(15)
                ->get(self::BASE_URL . "/reputation/items/{$itemId}/purchase_experience/integrators", [
                    'locale' => 'pt_BR',
                ]);

            if ($response->status() === 302) {
                // Item migrated to User Product structure — follow redirect or skip
                Log::info("ML getPurchaseExperience({$itemId}): 302 redirect (User Product structure).");
                return ['_redirect' => true];
            }

            if ($response->status() === 404) {
                return [];
            }

            if ($response->successful()) {
                $data = $response->json() ?? [];
                // reputation.value = -1 means no sales yet
                if (($data['reputation']['value'] ?? null) === -1) {
                    return [];
                }
                return $data;
            }

            Log::warning("ML getPurchaseExperience({$itemId}): HTTP {$response->status()} — " . substr($response->body(), 0, 300));
            return [];
        } catch (\Throwable $e) {
            Log::warning("ML getPurchaseExperience({$itemId}) exception: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get item quality/performance score from ML API.
     *
     * Endpoint: GET /item/{id}/performance  (singular "item")
     * Returns array with 'score', 'level_wording', 'buckets', etc.
     * Returns ['_unavailable' => true] when ML hasn't computed the score yet (404).
     * Returns [] on error.
     *
     * @see https://developers.mercadolivre.com.br/pt_br/qualidade-das-publicacoes
     */
    public function getItemQuality(string $itemId): array
    {
        try {
            $response = Http::withToken($this->token())
                ->timeout(20)
                ->get(self::BASE_URL . "/item/{$itemId}/performance");

            if ($response->successful()) {
                $data = $response->json() ?? [];
                if (isset($data['score']) || isset($data['buckets'])) {
                    return $data;
                }
                // Successful but unexpected structure
                Log::warning("ML getItemQuality({$itemId}): unexpected structure: " . substr($response->body(), 0, 500));
                return ['_unavailable' => true];
            }

            // 404 = ML hasn't generated the score yet for this listing
            if ($response->status() === 404) {
                Log::info("ML getItemQuality({$itemId}): 404 — score not yet generated by ML.");
                return ['_unavailable' => true];
            }

            Log::warning("ML getItemQuality({$itemId}): HTTP {$response->status()} — " . substr($response->body(), 0, 500));
            return [];
        } catch (\Throwable $e) {
            Log::warning("ML getItemQuality({$itemId}) exception: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get item visits for the last N days.
     * Returns ['total_visits' => int, 'results' => [...]] or [] on error.
     */
    public function getItemVisits(string $itemId, int $lastDays = 30): array
    {
        try {
            $response = Http::withToken($this->token())
                ->timeout(15)
                ->get(self::BASE_URL . "/items/{$itemId}/visits/time_window", [
                    'last' => $lastDays,
                    'unit' => 'day',
                ]);

            if ($response->successful()) {
                return $response->json() ?? [];
            }

            Log::info("ML getItemVisits({$itemId}): HTTP {$response->status()}");
            return [];
        } catch (\Throwable $e) {
            Log::info("ML getItemVisits({$itemId}) exception: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get visits for multiple items in batch (max 50 per call).
     * Uses /visits/items endpoint which returns total visits per item.
     * Returns array keyed by item_id => total_visits.
     */
    public function getItemVisitsBatch(array $itemIds, int $lastDays = 30): array
    {
        $result = [];
        $dateFrom = now()->subDays($lastDays)->format('Y-m-d');
        $dateTo   = now()->format('Y-m-d');

        foreach (array_chunk($itemIds, 50) as $chunk) {
            try {
                // Primary: /visits/items endpoint (total visits, no breakdown)
                $response = Http::withToken($this->token())
                    ->timeout(20)
                    ->get(self::BASE_URL . '/visits/items', [
                        'ids'       => implode(',', $chunk),
                        'date_from' => $dateFrom,
                        'date_to'   => $dateTo,
                    ]);

                if ($response->successful()) {
                    $data = $response->json() ?? [];
                    // Response can be keyed by item_id or array of objects
                    if (isset($data[0]['item_id'])) {
                        // Array format: [{item_id, total_visits}, ...]
                        foreach ($data as $item) {
                            $id = $item['item_id'] ?? null;
                            if ($id) {
                                $result[$id] = (int) ($item['total_visits'] ?? 0);
                            }
                        }
                    } else {
                        // Object format keyed by item_id: {MLB123: 500, MLB456: 200}
                        foreach ($data as $id => $visits) {
                            if (is_numeric($visits)) {
                                $result[$id] = (int) $visits;
                            } elseif (is_array($visits)) {
                                $result[$id] = (int) ($visits['total_visits'] ?? $visits['total'] ?? 0);
                            }
                        }
                    }
                } else {
                    Log::info("ML getItemVisitsBatch: HTTP {$response->status()} for " . count($chunk) . " items");
                }
            } catch (\Throwable $e) {
                Log::info("ML getItemVisitsBatch exception: " . $e->getMessage());
            }
        }

        return $result;
    }

    /**
     * Get category attributes (for building attribute forms).
     */
    public function getCategoryAttributes(string $categoryId): array
    {
        try {
            return $this->get("/categories/{$categoryId}/attributes");
        } catch (\Throwable $e) {
            Log::warning("ML getCategoryAttributes({$categoryId}) failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Search ML categories by keyword (for publishing new items).
     * Uses multiple public ML API strategies for best coverage.
     */
    public function searchCategories(string $query): array
    {
        $results = [];
        $seenIds = [];
        $baseUrl = self::BASE_URL;

        // 1) Domain discovery — primary method, public endpoint (limit max 8)
        try {
            $resp = Http::timeout(10)->get("{$baseUrl}/sites/MLB/domain_discovery/search", ['q' => $query, 'limit' => 8]);
            if ($resp->successful()) {
                $domains = $resp->json();
                if (is_array($domains)) {
                    foreach ($domains as $d) {
                        $id = $d['category_id'] ?? null;
                        if ($id && !isset($seenIds[$id])) {
                            $seenIds[$id] = true;
                            $results[]    = $d;
                        }
                    }
                }
                Log::debug("ML domain_discovery({$query}): found " . count($results) . " categories");
            } else {
                Log::debug("ML domain_discovery({$query}): HTTP {$resp->status()} - {$resp->body()}");
            }
        } catch (\Throwable $e) {
            Log::debug("ML domain_discovery({$query}) error: " . $e->getMessage());
        }

        // 2) Product search fallback (authenticated) — extract categories from real listings
        if (count($results) < 3) {
            try {
                $resp = Http::withToken($this->token())
                    ->timeout(10)
                    ->get("{$baseUrl}/sites/MLB/search", ['q' => $query, 'limit' => 5]);
                if ($resp->successful()) {
                    $search = $resp->json();

                    // Extract category filter (gives category name + count)
                    foreach ($search['available_filters'] ?? [] as $filter) {
                        if ($filter['id'] === 'category') {
                            foreach ($filter['values'] ?? [] as $val) {
                                $catId = $val['id'] ?? null;
                                if ($catId && !isset($seenIds[$catId])) {
                                    $seenIds[$catId] = true;
                                    $results[] = [
                                        'category_id'   => $catId,
                                        'category_name' => $val['name'] ?? $catId,
                                        'domain_name'   => ($val['results'] ?? '') . ' anúncios no ML',
                                    ];
                                }
                            }
                            break;
                        }
                    }

                    // Also extract from individual results if still few
                    if (count($results) < 3) {
                        foreach ($search['results'] ?? [] as $item) {
                            $catId = $item['category_id'] ?? null;
                            if ($catId && !isset($seenIds[$catId])) {
                                $seenIds[$catId] = true;
                                $catName = $catId;
                                try {
                                    $catResp = Http::timeout(5)->get("{$baseUrl}/categories/{$catId}");
                                    if ($catResp->successful()) {
                                        $catName = $catResp->json()['name'] ?? $catId;
                                    }
                                } catch (\Throwable $e) {}
                                $results[] = [
                                    'category_id'   => $catId,
                                    'category_name' => $catName,
                                    'domain_name'   => 'via busca de produtos',
                                ];
                                if (count($results) >= 10) break;
                            }
                        }
                    }
                } else {
                    Log::debug("ML search fallback({$query}): HTTP {$resp->status()}");
                }
            } catch (\Throwable $e) {
                Log::debug("ML search fallback({$query}) error: " . $e->getMessage());
            }
        }

        Log::info("ML searchCategories({$query}): " . count($results) . " results");

        return $results;
    }

    /**
     * Search seller items by query and return item details.
     */
    public function searchSellerItems(string $sellerId, string $query, int $limit = 20, ?string $excludeId = null): array
    {
        $searchResult = $this->get('/users/' . $sellerId . '/items/search', [
            'q'     => $query,
            'limit' => $limit,
        ]);

        $ids = collect($searchResult['results'] ?? [])
            ->when($excludeId, fn ($c) => $c->reject(fn ($id) => $id === $excludeId))
            ->take($limit)
            ->all();

        if (empty($ids)) {
            return [];
        }

        $response = $this->get('/items', ['ids' => implode(',', array_slice($ids, 0, 20))]);

        return collect($response)
            ->filter(fn ($r) => ($r['code'] ?? 200) === 200)
            ->map(fn ($r) => $r['body'] ?? $r)
            ->values()
            ->all();
    }

    /**
     * Update item fields via ML API.
     */
    public function updateItem(string $itemId, array $data): array
    {
        return $this->put("/items/{$itemId}", $data);
    }

    /**
     * Create or update item description via ML API.
     *
     * ML uses two different methods:
     *  - POST /items/{id}/description             → create (no description yet)
     *  - PUT  /items/{id}/description?api_version=2 → update existing (api_version=2 is REQUIRED)
     *
     * Without ?api_version=2, ML accepts the PUT but silently ignores the change.
     * @see https://developers.mercadolivre.com.br/en_us/item-description-2#Replacing
     */
    public function updateDescription(string $itemId, string $plainText): array
    {
        $payload = ['plain_text' => $plainText];
        $path    = "/items/{$itemId}/description";
        $url     = self::BASE_URL . $path;

        Log::info("ML updateDescription({$itemId}): sending PUT to {$url}?api_version=2, payload length=" . mb_strlen($plainText));

        // PUT with mandatory api_version=2 for existing descriptions
        $response = Http::withToken($this->token())
            ->timeout(30)
            ->put($url . '?api_version=2', $payload);

        Log::info("ML updateDescription({$itemId}): PUT response HTTP {$response->status()} body=" . substr($response->body(), 0, 500));

        // 404 = description doesn't exist yet → POST to create it
        if ($response->status() === 404) {
            Log::info("ML updateDescription({$itemId}): PUT 404, falling back to POST (create).");
            $response = Http::withToken($this->token())
                ->timeout(30)
                ->post($url, $payload);
            Log::info("ML updateDescription({$itemId}): POST response HTTP {$response->status()} body=" . substr($response->body(), 0, 500));
        }

        if ($response->failed()) {
            throw new \RuntimeException(
                "ML API description error [{$response->status()}] {$path}: " . $response->body()
            );
        }

        // Verify: re-read the description from ML to confirm it actually changed
        try {
            $verify = Http::withToken($this->token())
                ->timeout(15)
                ->get($url);
            $verifyData = $verify->json() ?? [];
            $savedText  = $verifyData['plain_text'] ?? '(empty)';
            $matches    = trim($savedText) === trim($plainText);
            Log::info("ML updateDescription({$itemId}): VERIFY — matches=" . ($matches ? 'YES' : 'NO')
                . " saved_length=" . mb_strlen($savedText)
                . " sent_length=" . mb_strlen($plainText)
                . " saved_preview=" . substr($savedText, 0, 100));
        } catch (\Throwable $e) {
            Log::warning("ML updateDescription({$itemId}): verify GET failed: " . $e->getMessage());
        }

        return $response->json() ?? [];
    }

    /**
     * Upgrade/downgrade listing type (Classic/Premium).
     * ML requires a POST to /items/{id}/listing_type — cannot be done via PUT.
     * The seller must have contracted a listing package (gold_special or gold_premium).
     */
    public function updateListingType(string $itemId, string $listingTypeId): array
    {
        return $this->post("/items/{$itemId}/listing_type", ['id' => $listingTypeId]);
    }

    /**
     * Update shipping settings: free_shipping, local_pick_up, handling_time, dimensions.
     */
    public function updateShipping(string $itemId, array $shippingData): array
    {
        return $this->put("/items/{$itemId}", ['shipping' => $shippingData]);
    }

    /**
     * Get available listing types for a specific item (upgrades + current).
     */
    public function getAvailableListingTypes(string $itemId): array
    {
        try {
            return $this->get("/items/{$itemId}/available_listing_types");
        } catch (\Throwable $e) {
            Log::warning("ML getAvailableListingTypes({$itemId}) failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get possible upgrades for a specific item.
     */
    public function getAvailableUpgrades(string $itemId): array
    {
        try {
            return $this->get("/items/{$itemId}/available_upgrades");
        } catch (\Throwable $e) {
            Log::warning("ML getAvailableUpgrades({$itemId}) failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get possible downgrades for a specific item.
     */
    public function getAvailableDowngrades(string $itemId): array
    {
        try {
            return $this->get("/items/{$itemId}/available_downgrades");
        } catch (\Throwable $e) {
            Log::warning("ML getAvailableDowngrades({$itemId}) failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Upload a picture file directly to ML and return the picture ID.
     * Supports JPG/PNG, max 10MB.
     */
    public function uploadPicture(UploadedFile $file): string
    {
        $token = $this->token();

        $response = Http::withToken($token)
            ->timeout(60)
            ->attach('file', $file->getContent(), $file->getClientOriginalName())
            ->post(self::BASE_URL . '/pictures/items/upload');

        if ($response->failed()) {
            throw new \RuntimeException(
                "ML uploadPicture error [{$response->status()}]: " . $response->body()
            );
        }

        $data = $response->json();
        return $data['id'] ?? throw new \RuntimeException('ML uploadPicture: resposta sem ID.');
    }

    /**
     * Publish a new item on ML.
     */
    public function publishItem(array $data): array
    {
        return $this->post('/items', $data);
    }

    // ─── Wholesale / quantity pricing ────────────────────────────────────────

    /**
     * Get item prices including wholesale tiers.
     */
    public function getItemPrices(string $itemId): array
    {
        return $this->get("/items/{$itemId}/prices");
    }

    /**
     * Update item wholesale/quantity pricing tiers via ML API.
     * POST /items/{ITEM_ID}/prices/standard/quantity
     */
    public function updateItemPrices(string $itemId, array $prices): array
    {
        return $this->post("/items/{$itemId}/prices/standard/quantity", ['prices' => $prices]);
    }

    // ─── Kits ────────────────────────────────────────────────────────────────

    /**
     * Search for eligible kit components (User Products) for a seller.
     * First call: no added_products. Subsequent calls: include added_products + ONLY_ELIGIBLE filter.
     *
     * @see https://developers.mercadolivre.com.br/pt_br/kits-virtuais
     */
    public function searchKitComponents(string $query, array $addedProductIds = [], ?string $mainProductId = null): array
    {
        $sellerId = $this->requireShopId();

        $body = ['active_channels' => ['marketplace']];

        if ($mainProductId) {
            $body['main_product_id'] = $mainProductId;
        }
        if (! empty($addedProductIds)) {
            $body['added_products'] = $addedProductIds;
            $body['search_filters'] = ['only_eligible' => 'ONLY_ELIGIBLE'];
        }

        try {
            $response = Http::withToken($this->token())
                ->timeout(20)
                ->post(self::BASE_URL . "/users/{$sellerId}/kits/components/search?searchText=" . urlencode($query) . '&limit=10', $body);

            if ($response->failed()) {
                Log::warning("ML searchKitComponents: HTTP {$response->status()} — " . substr($response->body(), 0, 300));
                return [];
            }
            return $response->json() ?? [];
        } catch (\Throwable $e) {
            Log::warning("ML searchKitComponents exception: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Create a Virtual Kit listing (POST /items/kits).
     * Requires user_product_id (MLBU...) for each component.
     *
     * @param array $components [['user_product_id' => 'MLBU...', 'quantity' => 2, 'discount' => 0.10], ...]
     * @see https://developers.mercadolivre.com.br/pt_br/kits-virtuais
     */
    public function createVirtualKit(string $familyName, array $components, ?float $price, string $listingTypeId, ?array $thumbnail = null, bool $autoPrice = false, float $autoDiscount = 0): array
    {
        $bundleComponents = array_map(function ($c) use ($autoPrice, $autoDiscount) {
            $comp = [
                'type'            => 'user_product',
                'user_product_id' => $c['user_product_id'],
                'quantity'        => (int) ($c['quantity'] ?? 1),
                'automatic_price' => null,
            ];
            if ($autoPrice && $autoDiscount > 0) {
                $comp['automatic_price'] = ['discount' => round($autoDiscount, 2)];
            }
            return $comp;
        }, $components);

        $payload = [
            'family_name'     => $familyName,
            'channels'        => ['marketplace'],
            'currency_id'     => 'BRL',
            'listing_type_id' => $listingTypeId,
            'bundle'          => [
                'type'       => 'kit',
                'components' => $bundleComponents,
            ],
        ];

        if (! $autoPrice && $price !== null) {
            $payload['price'] = round($price, 2);
        }

        if ($thumbnail) {
            $payload['thumbnail'] = $thumbnail;
        }

        return $this->post('/items/kits', $payload);
    }

    // ─── Promotions ──────────────────────────────────────────────────────────

    /**
     * Get active promotions for an item.
     * @see https://developers.mercadolivre.com.br/pt_br/desconto-individua
     */
    public function getItemPromotions(string $itemId): array
    {
        try {
            $response = Http::withToken($this->token())
                ->timeout(15)
                ->get(self::BASE_URL . "/seller-promotions/items/{$itemId}", [
                    'promotion_type' => 'PRICE_DISCOUNT',
                ]);

            if ($response->status() === 404) {
                return [];
            }
            if ($response->failed()) {
                Log::warning("ML getItemPromotions({$itemId}): HTTP {$response->status()}");
                return [];
            }
            $data = $response->json() ?? [];
            return is_array($data) ? $data : [$data];
        } catch (\Throwable $e) {
            Log::warning("ML getItemPromotions({$itemId}) exception: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Create a PRICE_DISCOUNT promotion on an item.
     *
     * @param array $data ['deal_price' => float, 'start_date' => 'Y-m-d\TH:i:s', 'finish_date' => ..., 'promotion_type' => 'PRICE_DISCOUNT']
     * @see https://developers.mercadolivre.com.br/pt_br/desconto-individua
     */
    public function createPromotion(string $itemId, array $data): array
    {
        return $this->post("/seller-promotions/items/{$itemId}", $data);
    }

    /**
     * Delete an active promotion from an item.
     */
    public function deletePromotion(string $itemId, string $promotionType = 'PRICE_DISCOUNT'): array
    {
        try {
            $response = Http::withToken($this->token())
                ->timeout(15)
                ->delete(self::BASE_URL . "/seller-promotions/items/{$itemId}", [
                    'promotion_type' => $promotionType,
                ]);

            if ($response->failed()) {
                throw new \RuntimeException(
                    "ML API DELETE error [{$response->status()}] /seller-promotions/items/{$itemId}: " . $response->body()
                );
            }
            return $response->json() ?? [];
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    // ─── Messages ────────────────────────────────────────────────────────────

    /**
     * Get messages for a pack/order.
     * Uses pack_id or order_id as fallback.
     */
    public function getMessages(string $packId, bool $markAsRead = false): array
    {
        $sellerId = $this->requireShopId();
        $params   = ['tag' => 'post_sale'];

        if (! $markAsRead) {
            $params['mark_as_read'] = 'false';
        }

        try {
            return $this->get("/messages/packs/{$packId}/sellers/{$sellerId}", $params);
        } catch (\Throwable $e) {
            Log::warning("ML getMessages(pack={$packId}) failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Send a message to the buyer (via MLB agent for Brazil).
     * Max 350 characters.
     */
    public function sendMessage(string $packId, string $text): array
    {
        $sellerId = $this->requireShopId();

        return $this->post(
            "/messages/packs/{$packId}/sellers/{$sellerId}",
            [
                'from' => ['user_id' => (string) $sellerId],
                'to'   => ['user_id' => (string) self::MLB_AGENT_ID],
                'text' => mb_substr($text, 0, 350),
            ],
            ['tag' => 'post_sale']
        );
    }

    // ─── Shipping Labels ─────────────────────────────────────────────────────

    /**
     * Retorna a URL do PDF de etiqueta oficial dos Correios/transportadora para
     * um ou mais shipment IDs. Endpoint: GET /shipment_labels?shipment_ids=X,Y&response_type=pdf
     *
     * @param  array<string|int>  $shipmentIds
     */
    public function getShipmentLabels(array $shipmentIds, string $responseType = 'pdf'): string
    {
        if (empty($shipmentIds)) {
            throw new \InvalidArgumentException('Informe ao menos um shipment_id.');
        }

        $ids      = implode(',', $shipmentIds);
        $response = Http::withToken($this->token())
            ->timeout(30)
            ->get(self::BASE_URL . '/shipment_labels', [
                'shipment_ids'  => $ids,
                'response_type' => $responseType, // 'pdf' | 'zpl2'
            ]);

        if ($response->failed()) {
            throw new \RuntimeException(
                "ML getShipmentLabels error [{$response->status()}]: " . $response->body()
            );
        }

        // A API retorna redirect para URL do PDF; o Http client segue o redirect automaticamente.
        // Devolvemos a URL final (ou o body se for conteúdo direto).
        $finalUrl = $response->effectiveUri()?->__toString() ?? '';

        if ($finalUrl && $finalUrl !== self::BASE_URL . '/shipment_labels') {
            return $finalUrl;
        }

        // Fallback: retorna body como base64 caso seja PDF binário
        return 'data:application/pdf;base64,' . base64_encode($response->body());
    }

    /**
     * Emite NF-e via Faturador integrado do Mercado Livre.
     *
     * Pré-requisitos: vendedor PJ com certificado digital A1 e Inscrição Estadual
     * configurados no ML. O Faturador emite a NF-e diretamente na SEFAZ.
     *
     * @param  array  $orderIds  Um ou mais IDs de pedido ML para consolidar numa NF-e
     * @return array  Resposta da API com id, status, access_key, invoice_number, etc.
     *
     * @see https://developers.mercadolivre.com.br/pt_br/api-fiscal-faturamento-de-venda
     */
    public function emitInvoice(array $orderIds): array
    {
        $sellerId = $this->requireShopId();

        return $this->post("/users/{$sellerId}/invoices/orders", [
            'orders' => array_map('intval', $orderIds),
        ]);
    }

    /**
     * Consulta o status de uma NF-e emitida pelo Faturador do ML.
     *
     * @param  string  $invoiceId  ID da invoice retornado pela emissão
     * @return array   Dados da NF-e: status, access_key, danfe_url, etc.
     */
    public function getInvoice(string $invoiceId): array
    {
        $sellerId = $this->requireShopId();

        return $this->get("/users/{$sellerId}/invoices/{$invoiceId}");
    }

    /**
     * Envia documento fiscal (PDF/XML de NF-e já emitida) ao pack do ML.
     * Usado para submeter NF-e emitida externamente (ex: Webmaniabr).
     *
     * @param  string       $packId     Pack ID do envio
     * @param  string       $accessKey  Chave de acesso de 44 dígitos
     * @param  string|null  $xmlPath    Caminho do XML da NF-e (opcional)
     */
    public function submitFiscalDocument(
        string $mlOrderId,
        string $accessKey,
        ?string $packId = null
    ): array {
        $sellerId = $this->requireShopId();

        if ($packId) {
            return $this->post("/packs/{$packId}/fiscal_documents", [
                'type'       => 'invoice',
                'invoice_id' => $accessKey,
            ]);
        }

        return $this->post("/users/{$sellerId}/invoices/orders/{$mlOrderId}", [
            'type'       => 'invoice',
            'invoice_id' => $accessKey,
        ]);
    }

    // ─── Fiscal Data (Dados Fiscais) ─────────────────────────────────────────

    /**
     * Consulta os dados fiscais de um anúncio.
     *
     * @see https://developers.mercadolivre.com.br/pt_br/envio-dos-dados-fiscais
     */
    public function getItemFiscalData(string $itemId): array
    {
        try {
            return $this->get("/items/{$itemId}/fiscal_information/detail");
        } catch (\Throwable $e) {
            // 404 = sem dados fiscais cadastrados
            if (str_contains($e->getMessage(), '404')) {
                return [];
            }
            throw $e;
        }
    }

    /**
     * Verifica se um anúncio (ou variação) pode ser faturado.
     *
     * Para itens com variações, deve-se usar:
     *   GET /can_invoice/items/{itemId}/variations/{variationId}
     */
    public function canInvoiceItem(string $itemId, ?string $variationId = null): array
    {
        try {
            $url = "/can_invoice/items/{$itemId}";
            if ($variationId) {
                $url .= "/variations/{$variationId}";
            }

            return $this->get($url);
        } catch (\Throwable $e) {
            Log::warning("ML canInvoiceItem({$itemId}, var:{$variationId}): " . $e->getMessage());

            return ['can_invoice' => false, 'status' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Cria dados fiscais para um produto (SKU).
     *
     * Campos obrigatórios:
     *  - sku, title, type (single|bundle), cost
     *  - tax_information: ncm, origin_type (manufacturer|reseller|imported), origin_detail
     *
     * Campos opcionais:
     *  - tax_information: tax_rule_id (Regime Normal), csosn (Simples), cest, ean, fci, ex_tipi
     *  - measurement_unit (UN, KG, L, etc.)
     *
     * @see https://developers.mercadolivre.com.br/pt_br/envio-dos-dados-fiscais
     */
    public function createFiscalData(array $data): array
    {
        return $this->post('/items/fiscal_information', $data);
    }

    /**
     * Atualiza dados fiscais completos de um SKU (PUT — substitui tudo).
     */
    public function updateFiscalData(string $sku, array $data): array
    {
        return $this->put("/items/fiscal_information/{$sku}", $data);
    }

    /**
     * Atualização parcial de dados fiscais (PATCH).
     * Campos permitidos: cost, measurement_unit, fci, ex_tipi, tax_rule_id,
     * med_anvisa_code, med_exemption_reason.
     */
    public function patchFiscalData(string $sku, array $data): array
    {
        return $this->patch("/items/fiscal_information/{$sku}", $data);
    }

    /**
     * Vincula um SKU (com dados fiscais) a um anúncio/variação.
     *
     * @param  string       $sku          Código do produto (fiscal)
     * @param  string       $itemId       ID do anúncio ML (ex: MLB1234567890)
     * @param  string|null  $variationId  ID da variação (null se item sem variações)
     */
    public function linkFiscalDataToItem(string $sku, string $itemId, ?string $variationId = null): array
    {
        $payload = [
            'sku'     => $sku,
            'item_id' => $itemId,
        ];

        if ($variationId) {
            $payload['variation_id'] = $variationId;
        }

        return $this->post('/items/fiscal_information/items', $payload);
    }

    /**
     * Lista as regras tributárias do vendedor (Regime Normal).
     *
     * @see https://developers.mercadolivre.com.br/pt_br/envio-regras-tributarias
     */
    public function getTaxRules(): array
    {
        $sellerId = $this->requireShopId();

        return $this->get("/users/{$sellerId}/invoices/tax_rules");
    }

    /**
     * Consulta uma regra tributária específica.
     */
    public function getTaxRule(string $ruleId): array
    {
        $sellerId = $this->requireShopId();

        return $this->get("/users/{$sellerId}/invoices/tax_rules/{$ruleId}");
    }

    // ─── Status Mappers ───────────────────────────────────────────────────────

    public static function mapOrderStatus(string $mlStatus): string
    {
        return match ($mlStatus) {
            'confirmed', 'paid', 'partially_delivered' => 'confirmed',
            'ready_to_ship'                             => 'ready_to_ship',
            'shipped'                                   => 'shipped',
            'delivered'                                 => 'delivered',
            'cancelled', 'invalid'                      => 'cancelled',
            default                                     => 'pending',
        };
    }

    public static function mapPaymentStatus(string $mlPaymentStatus): string
    {
        return match ($mlPaymentStatus) {
            'approved'                 => 'paid',
            'refunded', 'charged_back' => 'refunded',
            'rejected', 'cancelled'    => 'cancelled',
            default                    => 'pending',
        };
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function requireShopId(): string
    {
        $shopId = $this->account->shop_id;
        if (! $shopId) {
            throw new \RuntimeException("Conta {$this->account->id} não possui shop_id (user_id do ML).");
        }
        return $shopId;
    }
}
