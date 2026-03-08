<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\WebmaniaAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Serviço de emissão de NF-e via Webmaniabr (API 2.0).
 *
 * Documentação: https://webmaniabr.com/docs/rest-api-nf-e/
 * Autenticação: Bearer token (API 2.0) ou OAuth 1.0 (API 1.0 legacy).
 */
class WebmaniaService
{
    private string $baseUrl = 'https://webmaniabr.com/api/2';

    public function __construct(private WebmaniaAccount $account) {}

    // ─── HTTP Helpers ────────────────────────────────────────────────────────

    private function http()
    {
        return Http::withToken($this->account->bearer_token)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->timeout(60);
    }

    private function post(string $path, array $data): array
    {
        $response = $this->http()->post($this->baseUrl . $path, $data);

        if ($response->failed()) {
            $body = $response->json() ?? [];
            $msg  = $body['message'] ?? $response->body();
            throw new \RuntimeException("Webmaniabr POST {$path} [{$response->status()}]: {$msg}");
        }

        return $response->json() ?? [];
    }

    private function get(string $path, array $params = []): array
    {
        $response = $this->http()->get($this->baseUrl . $path, $params);

        if ($response->failed()) {
            throw new \RuntimeException("Webmaniabr GET {$path} [{$response->status()}]: " . $response->body());
        }

        return $response->json() ?? [];
    }

    // ─── Emissão ─────────────────────────────────────────────────────────────

    /**
     * Emite uma NF-e para um pedido.
     * Retorna os dados da nota (uuid, numero, serie, chave, status, url_pdf, url_xml).
     *
     * @param  array  $overrides  Sobrescreve campos do payload padrão
     */
    public function emit(Order $order, array $overrides = []): array
    {
        $payload = $this->buildPayload($order, $overrides);

        Log::info("Webmaniabr emitindo NF-e para pedido #{$order->id}", ['payload_keys' => array_keys($payload)]);

        $result = $this->post('/nfe/emissao', $payload);

        // Persiste no banco
        $this->persistInvoice($order, $result, $payload);

        return $result;
    }

    /**
     * Retorna o DANFE em base64 sem efetuar emissão (apenas pré-visualização).
     */
    public function preview(Order $order, array $overrides = []): array
    {
        $payload              = $this->buildPayload($order, $overrides);
        $payload['visualizar'] = true;

        return $this->post('/nfe/emissao', $payload);
    }

    /**
     * Cancela uma NF-e já emitida.
     */
    public function cancel(string $uuid, string $reason = 'Cancelamento a pedido do emitente'): array
    {
        return $this->post('/nfe/cancelamento', [
            'uuid'     => $uuid,
            'motivo'   => $reason,
        ]);
    }

    // ─── Payload Builder ─────────────────────────────────────────────────────

    /**
     * Monta o payload completo para emissão de NF-e a partir de um Order.
     * Usa os defaults configurados na WebmaniaAccount e permite sobrescritas pontuais.
     */
    public function buildPayload(Order $order, array $overrides = []): array
    {
        $acc     = $this->account;
        $meta    = $order->meta ?? [];
        $addr    = $order->shipping_address ?? [];
        $company = $order->company;

        // ── Emitente (puxado da empresa no sistema)
        $emitente = [
            'nome'    => $company->name,
            'cnpj'    => preg_replace('/\D/', '', $company->document ?? ''),
            'ie'      => $company->state_registration ?? '',
            'endereco'=> $company->address['street'] ?? '',
            'numero'  => $company->address['number'] ?? 'S/N',
            'complemento' => $company->address['complement'] ?? '',
            'bairro'  => $company->address['neighborhood'] ?? '',
            'cidade'  => $company->address['city'] ?? '',
            'uf'      => $company->address['state'] ?? '',
            'cep'     => preg_replace('/\D/', '', $company->address['zipcode'] ?? ''),
            'telefone'=> preg_replace('/\D/', '', $company->phone ?? ''),
        ];

        // ── Destinatário
        $destDoc  = preg_replace('/\D/', '', $order->customer_document ?? '');
        $destType = strlen($destDoc) === 14 ? 'cnpj' : 'cpf';

        $destinatario = [
            'nome'        => $order->customer_name,
            $destType     => $destDoc,
            'email'       => $order->customer_email ?? '',
            'telefone'    => preg_replace('/\D/', '', $order->customer_phone ?? ''),
            'endereco'    => $addr['street'] ?? '',
            'numero'      => $addr['number'] ?? 'S/N',
            'complemento' => $addr['complement'] ?? '',
            'bairro'      => $addr['neighborhood'] ?? '',
            'cidade'      => $addr['city'] ?? '',
            'uf'          => $addr['state'] ?? '',
            'cep'         => preg_replace('/\D/', '', $addr['zip'] ?? $addr['zipcode'] ?? ''),
        ];

        // ── Itens
        $itens = [];
        foreach ($order->items as $item) {
            $product = $item->product;
            $ncm     = $product?->meta['ncm'] ?? $acc->default_ncm ?? '00000000';
            $cfop    = $acc->default_cfop ?? '5102';

            $itens[] = [
                'nome'      => mb_substr($item->name, 0, 120),
                'codigo'    => $item->sku ?: $item->id,
                'ncm'       => $ncm,
                'cest'      => $acc->default_cest ?? '',
                'cfop'      => $cfop,
                'unidade'   => 'UN',
                'quantidade'=> $item->quantity,
                'valor'     => round($item->unit_price, 2),
                'desconto'  => round($item->discount, 2),
                'origem'    => $acc->default_origin ?? '0',
                'impostos'  => [
                    'icms' => ['situacao_tributaria' => $acc->default_tax_class ?? '400'],
                    'pis'  => ['situacao_tributaria' => '07'],
                    'cofins' => ['situacao_tributaria' => '07'],
                ],
            ];
        }

        // ── Frete
        $frete = [
            'modalidade' => (int) ($acc->default_shipping_modality ?? 9),
            'valor'      => round($order->shipping_cost, 2),
        ];
        if ($order->tracking_code) {
            $frete['numero_volumes'] = $order->expedition_volumes ?? 1;
        }

        // ── Intermediador (marketplace)
        $intermediador = null;
        if (($acc->intermediador_type ?? '0') !== '0') {
            $intermediador = [
                'tipo'       => $acc->intermediador_type,
                'cnpj'       => $acc->intermediador_cnpj ?? '',
                'identificador' => $acc->intermediador_id ?? '',
            ];
        }

        $payload = [
            'operacao'      => 1, // 1 = saída
            'natureza_operacao' => $acc->default_nature_operation ?? 'Venda',
            'modelo'        => 55, // NF-e
            'emissao'       => 1,  // 1 = normal
            'emitente'      => $emitente,
            'destinatario'  => $destinatario,
            'produtos'      => $itens,
            'frete'         => $frete,
            'informacoes_adicionais' => [
                'fisco'      => $acc->additional_info_fisco ?? '',
                'contribuinte' => $acc->additional_info_consumer ?? '',
            ],
        ];

        if ($intermediador) {
            $payload['intermediador'] = $intermediador;
        }

        if ($acc->auto_send_email) {
            $payload['enviar_email'] = true;
        }

        if ($acc->emit_with_order_date && $order->created_at) {
            $payload['data_emissao'] = $order->created_at->format('Y-m-d');
        }

        // Aplica overrides manuais (ex: nota fiscal em homologação)
        return array_merge($payload, $overrides);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function mapStatus(string $webmaniaStatus): \App\Enums\NfeStatus
    {
        return match (strtolower($webmaniaStatus)) {
            'aprovado', 'approved' => \App\Enums\NfeStatus::Approved,
            'reprovado', 'rejected' => \App\Enums\NfeStatus::Rejected,
            'cancelado', 'cancelled' => \App\Enums\NfeStatus::Cancelled,
            'contingencia', 'contingency' => \App\Enums\NfeStatus::Contingency,
            'processando', 'processing' => \App\Enums\NfeStatus::Processing,
            default => \App\Enums\NfeStatus::Pending,
        };
    }

    // ─── Persistência ────────────────────────────────────────────────────────

    private function persistInvoice(Order $order, array $result, array $payload): Invoice
    {
        return Invoice::updateOrCreate(
            ['external_id' => $result['uuid'] ?? null, 'order_id' => $order->id],
            [
                'company_id'       => $order->company_id,
                'number'           => $result['numero'] ?? null,
                'series'           => $result['serie'] ?? null,
                'access_key'       => $result['chave'] ?? null,
                'protocol'         => $result['protocolo'] ?? null,
                'status'           => $this->mapStatus($result['status'] ?? 'aprovado'),
                'type'             => 'nfe',
                'customer_name'    => $order->customer_name,
                'customer_document'=> $order->customer_document,
                'customer_address' => $order->shipping_address,
                'total_products'   => $order->subtotal,
                'total_shipping'   => $order->shipping_cost,
                'total_discount'   => $order->discount,
                'total'            => $order->total,
                'nature_operation' => $payload['natureza_operacao'] ?? 'Venda',
                'pdf_url'          => $result['danfe'] ?? null,
                'xml_url'          => $result['xml'] ?? null,
                'external_id'      => $result['uuid'] ?? null,
                'meta'             => $result,
            ]
        );
    }
}
