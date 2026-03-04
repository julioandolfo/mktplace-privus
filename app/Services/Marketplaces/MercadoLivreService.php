<?php

namespace App\Services\Marketplaces;

use App\Models\MarketplaceAccount;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MercadoLivreService
{
    private const BASE_URL = 'https://api.mercadolibre.com';

    public function __construct(private MarketplaceAccount $account) {}

    private function get(string $path, array $params = []): array
    {
        $creds = $this->account->credentials ?? [];
        $token = $creds['access_token'] ?? null;

        if (! $token) {
            throw new \RuntimeException("Conta {$this->account->id} não possui access_token.");
        }

        $response = Http::withToken($token)
            ->timeout(30)
            ->get(self::BASE_URL . $path, $params);

        if ($response->failed()) {
            throw new \RuntimeException(
                "ML API error [{$response->status()}] {$path}: " . $response->body()
            );
        }

        return $response->json();
    }

    /**
     * Yield pages of orders from ML API.
     * Each yielded value is an array of order objects.
     */
    public function getOrders(?Carbon $since = null): \Generator
    {
        $shopId = $this->account->shop_id;
        if (! $shopId) {
            throw new \RuntimeException("Conta {$this->account->id} não possui shop_id (user_id do ML).");
        }

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
     * Yield batches of listing details (up to 20 per batch).
     * First fetches item IDs, then fetches details in batches.
     */
    public function getListings(): \Generator
    {
        $shopId = $this->account->shop_id;
        if (! $shopId) {
            throw new \RuntimeException("Conta {$this->account->id} não possui shop_id.");
        }

        $offset = 0;
        $limit  = 100;
        $allIds = [];

        // Collect all item IDs
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

        // Fetch details in batches of 20
        foreach (array_chunk($allIds, 20) as $batch) {
            $data = $this->get('/items', ['ids' => implode(',', $batch)]);
            // Response is array of {code, body} objects
            $items = array_filter(
                array_map(fn ($r) => $r['body'] ?? null, $data),
                fn ($item) => $item !== null && ($item['id'] ?? null) !== null
            );
            yield array_values($items);
        }
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
            default                                     => 'pending', // payment_required, payment_in_process
        };
    }

    public static function mapPaymentStatus(string $mlPaymentStatus): string
    {
        return match ($mlPaymentStatus) {
            'approved'                       => 'paid',
            'refunded', 'charged_back'       => 'refunded',
            'rejected', 'cancelled'          => 'cancelled',
            default                          => 'pending', // pending, in_process
        };
    }
}
