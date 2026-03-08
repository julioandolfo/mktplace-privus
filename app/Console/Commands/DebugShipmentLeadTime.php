<?php

namespace App\Console\Commands;

use App\Enums\MarketplaceType;
use App\Models\MarketplaceAccount;
use App\Models\Order;
use App\Services\Marketplaces\MercadoLivreService;
use Illuminate\Console\Command;

class DebugShipmentLeadTime extends Command
{
    protected $signature = 'debug:shipment-lead-time {--order= : ID interno do pedido} {--account= : ID da conta marketplace}';
    protected $description = 'Testa o endpoint /shipments/{id}/lead_time para diagnosticar o prazo de despacho';

    public function handle(): int
    {
        $orderId   = $this->option('order');
        $accountId = $this->option('account');

        if ($orderId) {
            return $this->debugOrder($orderId);
        }

        if ($accountId) {
            return $this->debugAccount($accountId);
        }

        $this->error('Informe --order=ID ou --account=ID');
        return self::FAILURE;
    }

    private function debugOrder(string $orderId): int
    {
        $order = Order::find($orderId);
        if (! $order) {
            $this->error("Pedido #{$orderId} não encontrado.");
            return self::FAILURE;
        }

        $meta       = $order->meta ?? [];
        $shippingId = $meta['ml_shipping_id'] ?? null;

        $this->info("Pedido: {$order->order_number}");
        $this->info("Status: {$order->status->value}");
        $this->info("ML Shipping ID: " . ($shippingId ?: 'N/A'));
        $this->info("ml_shipping_deadline (salvo): " . ($meta['ml_shipping_deadline'] ?? 'NÃO DEFINIDO'));
        $this->info("ml_estimated_delivery (salvo): " . ($meta['ml_estimated_delivery'] ?? 'NÃO DEFINIDO'));

        if (! $shippingId) {
            $this->warn('Sem shipping_id — não é possível consultar lead_time.');
            return self::SUCCESS;
        }

        $account = $order->marketplaceAccount;
        if (! $account) {
            $this->error('Sem conta marketplace vinculada.');
            return self::FAILURE;
        }

        return $this->callLeadTime($account, $shippingId, $order);
    }

    private function debugAccount(string $accountId): int
    {
        $account = MarketplaceAccount::find($accountId);
        if (! $account) {
            $this->error("Conta #{$accountId} não encontrada.");
            return self::FAILURE;
        }

        $orders = Order::where('marketplace_account_id', $account->id)
            ->whereNotNull('meta')
            ->latest('id')
            ->take(3)
            ->get();

        if ($orders->isEmpty()) {
            $this->warn('Nenhum pedido encontrado para essa conta.');
            return self::FAILURE;
        }

        foreach ($orders as $order) {
            $meta       = $order->meta ?? [];
            $shippingId = $meta['ml_shipping_id'] ?? null;

            $this->newLine();
            $this->info("═══ Pedido: {$order->order_number} (status: {$order->status->value}) ═══");
            $this->info("ML Shipping ID: " . ($shippingId ?: 'N/A'));
            $this->info("paid_at: " . ($order->paid_at?->format('d/m/Y H:i') ?? 'N/A'));

            if ($shippingId) {
                $this->callLeadTime($account, $shippingId, $order);
            } else {
                $this->warn('  Sem shipping_id.');
            }
        }

        return self::SUCCESS;
    }

    private function callLeadTime(MarketplaceAccount $account, string $shippingId, ?Order $order = null): int
    {
        $service = new MercadoLivreService($account);

        $this->line("  Chamando GET /shipments/{$shippingId}/lead_time ...");

        $leadTime = $service->getShippingLeadTime($shippingId);

        if (empty($leadTime)) {
            $this->error('  Resposta vazia ou erro. Verifique o log (storage/logs/laravel.log).');
            return self::FAILURE;
        }

        $this->info('  Keys retornadas: ' . implode(', ', array_keys($leadTime)));

        // Show all relevant deadline fields
        $fields = [
            'estimated_handling_limit' => $leadTime['estimated_handling_limit']['date'] ?? null,
            'estimated_schedule_limit' => $leadTime['estimated_schedule_limit']['date'] ?? null,
            'estimated_delivery_time.handling (horas)' => $leadTime['estimated_delivery_time']['handling'] ?? null,
            'estimated_delivery_time.date' => $leadTime['estimated_delivery_time']['date'] ?? null,
            'estimated_delivery_limit' => $leadTime['estimated_delivery_limit']['date'] ?? null,
            'buffering' => $leadTime['buffering']['date'] ?? null,
        ];

        foreach ($fields as $name => $value) {
            $icon = $value !== null ? '✓' : '✗';
            $this->line("  {$icon} {$name} = " . ($value ?? 'null'));
        }

        // Test the extraction method
        $paidAt = $order?->paid_at;
        $extracted = MercadoLivreService::extractDispatchDeadline($leadTime, $paidAt);
        $this->newLine();
        if ($extracted) {
            $formatted = \Carbon\Carbon::parse($extracted)->format('d/m/Y H:i');
            $this->info("  >>> PRAZO EXTRAÍDO: {$formatted}");
            $this->info("  >>> Valor bruto: {$extracted}");
        } else {
            $this->error('  >>> Não foi possível extrair prazo de despacho.');
        }

        if ($paidAt) {
            $this->line("  paid_at do pedido: {$paidAt->format('d/m/Y H:i')}");
        }

        return self::SUCCESS;
    }
}
