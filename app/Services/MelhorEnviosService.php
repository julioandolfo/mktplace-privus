<?php

namespace App\Services;

use App\Models\MelhorEnviosAccount;
use App\Models\Order;
use App\Models\ShipmentLabel;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Serviço de integração com a API do Melhor Envios.
 *
 * Documentação: https://docs.melhorenvios.com.br/
 * Autenticação: OAuth2 Authorization Code Flow.
 * Ambientes:
 *   - Sandbox: https://sandbox.melhorenvios.com.br
 *   - Produção: https://melhorenvios.com.br
 */
class MelhorEnviosService
{
    private const PROD_URL    = 'https://melhorenvios.com.br';
    private const SANDBOX_URL = 'https://sandbox.melhorenvios.com.br';

    public function __construct(private MelhorEnviosAccount $account) {}

    // ─── HTTP / Auth ─────────────────────────────────────────────────────────

    private function baseUrl(): string
    {
        return $this->account->isSandbox() ? self::SANDBOX_URL : self::PROD_URL;
    }

    private function http()
    {
        $this->refreshTokenIfNeeded();

        return Http::withToken($this->account->access_token)
            ->withHeaders([
                'Accept'     => 'application/json',
                'User-Agent' => config('app.name') . ' (suporte@' . parse_url(config('app.url'), PHP_URL_HOST) . ')',
            ])
            ->timeout(30);
    }

    private function get(string $path, array $params = []): array
    {
        $response = $this->http()->get($this->baseUrl() . '/api/v2' . $path, $params);

        if ($response->failed()) {
            throw new \RuntimeException(
                "MelhorEnvios GET {$path} [{$response->status()}]: " . $response->body()
            );
        }

        return $response->json() ?? [];
    }

    private function post(string $path, array $data): mixed
    {
        $response = $this->http()->post($this->baseUrl() . '/api/v2' . $path, $data);

        if ($response->failed()) {
            throw new \RuntimeException(
                "MelhorEnvios POST {$path} [{$response->status()}]: " . $response->body()
            );
        }

        return $response->json();
    }

    // ─── OAuth2 ──────────────────────────────────────────────────────────────

    /**
     * URL de autorização OAuth2 para redirecionar o usuário.
     */
    public function authorizationUrl(string $redirectUri, string $state = ''): string
    {
        $params = http_build_query([
            'response_type' => 'code',
            'client_id'     => $this->account->client_id,
            'redirect_uri'  => $redirectUri,
            'scope'         => 'cart-read cart-write companies-read coupons-read notifications-read orders-read products-read products-write purchases-read shipping-calculate shipping-cancel shipping-checkout shipping-companies shipping-generate shipping-preview shipping-print shipping-share shipping-tracking ecommerce-shipping transactions-read users-read users-write',
            'state'         => $state,
        ]);

        return $this->baseUrl() . '/oauth/authorize?' . $params;
    }

    /**
     * Troca o authorization code por access_token + refresh_token.
     */
    public function exchangeCode(string $code, string $redirectUri): void
    {
        $response = Http::post($this->baseUrl() . '/oauth/token', [
            'grant_type'    => 'authorization_code',
            'client_id'     => $this->account->client_id,
            'client_secret' => $this->account->client_secret,
            'redirect_uri'  => $redirectUri,
            'code'          => $code,
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('ME OAuth token exchange failed: ' . $response->body());
        }

        $this->saveTokens($response->json());
    }

    /**
     * Renova o access_token usando o refresh_token.
     */
    public function refreshToken(): void
    {
        $response = Http::post($this->baseUrl() . '/oauth/token', [
            'grant_type'    => 'refresh_token',
            'client_id'     => $this->account->client_id,
            'client_secret' => $this->account->client_secret,
            'refresh_token' => $this->account->refresh_token,
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('ME OAuth refresh failed: ' . $response->body());
        }

        $this->saveTokens($response->json());
    }

    private function refreshTokenIfNeeded(): void
    {
        if ($this->account->isTokenExpired() && $this->account->refresh_token) {
            $this->refreshToken();
        }
    }

    private function saveTokens(array $data): void
    {
        $this->account->update([
            'access_token'    => $data['access_token'],
            'refresh_token'   => $data['refresh_token'] ?? $this->account->refresh_token,
            'token_expires_at'=> isset($data['expires_in'])
                ? now()->addSeconds($data['expires_in'])
                : null,
            'is_active'       => true,
        ]);
    }

    // ─── Cotação ─────────────────────────────────────────────────────────────

    /**
     * Realiza cotação de frete para um pedido.
     *
     * @param  array  $to      ['postal_code' => '01310-100']
     * @param  array  $package ['weight' => 0.3, 'width' => 12, 'height' => 4, 'length' => 17, 'insurance_value' => 50.00]
     * @return array Lista de opções de frete ordenadas por preço
     */
    public function quote(array $to, array $package): array
    {
        $from = [
            'postal_code' => preg_replace('/\D/', '', $this->account->from_cep ?? ''),
        ];

        $payload = [
            'from'    => $from,
            'to'      => ['postal_code' => preg_replace('/\D/', '', $to['postal_code'] ?? '')],
            'package' => [
                'height'          => (float) ($package['height'] ?? 4),
                'width'           => (float) ($package['width'] ?? 12),
                'length'          => (float) ($package['length'] ?? 17),
                'weight'          => (float) ($package['weight'] ?? 0.3),
            ],
            'options' => [
                'insurance_value' => (float) ($package['insurance_value'] ?? 0),
                'receipt'         => false,
                'own_hand'        => false,
            ],
            'services' => '1,2,3,4,31,32,33,34,35,17', // Correios + Jadlog + Total Express
        ];

        return $this->post('/me/shipment/calculate', $payload) ?? [];
    }

    /**
     * Cotação baseada nos dados de um Order.
     */
    public function quoteForOrder(Order $order, array $packageOverrides = []): array
    {
        $addr = $order->shipping_address ?? [];
        $zip  = preg_replace('/\D/', '', $addr['zip'] ?? $addr['zipcode'] ?? '');

        if (! $zip) {
            throw new \InvalidArgumentException('CEP de destino não encontrado no pedido.');
        }

        $defaults = $this->account->default_package ?? [];

        return $this->quote(
            ['postal_code' => $zip],
            array_merge([
                'weight'          => $defaults['weight'] ?? 0.5,
                'width'           => $defaults['width'] ?? 12,
                'height'          => $defaults['height'] ?? 4,
                'length'          => $defaults['length'] ?? 17,
                'insurance_value' => $order->total,
            ], $packageOverrides)
        );
    }

    // ─── Carrinho / Checkout ──────────────────────────────────────────────────

    /**
     * Adiciona uma etiqueta ao carrinho do ME.
     * Retorna o ID do item no carrinho.
     */
    public function addToCart(Order $order, int $serviceId, array $packageData = []): array
    {
        $addr    = $order->shipping_address ?? [];
        $from    = $this->account->from_address ?? [];
        $defaults = $this->account->default_package ?? [];

        $payload = [
            'service'  => $serviceId,
            'agency'   => null,
            'from' => [
                'name'          => $this->account->from_name ?? config('app.name'),
                'phone'         => preg_replace('/\D/', '', $from['phone'] ?? ''),
                'email'         => $from['email'] ?? '',
                'document'      => preg_replace('/\D/', '', $this->account->from_document ?? ''),
                'company_document' => '',
                'state_register'   => '',
                'address'       => $from['street'] ?? '',
                'complement'    => $from['complement'] ?? '',
                'number'        => $from['number'] ?? '',
                'district'      => $from['neighborhood'] ?? '',
                'city'          => $from['city'] ?? '',
                'country_id'    => 'BR',
                'postal_code'   => preg_replace('/\D/', '', $this->account->from_cep ?? ''),
                'note'          => '',
            ],
            'to' => [
                'name'       => $order->customer_name,
                'phone'      => preg_replace('/\D/', '', $order->customer_phone ?? ''),
                'email'      => $order->customer_email ?? '',
                'document'   => preg_replace('/\D/', '', $order->customer_document ?? ''),
                'address'    => $addr['street'] ?? '',
                'complement' => $addr['complement'] ?? '',
                'number'     => $addr['number'] ?? '',
                'district'   => $addr['neighborhood'] ?? '',
                'city'       => $addr['city'] ?? '',
                'country_id' => 'BR',
                'postal_code'=> preg_replace('/\D/', '', $addr['zip'] ?? $addr['zipcode'] ?? ''),
            ],
            'products' => $order->items->map(fn ($item) => [
                'name'     => mb_substr($item->name, 0, 80),
                'quantity' => $item->quantity,
                'unitary_value' => $item->unit_price,
            ])->toArray(),
            'volumes' => [
                [
                    'height' => (float) ($packageData['height'] ?? $defaults['height'] ?? 4),
                    'width'  => (float) ($packageData['width'] ?? $defaults['width'] ?? 12),
                    'length' => (float) ($packageData['length'] ?? $defaults['length'] ?? 17),
                    'weight' => (float) ($packageData['weight'] ?? $defaults['weight'] ?? 0.5),
                ],
            ],
            'options' => [
                'insurance_value'    => $order->total,
                'receipt'            => false,
                'own_hand'           => false,
                'reverse'            => false,
                'non_commercial'     => false,
                'invoice'            => [
                    'key' => $order->invoices->last()?->access_key ?? '',
                ],
            ],
        ];

        return $this->post('/me/cart', $payload) ?? [];
    }

    /**
     * Realiza o checkout (compra) das etiquetas no carrinho.
     * Deduz da carteira ME do usuário.
     *
     * @param  array<string>  $orderIds IDs de itens do carrinho ME
     */
    public function checkout(array $orderIds): array
    {
        return $this->post('/me/shipment/checkout', ['orders' => $orderIds]) ?? [];
    }

    // ─── Geração de Etiqueta ──────────────────────────────────────────────────

    /**
     * Gera (queue) as etiquetas para impressão.
     * As etiquetas compradas precisam ser geradas antes de imprimir.
     *
     * @param  array<string>  $orderIds IDs de itens do carrinho ME (após checkout)
     */
    public function generateLabels(array $orderIds): array
    {
        return $this->post('/me/shipment/generate', ['orders' => $orderIds]) ?? [];
    }

    /**
     * Retorna a URL de impressão da etiqueta.
     *
     * @param  array<string>  $orderIds
     * @param  string  $mode 'public' | 'third' | 'customized'
     */
    public function printLabels(array $orderIds, string $mode = 'public'): array
    {
        return $this->post('/me/shipment/print', [
            'mode'   => $mode,
            'orders' => $orderIds,
        ]) ?? [];
    }

    // ─── Rastreio ────────────────────────────────────────────────────────────

    /**
     * Busca rastreio de um ou mais pedidos ME.
     *
     * @param  array<string>  $orderIds
     */
    public function tracking(array $orderIds): array
    {
        return $this->post('/me/shipment/tracking', ['orders' => $orderIds]) ?? [];
    }

    // ─── Fluxo Completo ───────────────────────────────────────────────────────

    /**
     * Fluxo completo: adiciona ao carrinho → checkout → gera etiqueta → retorna URL de impressão.
     * Persiste um ShipmentLabel no banco.
     *
     * @param  array  $quoteOption  Item da cotação com 'id' (service_id) e 'price'
     * @param  array  $packageData  Dimensões do pacote
     */
    public function purchaseLabel(Order $order, array $quoteOption, array $packageData = []): ShipmentLabel
    {
        // 1. Adiciona ao carrinho
        $cartItem = $this->addToCart($order, $quoteOption['id'], $packageData);

        if (empty($cartItem['id'])) {
            throw new \RuntimeException('ME: resposta do carrinho não contém ID.');
        }

        $meOrderId = $cartItem['id'];

        // 2. Checkout (compra)
        $this->checkout([$meOrderId]);

        // 3. Gera etiqueta
        $this->generateLabels([$meOrderId]);

        // 4. Solicita URL de impressão
        $printResult = $this->printLabels([$meOrderId]);
        $labelUrl    = $printResult['url'] ?? ($printResult[0]['url'] ?? null);

        // 5. Persiste no banco
        $label = ShipmentLabel::updateOrCreate(
            ['me_label_id' => (string) $meOrderId],
            [
                'order_id'              => $order->id,
                'melhor_envios_account_id' => $this->account->id,
                'carrier'               => $quoteOption['company']['name'] ?? null,
                'service'               => $quoteOption['name'] ?? null,
                'cost'                  => $quoteOption['price'] ?? null,
                'customer_paid'         => $order->shipping_cost,
                'label_url'             => $labelUrl,
                'status'                => 'purchased',
                'purchased_at'          => now(),
                'meta'                  => [
                    'cart_item'    => $cartItem,
                    'quote_option' => $quoteOption,
                ],
            ]
        );

        return $label;
    }

    // ─── Relatório Financeiro ─────────────────────────────────────────────────

    /**
     * Retorna extrato financeiro da carteira ME (transações).
     */
    public function financialBalance(): array
    {
        return $this->get('/me/balance') ?? [];
    }

    /**
     * Retorna histórico de compras de etiquetas.
     */
    public function purchaseHistory(int $page = 1, int $perPage = 20): array
    {
        return $this->get('/me/shipment/generate', ['per_page' => $perPage, 'page' => $page]) ?? [];
    }
}
