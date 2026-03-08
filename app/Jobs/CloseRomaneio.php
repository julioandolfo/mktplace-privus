<?php

namespace App\Jobs;

use App\Enums\OrderStatus;
use App\Enums\PipelineStatus;
use App\Models\MarketplaceAccount;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Romaneio;
use App\Services\Marketplaces\WooCommerceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Facades\LogActivity;

class CloseRomaneio implements ShouldQueue
{
    use Queueable, InteractsWithQueue;

    public function __construct(
        public int $romaneioId,
        public int $closedBy
    ) {}

    public function handle(): void
    {
        $romaneio = Romaneio::with(['items.order.items', 'items.order.marketplaceAccount'])
            ->findOrFail($this->romaneioId);

        if ($romaneio->isClosed()) {
            return;
        }

        DB::transaction(function () use ($romaneio) {
            foreach ($romaneio->items as $romaneioItem) {
                $order = $romaneioItem->order;

                // Atualiza shipped_quantity por item conforme items_detail
                if (! empty($romaneioItem->items_detail)) {
                    foreach ($romaneioItem->items_detail as $detail) {
                        if (empty($detail['order_item_id']) || empty($detail['quantity'])) {
                            continue;
                        }

                        $orderItem = OrderItem::find($detail['order_item_id']);
                        if ($orderItem && $orderItem->order_id === $order->id) {
                            $orderItem->increment('shipped_quantity', (int) $detail['quantity']);
                        }
                    }
                } else {
                    // Sem items_detail: marca todos os itens como enviados
                    foreach ($order->items as $item) {
                        $item->update(['shipped_quantity' => $item->quantity]);
                    }
                }

                // Recalcula pipeline_status
                $order->recalculatePipelineStatus();

                // Marca shipped_at e status se ainda não tiver
                if (! $order->shipped_at) {
                    $order->update([
                        'status'     => OrderStatus::Shipped,
                        'shipped_at' => now(),
                    ]);
                }

                // Log de atividade
                activity()
                    ->performedOn($order)
                    ->causedBy($this->closedBy)
                    ->withProperties(['romaneio_id' => $romaneio->id])
                    ->log("Pedido despachado via Romaneio #{$romaneio->name}");

                // WooCommerce: atualiza status se tiver conta configurada
                $account = $order->marketplaceAccount;
                if ($account && $account->marketplace_type->value === 'woocommerce') {
                    $this->updateWooCommerce($order, $account);
                }
            }

            // Fecha o romaneio
            $romaneio->update([
                'status'    => 'closed',
                'closed_at' => now(),
                'closed_by' => $this->closedBy,
            ]);
        });

        Log::info("Romaneio #{$romaneio->id} fechado com sucesso.", [
            'orders' => $romaneio->items->pluck('order_id'),
        ]);
    }

    private function updateWooCommerce(Order $order, MarketplaceAccount $account): void
    {
        try {
            $settings     = $account->settings ?? [];
            $shippedStatus = $settings['woo_shipped_status'] ?? null;

            if (! $shippedStatus || ! $order->external_id) {
                return;
            }

            $service = new WooCommerceService($account);
            $service->markShipped($order->external_id, $order->tracking_code ?? '', '');
        } catch (\Throwable $e) {
            Log::error("Erro ao atualizar WooCommerce para pedido {$order->id}: " . $e->getMessage());
        }
    }
}
