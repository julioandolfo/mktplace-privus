<?php

namespace App\Observers;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PipelineStatus;
use App\Jobs\AssignOrderToDesigner;
use App\Jobs\AutoEmitInvoice;
use App\Models\Order;
use App\Models\OrderTimeline;
use App\Services\PurchaseService;

class OrderObserver
{
    public function created(Order $order): void
    {
        OrderTimeline::log(
            $order,
            'order_created',
            'Pedido recebido',
            "Pedido #{$order->order_number} criado no sistema.",
            ['status' => $order->status?->value, 'total' => $order->total],
            null,
        );

        // Gera solicitação de compra automática se algum item exige compra
        try {
            app(PurchaseService::class)->generateFromOrder($order);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Erro ao gerar compra para pedido #{$order->order_number}: {$e->getMessage()}");
        }
    }

    public function updated(Order $order): void
    {
        $this->logStatusChanges($order);
        $this->checkDesignAssignment($order);
        $this->checkAutoEmitTrigger($order);
    }

    // ─── Timeline Logging ────────────────────────────────────────────────────

    private function logStatusChanges(Order $order): void
    {
        // Pagamento confirmado
        if ($order->wasChanged('payment_status')
            && $order->payment_status === PaymentStatus::Paid
            && $order->getOriginal('payment_status') !== PaymentStatus::Paid->value
        ) {
            OrderTimeline::log(
                $order,
                'payment_confirmed',
                'Pagamento confirmado',
                'Pagamento aprovado. Pedido liberado para processamento.',
                ['payment_status' => $order->payment_status->value, 'total' => $order->total],
            );
        }

        // Pipeline status changes
        if ($order->wasChanged('pipeline_status')) {
            $old = $order->getOriginal('pipeline_status');
            $new = $order->pipeline_status?->value;

            $events = [
                'awaiting_production' => ['production_started',   'Enviado para produção',          'O pedido foi encaminhado ao módulo de produção.'],
                'in_production'       => ['production_in_progress','Produção em andamento',          'O pedido está sendo produzido.'],
                'ready_to_ship'       => ['ready_to_ship',         'Pronto para envio',              'O pedido foi liberado para expedição.'],
                'packing'             => ['packing_started',       'Embalagem iniciada',             'O pedido está sendo embalado.'],
                'packed'              => ['packing_completed',     'Embalagem concluída',            'O pedido foi embalado e está aguardando coleta.'],
                'partially_shipped'   => ['partial_shipped',       'Envio parcial realizado',        'Parte dos itens foi despachada.'],
                'shipped'             => ['shipped',               'Pedido despachado',              'Todos os itens foram enviados.'],
            ];

            if (isset($events[$new]) && $old !== $new) {
                [$event, $title, $desc] = $events[$new];
                OrderTimeline::log($order, $event, $title, $desc, ['from' => $old, 'to' => $new]);
            }
        }

        // Enviado com rastreio
        if ($order->wasChanged('tracking_code') && $order->tracking_code) {
            OrderTimeline::log(
                $order,
                'tracking_updated',
                'Código de rastreio atualizado',
                "Código de rastreio: {$order->tracking_code}",
                ['tracking_code' => $order->tracking_code, 'carrier' => $order->shipping_method],
            );
        }
    }

    // ─── Design Assignment ────────────────────────────────────────────────────

    private function checkDesignAssignment(Order $order): void
    {
        // Quando entra em awaiting_production E ainda não tem assignment → dispara round-robin
        if (! $order->wasChanged('pipeline_status')) {
            return;
        }

        $newStatus = $order->pipeline_status?->value;

        if ($newStatus === 'awaiting_production') {
            // Verifica se já tem assignment ativo (não concluído)
            $hasActive = $order->designAssignment()->whereIn('status', ['pending', 'in_progress', 'revision'])->exists();

            if (! $hasActive) {
                AssignOrderToDesigner::dispatch($order->id)->delay(now()->addSeconds(2));
            }
        }
    }

    // ─── NF-e Auto-emit ───────────────────────────────────────────────────────

    private function checkAutoEmitTrigger(Order $order): void
    {
        if (! $order->relationLoaded('marketplaceAccount')) {
            $order->load('marketplaceAccount.webmaniaAccount');
        }

        $webmaniaAccount = $order->marketplaceAccount?->webmaniaAccount;

        if (! $webmaniaAccount || ! $webmaniaAccount->is_active) {
            return;
        }

        $trigger = $webmaniaAccount->settings['auto_emit_trigger'] ?? null;

        if (! $trigger || $trigger === 'manual') {
            return;
        }

        $shouldDispatch = match ($trigger) {
            'on_payment' => $order->wasChanged('payment_status')
                && $order->payment_status === PaymentStatus::Paid
                && $order->getOriginal('payment_status') !== PaymentStatus::Paid->value,

            'on_ready' => $order->wasChanged('pipeline_status')
                && $order->pipeline_status === PipelineStatus::ReadyToShip
                && ! in_array($order->getOriginal('pipeline_status'), [
                    PipelineStatus::ReadyToShip->value,
                    PipelineStatus::Packing->value,
                    PipelineStatus::Packed->value,
                    PipelineStatus::PartiallyShipped->value,
                    PipelineStatus::Shipped->value,
                ]),

            'on_shipped' => $order->wasChanged('status')
                && $order->status === OrderStatus::Shipped
                && $order->getOriginal('status') !== OrderStatus::Shipped->value,

            default => false,
        };

        if ($shouldDispatch) {
            AutoEmitInvoice::dispatch($order->id)->delay(now()->addSeconds(3));
        }
    }
}
