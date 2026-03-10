<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Marketplaces\MercadoLivreService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ShippingController extends Controller
{
    /**
     * Busca e redireciona para a etiqueta oficial dos Correios via API do ML.
     * Abre o PDF da etiqueta em nova aba.
     */
    public function mlLabel(Order $order)
    {
        abort_unless($order->company_id === Auth::user()->company_id, 403);

        $account = $order->marketplaceAccount;
        abort_unless($account, 404, 'Conta de marketplace não encontrada.');

        $shippingId = $order->meta['ml_shipping_id'] ?? null;
        abort_unless($shippingId, 404, 'Este pedido não possui ID de envio do Mercado Livre.');

        try {
            $service  = new MercadoLivreService($account);
            $labelUrl = $service->getShipmentLabels([$shippingId]);

            // Se for URL HTTP, redireciona diretamente
            if (filter_var($labelUrl, FILTER_VALIDATE_URL)) {
                return redirect($labelUrl);
            }

            // Se for base64 PDF, retorna como response
            if (str_starts_with($labelUrl, 'data:application/pdf;base64,')) {
                $pdf = base64_decode(substr($labelUrl, strpos($labelUrl, ',') + 1));
                return response($pdf, 200, [
                    'Content-Type'        => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="etiqueta-' . $order->order_number . '.pdf"',
                ]);
            }

            return back()->with('error', 'Não foi possível obter a etiqueta.');
        } catch (\Throwable $e) {
            Log::error("mlLabel order #{$order->id}: " . $e->getMessage());
            return back()->with('error', 'Erro ao buscar etiqueta ML: ' . $e->getMessage());
        }
    }

    /**
     * Gera etiquetas em lote para múltiplos pedidos ML.
     * Recebe JSON body: {"order_ids": [1, 2, 3]}
     */
    public function mlLabelsBatch(Request $request)
    {
        $validated = $request->validate([
            'order_ids'   => 'required|array|min:1|max:50',
            'order_ids.*' => 'integer',
        ]);

        $orders = Order::where('company_id', Auth::user()->company_id)
            ->whereIn('id', $validated['order_ids'])
            ->whereNotNull('marketplace_account_id')
            ->with('marketplaceAccount')
            ->get();

        if ($orders->isEmpty()) {
            return response()->json(['error' => 'Nenhum pedido encontrado.'], 404);
        }

        // Agrupa por conta ML
        $byAccount = $orders->groupBy('marketplace_account_id');
        $results   = [];

        foreach ($byAccount as $accountId => $accountOrders) {
            $account = $accountOrders->first()->marketplaceAccount;

            try {
                $shipmentIds = $accountOrders
                    ->pluck('meta')
                    ->map(fn ($m) => $m['ml_shipping_id'] ?? null)
                    ->filter()
                    ->values()
                    ->toArray();

                if (empty($shipmentIds)) {
                    continue;
                }

                $service  = new MercadoLivreService($account);
                $labelUrl = $service->getShipmentLabels($shipmentIds);

                $results[] = [
                    'account_id'   => $accountId,
                    'account_name' => $account->account_name,
                    'label_url'    => $labelUrl,
                    'count'        => count($shipmentIds),
                ];
            } catch (\Throwable $e) {
                $results[] = [
                    'account_id' => $accountId,
                    'error'      => $e->getMessage(),
                ];
            }
        }

        return response()->json(['labels' => $results]);
    }

    // ─── Melhor Envios ────────────────────────────────────────────────────────

    /**
     * Cotacao de frete via Melhor Envios.
     */
    public function quote(Request $request, Order $order)
    {
        abort_unless($order->company_id === Auth::user()->company_id, 403);

        $meAccount = $order->marketplaceAccount?->melhorEnviosAccount;
        if (! $meAccount) {
            return response()->json(['error' => 'Nenhuma conta Melhor Envios vinculada a este canal.'], 422);
        }

        $validated = $request->validate([
            'weight' => 'nullable|numeric|min:0.01',
            'width'  => 'nullable|numeric|min:1',
            'height' => 'nullable|numeric|min:1',
            'length' => 'nullable|numeric|min:1',
        ]);

        try {
            $service = new \App\Services\MelhorEnviosService($meAccount);
            $results = $service->quoteForOrder($order, array_filter($validated));

            $quotes = collect($results)
                ->filter(fn ($q) => empty($q['error']) && isset($q['price']))
                ->sortBy('price')
                ->values()
                ->toArray();

            return response()->json(['quotes' => $quotes]);
        } catch (\Throwable $e) {
            Log::error("ME quote order #{$order->id}: " . $e->getMessage());
            return response()->json(['error' => 'Erro na cotacao: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Compra de etiqueta Melhor Envios para o pedido.
     */
    public function purchase(Request $request, Order $order)
    {
        abort_unless($order->company_id === Auth::user()->company_id, 403);

        $meAccount = $order->marketplaceAccount?->melhorEnviosAccount;
        if (! $meAccount) {
            return response()->json(['error' => 'Nenhuma conta Melhor Envios vinculada.'], 422);
        }

        $validated = $request->validate([
            'service_id' => 'required|integer',
            'price'      => 'required|numeric|min:0',
            'company'    => 'nullable|array',
            'name'       => 'nullable|string',
            'weight'     => 'nullable|numeric|min:0.01',
            'width'      => 'nullable|numeric|min:1',
            'height'     => 'nullable|numeric|min:1',
            'length'     => 'nullable|numeric|min:1',
        ]);

        try {
            $service = new \App\Services\MelhorEnviosService($meAccount);

            $label = $service->purchaseLabel($order, [
                'id'      => $validated['service_id'],
                'price'   => $validated['price'],
                'company' => $validated['company'] ?? [],
                'name'    => $validated['name'] ?? '',
            ], array_filter([
                'weight' => $validated['weight'] ?? null,
                'width'  => $validated['width'] ?? null,
                'height' => $validated['height'] ?? null,
                'length' => $validated['length'] ?? null,
            ]));

            if ($label->tracking_code) {
                $order->update(['tracking_code' => $label->tracking_code]);
            }

            return response()->json([
                'success' => true,
                'label'   => [
                    'id'            => $label->id,
                    'carrier'       => $label->carrier,
                    'service'       => $label->service,
                    'tracking_code' => $label->tracking_code,
                    'cost'          => $label->cost,
                    'label_url'     => $label->label_url,
                    'status'        => $label->status,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error("ME purchase order #{$order->id}: " . $e->getMessage());
            return response()->json(['error' => 'Erro na compra: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Webhook do Melhor Envios (rastreio atualizado).
     */
    public function webhook(Request $request)
    {
        Log::info('MelhorEnvios webhook received', $request->all());

        $data = $request->all();
        if (isset($data['id'])) {
            $label = \App\Models\ShipmentLabel::where('me_label_id', (string) $data['id'])->first();
            if ($label) {
                if (! empty($data['tracking'])) {
                    $label->update(['tracking_code' => $data['tracking']]);
                    $label->order?->update(['tracking_code' => $data['tracking']]);
                }
                if (($data['status'] ?? '') === 'delivered') {
                    $label->order?->update(['delivered_at' => now()]);
                }
                $meta = $label->meta ?? [];
                $meta['webhook_events'][] = [
                    'status'    => $data['status'] ?? 'unknown',
                    'timestamp' => now()->toIso8601String(),
                    'data'      => $data,
                ];
                $label->update(['meta' => $meta]);
            }
        }

        return response()->json(['received' => true]);
    }

    // ─── NF-e (Webmaniabr / Faturador ML) ────────────────────────────────────

    /**
     * Emite ou pré-visualiza uma NF-e para o pedido.
     * Chamado via POST do modal em orders/show.
     */
    public function emitInvoice(Request $request, Order $order)
    {
        abort_unless($order->company_id === Auth::user()->company_id, 403);

        $validated = $request->validate([
            'action'             => 'required|in:emit,preview',
            'nature_operation'   => 'nullable|string|max:60',
            'shipping_modality'  => 'nullable|string',
            'info_fisco'         => 'nullable|string|max:2000',
            'info_consumer'      => 'nullable|string|max:2000',
            'homologation'       => 'nullable|boolean',
        ]);

        $account = $order->marketplaceAccount;
        abort_unless($account?->webmania_account_id, 422, 'Nenhuma conta Webmaniabr vinculada a este canal.');

        $order->load([
            'items.product',
            'company',
            'marketplaceAccount.webmaniaAccount',
        ]);

        $webmaniaAccount = $account->webmaniaAccount;
        $service         = new \App\Services\WebmaniaService($webmaniaAccount);

        $overrides = [];

        if (! empty($validated['nature_operation'])) {
            $overrides['natureza_operacao'] = $validated['nature_operation'];
        }
        if (! empty($validated['info_fisco'])) {
            $overrides['informacoes_adicionais']['fisco'] = $validated['info_fisco'];
        }
        if (! empty($validated['info_consumer'])) {
            $overrides['informacoes_adicionais']['contribuinte'] = $validated['info_consumer'];
        }
        if (! empty($validated['homologation'])) {
            $overrides['homologacao'] = true;
        }

        try {
            if ($validated['action'] === 'preview') {
                $result = $service->preview($order, $overrides);
                return back()->with('success', 'Pré-visualização gerada. PDF: ' . ($result['danfe'] ?? 'N/A'));
            }

            $result = $service->emit($order, $overrides);
            return back()->with('success', "NF-e nº {$result['numero']} emitida com sucesso.");
        } catch (\Throwable $e) {
            Log::error("emitInvoice order #{$order->id}: " . $e->getMessage());
            return back()->with('error', 'Erro na emissão: ' . $e->getMessage());
        }
    }
}
