<?php

namespace App\Console\Commands;

use App\Enums\MarketplaceType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\MarketplaceAccount;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\Marketplaces\MercadoLivreService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncMarketplaceOrders extends Command
{
    protected $signature = 'marketplace:sync-orders
                            {--account= : ID de conta específica (padrão: todas ativas)}
                            {--days=1   : Quantos dias para trás sincronizar (quando sem last_synced_at)}';

    protected $description = 'Importa pedidos/vendas do Mercado Livre para o sistema';

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
        $this->info("Sincronizando pedidos: [{$account->id}] {$account->account_name}");

        $since = $account->last_synced_at
            ? $account->last_synced_at->subHour()
            : now()->subDays((int) $this->option('days'));

        $service = new MercadoLivreService($account);
        $synced  = 0;
        $errors  = 0;

        try {
            foreach ($service->getOrders($since) as $page) {
                foreach ($page as $mlOrder) {
                    try {
                        $this->upsertOrder($account, $mlOrder, $service);
                        $synced++;
                    } catch (\Throwable $e) {
                        $errors++;
                        Log::error("SyncOrders: erro no pedido ML#{$mlOrder['id']}: " . $e->getMessage());
                        $this->error("  Erro pedido #{$mlOrder['id']}: " . $e->getMessage());
                    }
                }
            }

            $account->update(['last_synced_at' => now()]);

            activity('marketplace')
                ->performedOn($account)
                ->withProperties(['synced' => $synced, 'errors' => $errors, 'since' => $since->toDateTimeString()])
                ->log('Pedidos sincronizados');

            $this->info("  ✓ {$synced} pedidos sincronizados" . ($errors ? ", {$errors} erros" : ''));

        } catch (\Throwable $e) {
            $account->update(['last_error' => $e->getMessage()]);
            Log::error("SyncOrders: falha na conta {$account->id}: " . $e->getMessage());
            $this->error("  Falha na conta {$account->account_name}: " . $e->getMessage());
        }
    }

    private function upsertOrder(MarketplaceAccount $account, array $ml, MercadoLivreService $service): void
    {
        $payment = $ml['payments'][0] ?? [];
        $buyer   = $ml['buyer'] ?? [];

        $orderStatus   = OrderStatus::from(MercadoLivreService::mapOrderStatus($ml['status'] ?? 'pending'));
        $paymentStatus = PaymentStatus::from(
            MercadoLivreService::mapPaymentStatus($payment['status'] ?? 'pending')
        );

        // Shipping / tracking
        $trackingCode = null;
        $shippingCost = 0;
        $shipment     = [];

        if (! empty($ml['shipping']['id'])) {
            $shipment     = $service->getShipping((string) $ml['shipping']['id']);
            $trackingCode = $shipment['tracking_number'] ?? null;
            $shippingCost = $shipment['shipping_option']['cost'] ?? 0;
        }

        $receiver        = $shipment['receiver_address'] ?? [];
        $shippingAddress = ! empty($receiver) ? [
            'street'  => ($receiver['street_name'] ?? '') . ' ' . ($receiver['street_number'] ?? ''),
            'city'    => $receiver['city']['name'] ?? '',
            'state'   => $receiver['state']['name'] ?? '',
            'zip'     => $receiver['zip_code'] ?? '',
            'country' => $receiver['country']['id'] ?? 'BR',
        ] : null;

        $total    = (float) ($payment['transaction_amount'] ?? 0);
        $subtotal = $total - $shippingCost;

        $customerName  = trim(($buyer['first_name'] ?? '') . ' ' . ($buyer['last_name'] ?? ''))
                         ?: ($buyer['nickname'] ?? 'Comprador ML');
        $customerEmail = ! empty($buyer['email']) ? $buyer['email'] : null;
        $mlUserId      = $buyer['id'] ?? null;

        DB::transaction(function () use (
            $account, $ml, $buyer, $orderStatus, $paymentStatus, $payment,
            $trackingCode, $shippingCost, $shippingAddress, $total, $subtotal,
            $customerName, $customerEmail, $mlUserId, $receiver
        ) {
            // ── Upsert Customer ──────────────────────────────────────────────
            $customer = $this->upsertCustomer(
                $account, $customerName, $customerEmail, $mlUserId, $buyer, $receiver
            );

            // ── Upsert Order ─────────────────────────────────────────────────
            $order = Order::updateOrCreate(
                [
                    'marketplace_account_id' => $account->id,
                    'external_id'            => (string) $ml['id'],
                ],
                [
                    'company_id'      => $account->company_id,
                    'customer_id'     => $customer?->id,
                    'status'          => $orderStatus,
                    'payment_status'  => $paymentStatus,
                    'payment_method'  => $payment['payment_type'] ?? null,
                    'customer_name'   => $customerName,
                    'customer_email'  => $customerEmail,
                    'shipping_address' => $shippingAddress,
                    'subtotal'        => max(0, $subtotal),
                    'shipping_cost'   => $shippingCost,
                    'discount'        => 0,
                    'total'           => $total,
                    'tracking_code'   => $trackingCode,
                    'paid_at'         => $paymentStatus === PaymentStatus::Paid
                        ? (! empty($payment['date_approved']) ? now()->parse($payment['date_approved']) : null)
                        : null,
                    'meta' => [
                        'ml_order_id'    => $ml['id'],
                        'ml_status'      => $ml['status'],
                        'ml_payment_id'  => $payment['id'] ?? null,
                        'ml_shipping_id' => $ml['shipping']['id'] ?? null,
                    ],
                ]
            );

            // ── Upsert Order Items ────────────────────────────────────────────
            foreach ($ml['order_items'] ?? [] as $mlItem) {
                $product = null;
                $mlSku   = $mlItem['item']['seller_sku'] ?? null;

                if ($mlSku) {
                    $product = Product::where('sku', $mlSku)->first();
                }

                $unitPrice = (float) ($mlItem['unit_price'] ?? 0);
                $quantity  = (int) ($mlItem['quantity'] ?? 1);

                $existing = $order->items()
                    ->whereJsonContains('meta->ml_item_id', $mlItem['item']['id'] ?? null)
                    ->first();

                $itemData = [
                    'order_id'   => $order->id,
                    'product_id' => $product?->id,
                    'name'       => $mlItem['item']['title'] ?? 'Produto ML',
                    'sku'        => $mlSku ?? $mlItem['item']['id'],
                    'quantity'   => $quantity,
                    'unit_price' => $unitPrice,
                    'discount'   => 0,
                    'total'      => $unitPrice * $quantity,
                    'meta'       => [
                        'ml_item_id'  => $mlItem['item']['id'] ?? null,
                        'ml_category' => $mlItem['item']['category_id'] ?? null,
                    ],
                ];

                if ($existing) {
                    $existing->update($itemData);
                } else {
                    OrderItem::create($itemData);
                }
            }
        });
    }

    private function upsertCustomer(
        MarketplaceAccount $account,
        string $name,
        ?string $email,
        ?int $mlUserId,
        array $buyer,
        array $receiver
    ): ?Customer {
        $companyId = $account->company_id;

        $newData = array_filter([
            'name'    => $name,
            'email'   => $email,
            'phone'   => $buyer['phone']['number'] ?? null,
            'address' => ! empty($receiver) ? [
                'street'  => ($receiver['street_name'] ?? '') . ' ' . ($receiver['street_number'] ?? ''),
                'city'    => $receiver['city']['name'] ?? '',
                'state'   => $receiver['state']['name'] ?? '',
                'zip'     => $receiver['zip_code'] ?? '',
            ] : null,
            'meta'    => $mlUserId ? ['ml_user_id' => $mlUserId] : null,
        ]);

        // 1. Try by email
        if ($email) {
            $customer = Customer::where('company_id', $companyId)
                ->where('email', $email)
                ->first();

            if ($customer) {
                $customer->fill(array_filter($newData))->save();
                return $customer;
            }
        }

        // 2. Try by ML user ID in meta
        if ($mlUserId) {
            $customer = Customer::where('company_id', $companyId)
                ->whereJsonContains('meta->ml_user_id', $mlUserId)
                ->first();

            if ($customer) {
                $customer->fill(array_filter($newData))->save();
                return $customer;
            }
        }

        // 3. Create new
        return Customer::create(array_merge(['company_id' => $companyId], array_filter($newData)));
    }
}
