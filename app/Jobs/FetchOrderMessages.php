<?php

namespace App\Jobs;

use App\Models\MarketplaceAccount;
use App\Models\Order;
use App\Models\OrderMessage;
use App\Services\Marketplaces\MercadoLivreService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FetchOrderMessages implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public readonly int $accountId,
        public readonly int $orderId,
    ) {}

    public function handle(): void
    {
        $account = MarketplaceAccount::find($this->accountId);
        $order   = Order::find($this->orderId);

        if (! $account || ! $order) {
            return;
        }

        $packId   = $order->meta['pack_id'] ?? $order->external_id;
        $sellerId = $account->shop_id;

        if (! $packId || ! $sellerId) {
            return;
        }

        try {
            $service  = new MercadoLivreService($account);
            $response = $service->getMessages((string) $packId);

            foreach ($response['messages'] ?? [] as $msg) {
                $messageId = $msg['id'] ?? null;
                if (! $messageId) {
                    continue;
                }

                $fromId    = $msg['from']['user_id'] ?? null;
                $direction = (string) $fromId === (string) $sellerId ? 'sent' : 'received';
                $text      = is_array($msg['text'] ?? null) ? ($msg['text']['plain'] ?? '') : ($msg['text'] ?? '');

                OrderMessage::updateOrCreate(
                    ['external_id' => $messageId],
                    [
                        'order_id'          => $order->id,
                        'direction'         => $direction,
                        'sender_user_id'    => (string) $fromId,
                        'text'              => $text,
                        'status'            => $msg['status'] ?? 'available',
                        'moderation_status' => $msg['message_moderation']['status'] ?? 'non_moderated',
                        'message_date'      => $msg['message_date'] ?? null,
                        'attachments'       => $msg['message_attachments'] ?? null,
                        'is_read'           => ! empty($msg['message_date']['read']),
                    ]
                );
            }

        } catch (\Throwable $e) {
            Log::warning("FetchOrderMessages: erro pack#{$packId}: " . $e->getMessage());
            throw $e;
        }
    }
}
