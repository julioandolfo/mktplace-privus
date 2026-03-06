<?php

namespace App\Jobs;

use App\Enums\MarketplaceType;
use App\Models\MarketplaceAccount;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessWebhookEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10;

    public function __construct(
        public readonly string $marketplaceType,
        public readonly array $payload,
    ) {}

    public function handle(): void
    {
        $topic  = $this->payload['topic'] ?? null;
        $userId = $this->payload['user_id'] ?? null;

        if (! $topic || ! $userId) {
            Log::debug('ProcessWebhookEvent: payload sem topic ou user_id.', $this->payload);
            return;
        }

        // Find the marketplace account by shop_id (ML user_id)
        $account = MarketplaceAccount::where('shop_id', (string) $userId)
            ->where('marketplace_type', MarketplaceType::MercadoLivre)
            ->active()
            ->first();

        if (! $account) {
            Log::warning("ProcessWebhookEvent: conta não encontrada para user_id={$userId}");
            return;
        }

        $resourceId = $this->extractResourceId($this->payload);

        match ($topic) {
            'orders_v2'        => $this->handleOrder($account, $resourceId),
            'payments'         => $this->handlePayment($account, $resourceId),
            'shipments'        => $this->handleShipment($account, $resourceId),
            'items'            => $this->handleItem($account, $resourceId),
            'messages'         => $this->handleMessage($account, $resourceId),
            default            => Log::debug("ProcessWebhookEvent: topic desconhecido [{$topic}]"),
        };
    }

    private function extractResourceId(array $payload): string
    {
        // ML sends: resource like "/orders/12345" or "/items/MLB123"
        $resource = $payload['resource'] ?? '';
        $parts    = explode('/', trim($resource, '/'));
        return (string) end($parts);
    }

    private function handleOrder(MarketplaceAccount $account, string $orderId): void
    {
        if (! $orderId) {
            return;
        }
        Log::info("Webhook orders_v2: sincronizando pedido #{$orderId}");
        SyncSingleOrder::dispatch($account->id, $orderId)->onQueue('high');
    }

    private function handlePayment(MarketplaceAccount $account, string $paymentId): void
    {
        // Payment webhooks have resourceId = paymentId, not orderId
        // We need to find the order by ml_payment_id
        $order = Order::where('marketplace_account_id', $account->id)
            ->whereRaw("meta->>'ml_payment_id' = ?", [$paymentId])
            ->first();

        if ($order) {
            Log::info("Webhook payments: sincronizando pedido #{$order->external_id} via payment #{$paymentId}");
            SyncSingleOrder::dispatch($account->id, $order->external_id)->onQueue('high');
        } else {
            Log::debug("Webhook payments: payment #{$paymentId} sem pedido local correspondente.");
        }
    }

    private function handleShipment(MarketplaceAccount $account, string $shipmentId): void
    {
        // Find order by shipping_id
        $order = Order::where('marketplace_account_id', $account->id)
            ->whereRaw("meta->>'ml_shipping_id' = ?", [$shipmentId])
            ->first();

        if ($order) {
            Log::info("Webhook shipments: sincronizando pedido #{$order->external_id} via shipment #{$shipmentId}");
            SyncSingleOrder::dispatch($account->id, $order->external_id)->onQueue('high');
        } else {
            Log::debug("Webhook shipments: shipment #{$shipmentId} sem pedido local correspondente.");
        }
    }

    private function handleItem(MarketplaceAccount $account, string $itemId): void
    {
        if (! $itemId) {
            return;
        }
        Log::info("Webhook items: sincronizando anúncio #{$itemId}");
        SyncSingleListing::dispatch($account->id, $itemId)->onQueue('default');
    }

    private function handleMessage(MarketplaceAccount $account, string $packId): void
    {
        // packId might come as /packs/123 or just 123
        $order = Order::where('marketplace_account_id', $account->id)
            ->where(function ($q) use ($packId) {
                $q->whereRaw("meta->>'pack_id' = ?", [$packId])
                  ->orWhere('external_id', $packId);
            })
            ->first();

        if ($order) {
            Log::info("Webhook messages: buscando mensagens pack #{$packId}");
            FetchOrderMessages::dispatch($account->id, $order->id)->onQueue('default');
        } else {
            Log::debug("Webhook messages: pack #{$packId} sem pedido local correspondente.");
        }
    }
}
