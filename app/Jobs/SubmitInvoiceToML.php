<?php

namespace App\Jobs;

use App\Enums\MarketplaceType;
use App\Models\Invoice;
use App\Services\Marketplaces\MercadoLivreService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Após a emissão de NF-e via Webmaniabr, submete a chave de acesso
 * ao Faturador ML (submitFiscalDocument) para fechar o loop fiscal no ML.
 *
 * Só executa para pedidos de contas Mercado Livre com ml_shipping_id.
 */
class SubmitInvoiceToML implements ShouldQueue
{
    use Queueable, InteractsWithQueue;

    public int $tries   = 3;
    public int $backoff = 60;

    public function __construct(public int $invoiceId) {}

    public function handle(): void
    {
        $invoice = Invoice::with(['order.marketplaceAccount'])->findOrFail($this->invoiceId);
        $order   = $invoice->order;
        $account = $order?->marketplaceAccount;

        // Somente para contas Mercado Livre
        if (! $account || $account->marketplace_type !== MarketplaceType::MercadoLivre) {
            return;
        }

        // Precisa de chave de acesso
        if (! $invoice->access_key) {
            Log::warning("SubmitInvoiceToML: invoice #{$invoice->id} sem access_key, abortando.");
            return;
        }

        $meta       = $order->meta ?? [];
        $mlOrderId  = $meta['ml_order_id'] ?? $order->external_id;
        $packId     = $meta['pack_id'] ?? null;

        if (! $mlOrderId) {
            Log::warning("SubmitInvoiceToML: pedido #{$order->id} sem external_id ML, abortando.");
            return;
        }

        try {
            $service = new MercadoLivreService($account);
            $result  = $service->submitFiscalDocument(
                (string) $mlOrderId,
                $invoice->access_key,
                $packId ? (string) $packId : null
            );

            // Registra o resultado no meta da invoice
            $invoiceMeta              = $invoice->meta ?? [];
            $invoiceMeta['ml_fiscal'] = $result;
            $invoice->update(['meta' => $invoiceMeta]);

            Log::info("SubmitInvoiceToML: NF-e #{$invoice->number} submetida ao ML para pedido #{$order->external_id}.", $result);
        } catch (\Throwable $e) {
            Log::error("SubmitInvoiceToML: erro ao submeter ao ML para invoice #{$invoice->id}: " . $e->getMessage());

            // Re-lança para triggerar retry
            throw $e;
        }
    }
}
