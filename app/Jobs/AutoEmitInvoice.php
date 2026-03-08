<?php

namespace App\Jobs;

use App\Enums\NfeStatus;
use App\Models\Order;
use App\Services\WebmaniaService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Dispara automaticamente a emissão de NF-e para um pedido,
 * conforme o trigger definido em webmania_accounts.settings.auto_emit_trigger.
 *
 * Triggers suportados:
 *   - 'on_payment'    → acionado quando payment_status muda para paid
 *   - 'on_ready'      → acionado quando pipeline_status = ready_to_ship
 *   - 'on_shipped'    → acionado quando status = shipped
 */
class AutoEmitInvoice implements ShouldQueue
{
    use Queueable, InteractsWithQueue;

    public int $tries   = 2;
    public int $backoff = 30;

    public function __construct(public int $orderId) {}

    public function handle(): void
    {
        $order = Order::with(['marketplaceAccount.webmaniaAccount', 'items.product', 'customer', 'company'])->find($this->orderId);

        if (! $order) {
            return;
        }

        $webmaniaAccount = $order->marketplaceAccount?->webmaniaAccount;

        if (! $webmaniaAccount || ! $webmaniaAccount->is_active) {
            return;
        }

        // Verifica se já existe NF-e aprovada para este pedido
        $existingApproved = $order->invoices()
            ->where('status', NfeStatus::Approved->value)
            ->exists();

        if ($existingApproved) {
            Log::info("AutoEmitInvoice: pedido #{$order->id} já possui NF-e aprovada, ignorando.");
            return;
        }

        try {
            $service = new WebmaniaService($webmaniaAccount);
            $service->emit($order);

            Log::info("AutoEmitInvoice: NF-e emitida automaticamente para pedido #{$order->id}.");
        } catch (\Throwable $e) {
            Log::error("AutoEmitInvoice: erro ao emitir NF-e para pedido #{$order->id}: " . $e->getMessage());

            // Envia e-mail de erro se configurado
            $errorEmail = $webmaniaAccount->settings['error_email'] ?? null;
            if ($errorEmail) {
                \Illuminate\Support\Facades\Mail::raw(
                    "Erro ao emitir NF-e automática para pedido #{$order->order_number}:\n{$e->getMessage()}",
                    fn ($msg) => $msg->to($errorEmail)->subject('Erro NF-e Automática — ' . $order->order_number)
                );
            }

            throw $e;
        }
    }
}
