<?php

namespace App\Livewire\Orders;

use App\Models\Order;
use App\Models\OrderMessage;
use App\Services\Marketplaces\MercadoLivreService;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class OrderMessages extends Component
{
    public Order $order;
    public string $newMessage = '';
    public bool $sending = false;
    public ?string $sendError = null;
    public ?string $sendSuccess = null;

    public function mount(Order $order): void
    {
        $this->order = $order;
    }

    public function getMessagesProperty()
    {
        return $this->order->messages()->get();
    }

    public function getUnreadCountProperty(): int
    {
        return $this->order->messages()->where('direction', 'received')->where('is_read', false)->count();
    }

    public function getCanReplyProperty(): bool
    {
        if (! $this->order->marketplaceAccount) {
            return false;
        }

        $packId = $this->order->meta['pack_id'] ?? $this->order->external_id;
        if (! $packId) {
            return false;
        }

        // Cannot reply to cancelled orders
        if (in_array($this->order->status->value, ['cancelled', 'returned'])) {
            return false;
        }

        return true;
    }

    public function refreshMessages(): void
    {
        $account = $this->order->marketplaceAccount;
        if (! $account || ! $account->credentials) {
            return;
        }

        $packId   = $this->order->meta['pack_id'] ?? $this->order->external_id;
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
                $text      = is_array($msg['text'] ?? null)
                    ? ($msg['text']['plain'] ?? '')
                    : ($msg['text'] ?? '');

                OrderMessage::updateOrCreate(
                    ['external_id' => $messageId],
                    [
                        'order_id'          => $this->order->id,
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
            Log::warning("OrderMessages::refreshMessages error: " . $e->getMessage());
        }
    }

    public function sendMessage(): void
    {
        $this->sendError   = null;
        $this->sendSuccess = null;

        $this->validate([
            'newMessage' => 'required|string|max:350',
        ], [
            'newMessage.required' => 'Digite uma mensagem.',
            'newMessage.max'      => 'Máximo 350 caracteres.',
        ]);

        $account = $this->order->marketplaceAccount;
        if (! $account || ! $account->credentials) {
            $this->sendError = 'Conta do marketplace não encontrada.';
            return;
        }

        $packId = $this->order->meta['pack_id'] ?? $this->order->external_id;
        if (! $packId) {
            $this->sendError = 'Pack ID não encontrado para este pedido.';
            return;
        }

        $this->sending = true;

        try {
            $service  = new MercadoLivreService($account);
            $response = $service->sendMessage((string) $packId, $this->newMessage);

            // Save the sent message locally
            $messageId = $response['id'] ?? ('sent_' . now()->timestamp);

            OrderMessage::updateOrCreate(
                ['external_id' => $messageId],
                [
                    'order_id'          => $this->order->id,
                    'direction'         => 'sent',
                    'sender_user_id'    => (string) $account->shop_id,
                    'text'              => $this->newMessage,
                    'status'            => 'available',
                    'moderation_status' => 'non_moderated',
                    'message_date'      => ['created' => now()->toIso8601String()],
                    'is_read'           => true,
                ]
            );

            $this->newMessage  = '';
            $this->sendSuccess = 'Mensagem enviada com sucesso.';

        } catch (\Throwable $e) {
            Log::error("OrderMessages::sendMessage error: " . $e->getMessage());
            $this->sendError = 'Erro ao enviar mensagem: ' . $e->getMessage();
        } finally {
            $this->sending = false;
        }
    }

    public function render()
    {
        return view('livewire.orders.order-messages', [
            'messages'    => $this->messages,
            'unreadCount' => $this->unreadCount,
            'canReply'    => $this->canReply,
        ]);
    }
}
