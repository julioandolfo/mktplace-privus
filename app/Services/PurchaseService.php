<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderTimeline;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;

class PurchaseService
{
    /**
     * Gera solicitação de compra automaticamente para um pedido.
     * Somente itens cujo produto tenha requires_purchase = true.
     */
    public function generateFromOrder(Order $order): ?PurchaseRequest
    {
        $order->loadMissing('items.product');

        $itemsNeedPurchase = $order->items->filter(
            fn ($item) => $item->product?->requires_purchase
        );

        if ($itemsNeedPurchase->isEmpty()) {
            return null;
        }

        // Verificar se já existe solicitação para este pedido
        $existing = PurchaseRequest::where('order_id', $order->id)
            ->whereIn('status', ['pending', 'purchased'])
            ->first();

        if ($existing) {
            return $existing;
        }

        $pr = PurchaseRequest::create([
            'company_id' => $order->company_id,
            'order_id'   => $order->id,
            'status'     => 'pending',
            'title'      => "Compra — Pedido #{$order->order_number}",
        ]);

        foreach ($itemsNeedPurchase as $item) {
            PurchaseRequestItem::create([
                'purchase_request_id' => $pr->id,
                'product_id'          => $item->product_id,
                'order_item_id'       => $item->id,
                'description'         => $item->name ?? $item->product?->name ?? 'Produto',
                'quantity'            => $item->quantity,
                'unit_cost_cents'     => (int) (($item->product?->cost_price ?? 0) * 100),
                'status'              => 'pending',
            ]);
        }

        $pr->recalculateTotal();

        OrderTimeline::log(
            $order,
            'purchase_requested',
            "Solicitação de compra criada ({$itemsNeedPurchase->count()} item(ns))",
            null,
            ['purchase_request_id' => $pr->id]
        );

        return $pr;
    }

    /**
     * Marca solicitação como comprada.
     */
    public function markPurchased(PurchaseRequest $pr, int $supplierId, ?int $userId = null): void
    {
        $pr->update([
            'status'       => 'purchased',
            'supplier_id'  => $supplierId,
            'purchased_at' => now(),
            'purchased_by' => $userId,
        ]);

        $pr->items()->where('status', 'pending')->update(['status' => 'purchased']);

        if ($pr->order_id) {
            OrderTimeline::log(
                $pr->order,
                'purchase_completed',
                "Compra realizada — {$pr->supplier?->name}",
                null,
                [
                    'purchase_request_id' => $pr->id,
                    'supplier_id'         => $supplierId,
                    'supplier_name'       => $pr->supplier?->name,
                    'total_cost'          => $pr->total_cost_formatted,
                ]
            );
        }
    }

    /**
     * Cancela solicitação.
     */
    public function cancel(PurchaseRequest $pr): void
    {
        $pr->update(['status' => 'cancelled']);
        $pr->items()->where('status', 'pending')->update(['status' => 'cancelled']);
    }
}
