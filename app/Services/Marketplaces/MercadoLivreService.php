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

    public function __construct(private MarketplaceAccount $account) {}

    // ─── HTTP Helpers ────────────────────────────────────────────────────────

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
        $response = Http::withToken($this->token())
            ->timeout(30)
            ->get(self::BASE_URL . $path, $params);

        if ($response->failed()) {
            throw new \RuntimeException(
                "ML API error [{$response->status()}] GET {$path}: " . $response->body()
            );
        }

        return $response->json() ?? [];
    }

    private function put(string $path, array $data): array
    {
        $response = Http::withToken($this->token())
            ->timeout(30)
            ->put(self::BASE_URL . $path, $data);

        if ($response->failed()) {
            throw new \RuntimeException(
                "ML API PUT error [{$response->status()}] {$path}: " . $response->body()
            );
        }

        return $response->json() ?? [];
    }

    private function post(string $path, array $data, array $query = []): array
    {
        $request = Http::withToken($this->token())->timeout(30);

        $url = self::BASE_URL . $path;
        if (! empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        $response = $request->post($url, $data);

        if ($response->failed()) {
            throw new \RuntimeException(
                "ML API POST error [{$response->status()}] {$path}: " . $response->body()
            );
        }

        return $response->json() ?? [];
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
     */
    public function getItemWithVariations(string $itemId): array
    {
        try {
            return $this->get("/items/{$itemId}", ['include' => 'variations']);
        } catch (\Throwable $e) {
            Log::warning("ML getItemWithVariations({$itemId}) failed: " . $e->getMessage());
            return $this->getItem($itemId);
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
     */
    public function searchCategories(string $query): array
    {
        try {
            return $this->get('/sites/MLB/domain_discovery/search', ['q' => $query, 'limit' => 20]);
        } catch (\Throwable $e) {
            Log::warning("ML searchCategories({$query}) failed: " . $e->getMessage());
            return [];
        }
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
