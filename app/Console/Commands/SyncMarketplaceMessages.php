<?php

namespace App\Console\Commands;

use App\Enums\MarketplaceType;
use App\Models\MarketplaceAccount;
use App\Models\Order;
use App\Models\OrderMessage;
use App\Services\Marketplaces\MercadoLivreService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncMarketplaceMessages extends Command
{
    protected $signature = 'marketplace:sync-messages
                            {--account= : ID de conta específica (padrão: todas ativas)}
                            {--days=30  : Quantos dias para trás buscar pedidos com mensagens}';

    protected $description = 'Sincroniza mensagens pós-venda do Mercado Livre para os pedidos';

    public function handle(): int
    {
        $accounts = $this->resolveAccounts();

        if ($accounts->isEmpty()) {
            $this->warn('Nenhuma conta ativa do Mercado Livre encontrada.');
            return self::SUCCESS;
        }

        foreach ($accounts as $account) {
            $this->syncAccount($account);
        }

        return self::SUCCESS;
    }

    private function resolveAccounts()
    {
        $query = MarketplaceAccount::active()
            ->where('marketplace_type', MarketplaceType::MercadoLivre);

        if ($id = $this->option('account')) {
            $query->where('id', $id);
        }

        return $query->get();
    }

    private function syncAccount(MarketplaceAccount $account): void
    {
        $this->info("Sincronizando mensagens: [{$account->id}] {$account->account_name}");

        $days    = (int) $this->option('days');
        $service = new MercadoLivreService($account);
        $synced  = 0;
        $errors  = 0;

        // Find orders from this account that may have messages (open orders, last N days)
        $orders = Order::where('marketplace_account_id', $account->id)
            ->where('created_at', '>=', now()->subDays($days))
            ->whereNotNull('external_id')
            ->whereNotIn('status', ['delivered', 'cancelled', 'returned'])
            ->get();

        $this->info("  Pedidos a verificar: {$orders->count()}");

        foreach ($orders as $order) {
            try {
                $packId = $order->meta['pack_id'] ?? $order->external_id;

                if (! $packId) {
                    continue;
                }

                $response = $service->getMessages((string) $packId);
                $messages = $response['messages'] ?? [];

                if (empty($messages)) {
                    continue;
                }

                $sellerId = $account->shop_id;

                foreach ($messages as $msg) {
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

                    $synced++;
                }

            } catch (\Throwable $e) {
                $errors++;
                Log::warning("SyncMessages: erro no pedido #{$order->external_id}: " . $e->getMessage());
            }
        }

        activity('marketplace')
            ->performedOn($account)
            ->withProperties(['synced' => $synced, 'errors' => $errors])
            ->log('Mensagens sincronizadas');

        $this->info("  ✓ {$synced} mensagens sincronizadas" . ($errors ? ", {$errors} erros" : ''));
    }
}
