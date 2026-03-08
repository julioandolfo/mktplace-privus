<?php

namespace App\Observers;

use App\Models\OrderItem;

class OrderItemObserver
{
    /**
     * Recalcula o pipeline_status do pedido sempre que um item é salvo.
     * Isso garante que o status interno reflita o estado real de produção e despacho.
     */
    public function saved(OrderItem $item): void
    {
        $item->order?->recalculatePipelineStatus();
    }

    public function deleted(OrderItem $item): void
    {
        $item->order?->recalculatePipelineStatus();
    }
}
