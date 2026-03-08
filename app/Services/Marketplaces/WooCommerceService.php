<?php

namespace App\Services\Marketplaces;

use App\Models\MarketplaceAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Serviço de integração com a API REST do WooCommerce.
 *
 * Autenticação: Basic Auth com consumer_key e consumer_secret.
 * Base URL configurada nas credenciais da conta: credentials.store_url
 */
class WooCommerceService
{
    private string $baseUrl;
    private string $consumerKey;
    private string $consumerSecret;

    public function __construct(private MarketplaceAccount $account)
    {
        $creds = $account->credentials ?? [];

        $this->baseUrl        = rtrim($creds['store_url'] ?? '', '/');
        $this->consumerKey    = $creds['consumer_key'] ?? '';
        $this->consumerSecret = $creds['consumer_secret'] ?? '';

        if (! $this->baseUrl || ! $this->consumerKey || ! $this->consumerSecret) {
            throw new \RuntimeException("Conta WooCommerce #{$account->id} sem credenciais configuradas.");
        }
    }

    // ─── HTTP Helpers ────────────────────────────────────────────────────────

    private function endpoint(string $path): string
    {
        return $this->baseUrl . '/wp-json/wc/v3' . $path;
    }

    private function http()
    {
        return Http::withBasicAuth($this->consumerKey, $this->consumerSecret)->timeout(30);
    }

    private function get(string $path, array $params = []): array
    {
        $response = $this->http()->get($this->endpoint($path), $params);

        if ($response->failed()) {
            throw new \RuntimeException(
                "WooCommerce GET {$path} error [{$response->status()}]: " . $response->body()
            );
        }

        return $response->json() ?? [];
    }

    private function put(string $path, array $data): array
    {
        $response = $this->http()->put($this->endpoint($path), $data);

        if ($response->failed()) {
            throw new \RuntimeException(
                "WooCommerce PUT {$path} error [{$response->status()}]: " . $response->body()
            );
        }

        return $response->json() ?? [];
    }

    // ─── Status ──────────────────────────────────────────────────────────────

    /**
     * Retorna todos os status de pedido disponíveis na loja WooCommerce.
     * Inclui os status padrão (wc-pending, wc-processing, wc-on-hold, etc.)
     * e quaisquer status personalizados criados por plugins.
     *
     * @return array<string, string> ['wc-custom-slug' => 'Label do status']
     */
    public function getAvailableStatuses(): array
    {
        try {
            $response = $this->http()->get($this->endpoint('/orders/statuses'));

            if ($response->failed()) {
                Log::warning("WooCommerce getAvailableStatuses falhou: " . $response->body());
                return $this->defaultStatuses();
            }

            $data = $response->json();

            // Endpoint /orders/statuses retorna { "wc-pending": "Pending payment", ... }
            if (is_array($data) && ! isset($data[0])) {
                return $data;
            }

            return $this->defaultStatuses();
        } catch (\Throwable $e) {
            Log::warning("WooCommerce getAvailableStatuses exception: " . $e->getMessage());
            return $this->defaultStatuses();
        }
    }

    /**
     * Status padrão do WooCommerce como fallback.
     */
    private function defaultStatuses(): array
    {
        return [
            'pending'    => 'Pending payment',
            'processing' => 'Processing',
            'on-hold'    => 'On hold',
            'completed'  => 'Completed',
            'cancelled'  => 'Cancelled',
            'refunded'   => 'Refunded',
            'failed'     => 'Failed',
        ];
    }

    // ─── Atualização de Pedido ────────────────────────────────────────────────

    /**
     * Marca o pedido como "pronto para envio" usando o status configurado na conta.
     */
    public function markReadyToShip(string $externalOrderId): array
    {
        $settings      = $this->account->settings ?? [];
        $readyStatus   = $settings['woo_ready_to_ship_status'] ?? 'processing';

        return $this->updateOrderStatus($externalOrderId, $readyStatus);
    }

    /**
     * Marca o pedido como "despachado/enviado" e registra o código de rastreio.
     */
    public function markShipped(string $externalOrderId, string $trackingCode = '', string $carrier = ''): array
    {
        $settings      = $this->account->settings ?? [];
        $shippedStatus = $settings['woo_shipped_status'] ?? 'completed';

        $data = ['status' => $shippedStatus];

        if ($trackingCode) {
            // Se tiver plugin de rastreio (por ex: WC Shipment Tracking), adiciona metadados
            $data['meta_data'] = [
                ['key' => '_wc_shipment_tracking_items', 'value' => [[
                    'tracking_provider'  => $carrier ?: 'Correios',
                    'tracking_number'    => $trackingCode,
                    'date_shipped'       => now()->format('Y-m-d'),
                    'tracking_link'      => '',
                ]]],
                ['key' => '_tracking_code',    'value' => $trackingCode],
                ['key' => '_tracking_carrier', 'value' => $carrier],
            ];
        }

        return $this->updateOrderStatus($externalOrderId, $shippedStatus, $data);
    }

    /**
     * Atualiza o status de um pedido WooCommerce.
     */
    public function updateOrderStatus(string $externalOrderId, string $status, array $extraData = []): array
    {
        $data = array_merge(['status' => $status], $extraData);

        return $this->put("/orders/{$externalOrderId}", $data);
    }

    /**
     * Busca os dados completos de um pedido WooCommerce.
     */
    public function getOrder(string $externalOrderId): array
    {
        return $this->get("/orders/{$externalOrderId}");
    }

    /**
     * Retorna os pedidos recentes da loja.
     */
    public function getOrders(int $page = 1, int $perPage = 50, ?string $status = null): array
    {
        $params = [
            'page'     => $page,
            'per_page' => $perPage,
            'orderby'  => 'date',
            'order'    => 'desc',
        ];

        if ($status) {
            $params['status'] = $status;
        }

        return $this->get('/orders', $params);
    }
}
