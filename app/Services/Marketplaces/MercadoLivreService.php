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

        do {
            $params['offset'] = $offset;
            $data = $this->get('/orders/search', $params);

            $results = $data['results'] ?? [];
            $total   = $data['paging']['total'] ?? 0;

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
     * Get item quality/health score from ML API.
     * Endpoint: GET /items/{id}/health
     * Returns: health (0-1), level (basic|standard|professional), goals[]
     */
    public function getItemQuality(string $itemId): array
    {
        try {
            return $this->get("/items/{$itemId}/health");
        } catch (\Throwable $e) {
            Log::warning("ML getItemQuality({$itemId}) failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get pending health actions (goals not yet completed) for an item.
     * Endpoint: GET /items/{id}/health/actions
     */
    public function getItemHealthActions(string $itemId): array
    {
        try {
            return $this->get("/items/{$itemId}/health/actions");
        } catch (\Throwable $e) {
            Log::warning("ML getItemHealthActions({$itemId}) failed: " . $e->getMessage());
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
     * Update item description via ML API (separate endpoint).
     */
    public function updateDescription(string $itemId, string $plainText): array
    {
        return $this->put("/items/{$itemId}/description", ['plain_text' => $plainText]);
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
