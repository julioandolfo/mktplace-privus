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
                            {--days=7   : Quantos dias para trás sincronizar (quando sem last_synced_at)}';

    protected $description = 'Importa pedidos/vendas do Mercado Livre para o sistema';

    public function handle(): int
    {
        $accounts = $this->resolveAccounts();

        if ($accounts->isEmpty()) {
            if ($id = $this->option('account')) {
                // Check if the account exists but is inactive
                $account = MarketplaceAccount::find($id);
                if ($account) {
                    $this->error("Conta [{$account->account_name}] existe mas está com status '{$account->status->value}' (não ativa). Verifique o token OAuth.");
                } else {
                    $this->error("Conta com ID={$id} não encontrada.");
                }
            } else {
                $this->warn('Nenhuma conta ativa do Mercado Livre encontrada. Verifique se as contas estão com status "active" e com token válido.');
            }
            return self::FAILURE;
        }

        $totalErrors = 0;

        foreach ($accounts as $account) {
            $errors = $this->syncAccount($account);
            $totalErrors += $errors;
        }

        return $totalErrors > 0 ? self::FAILURE : self::SUCCESS;
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

    private function syncAccount(MarketplaceAccount $account): int
    {
        $this->info("Sincronizando pedidos: [{$account->id}] {$account->account_name}");

        if (! $account->shop_id) {
            $msg = "Conta não possui shop_id (user_id do ML). Configure-o na edição da conta.";
            $account->update(['last_error' => $msg]);
            $this->error("  {$msg}");
            return 1;
        }

        if ($account->isTokenExpired()) {
            $expiresAt = $account->token_expires_at?->format('d/m/Y H:i') ?? 'desconhecido';
            $msg = "Token OAuth expirado em {$expiresAt}. Execute marketplace:refresh-tokens --force para tentar renovar.";
            $this->warn("  {$msg}");
            // Token may still work; continue and let the API call fail naturally
        }

        // --days sempre define o mínimo de janela retroativa,
        // independente do last_synced_at. Isso evita ignorar --days quando
        // last_synced_at já foi definido por um sync anterior.
        $sinceFromDays    = now()->subDays((int) $this->option('days'));
        $sinceFromLastSync = $account->last_synced_at
            ? $account->last_synced_at->subHour()
            : null;

        // Usa a data mais antiga entre as duas (maior cobertura)
        $since = $sinceFromLastSync && $sinceFromLastSync->isAfter($sinceFromDays)
            ? $sinceFromDays
            : ($sinceFromLastSync ?? $sinceFromDays);

        $this->line("  Buscando pedidos desde: {$since->format('d/m/Y H:i')} (--days=" . $this->option('days') . ")");

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

            $account->update(['last_synced_at' => now(), 'last_error' => null]);

            activity('marketplace')
                ->performedOn($account)
                ->withProperties(['synced' => $synced, 'errors' => $errors, 'since' => $since->toDateTimeString()])
                ->log('Pedidos sincronizados');

            $this->info("  ✓ {$synced} pedido(s) sincronizado(s)" . ($errors ? ", {$errors} erro(s)" : ''));

            return $errors;

        } catch (\Throwable $e) {
            $account->update(['last_error' => $e->getMessage()]);
            Log::error("SyncOrders: falha na conta {$account->id}: " . $e->getMessage());
            $this->error("  Falha na conta {$account->account_name}: " . $e->getMessage());
            return 1;
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
        $trackingCode      = null;
        $shippingCost      = 0;
        $shippingMethod    = null;
        $shippingMode      = null;
        $estimatedDelivery = null;
        $shippingDeadline  = null;
        $dateDelivered     = null;
        $dateShipped       = null;
        $shipmentStatus    = null;
        $shipment          = [];

        if (! empty($ml['shipping']['id'])) {
            $shipment          = $service->getShipping((string) $ml['shipping']['id']);
            $trackingCode      = $shipment['tracking_number'] ?? null;
            $shippingCost      = $shipment['shipping_option']['cost'] ?? 0;
            $shippingMethod    = $shipment['shipping_option']['name'] ?? null;
            $shippingMode      = $shipment['mode'] ?? null;
            $estimatedDelivery = $shipment['estimated_delivery_time']['date'] ?? null;
            $dateDelivered     = $shipment['date_delivered'] ?? null;
            $shipmentStatus    = $shipment['status'] ?? null;

            // Override order status based on shipment status — ML keeps order.status
            // as 'confirmed' even after delivery; the real delivery state is in shipment.
            if ($orderStatus !== OrderStatus::Cancelled) {
                $orderStatus = match ($shipmentStatus) {
                    'delivered'                 => OrderStatus::Delivered,
                    'shipped', 'not_delivered'  => OrderStatus::Shipped,
                    'ready_to_ship', 'handling' => OrderStatus::ReadyToShip,
                    default                     => $orderStatus,
                };
            }

            // Dispatch deadline from /shipments/{id}/lead_time
            $leadTime  = $service->getShippingLeadTime((string) $ml['shipping']['id']);
            $paidAtRaw = $payment['date_approved'] ?? $ml['date_created'] ?? null;
            $paidAtCarbon = $paidAtRaw ? \Carbon\Carbon::parse($paidAtRaw) : null;
            $shippingDeadline = MercadoLivreService::extractDispatchDeadline($leadTime, $paidAtCarbon);

            if (in_array($shipmentStatus, ['shipped', 'delivered', 'to_be_agreed', 'not_delivered'])) {
                $dateShipped = $shipment['date_shipped'] ?? null;
                if (! $dateShipped) {
                    foreach (($shipment['status_history'] ?? []) as $hist) {
                        if (($hist['status'] ?? '') === 'shipped') {
                            $dateShipped = $hist['date'] ?? null;
                            break;
                        }
                    }
                }
                $dateShipped = $dateShipped ?? ($trackingCode ? ($ml['last_updated'] ?? null) : null);
            }
        }

        $receiver        = $shipment['receiver_address'] ?? [];
        $shippingAddress = ! empty($receiver) ? [
            'street'       => trim(($receiver['street_name'] ?? '') . ' ' . ($receiver['street_number'] ?? '')),
            'complement'   => $receiver['comment'] ?? null,
            'neighborhood' => $receiver['neighborhood']['name'] ?? null,
            'city'         => $receiver['city']['name'] ?? '',
            'state'        => $receiver['state']['name'] ?? '',
            'zip'          => $receiver['zip_code'] ?? '',
            'country'      => $receiver['country']['id'] ?? 'BR',
        ] : null;

        $total    = (float) ($payment['transaction_amount'] ?? 0);
        $subtotal = max(0, $total - (float) $shippingCost);

        $customerName  = trim(($buyer['first_name'] ?? '') . ' ' . ($buyer['last_name'] ?? ''))
                         ?: ($buyer['nickname'] ?? 'Comprador ML');
        $customerEmail = ! empty($buyer['email']) ? $buyer['email'] : null;
        $mlUserId      = $buyer['id'] ?? null;

        // Phone: combine area_code + number, fallback to receiver_address
        $customerPhone = null;
        $buyerAreaCode = $buyer['phone']['area_code'] ?? null;
        $buyerNumber   = $buyer['phone']['number'] ?? null;
        if ($buyerAreaCode && $buyerNumber) {
            $customerPhone = '(' . $buyerAreaCode . ') ' . $buyerNumber;
        } elseif ($buyerNumber) {
            $customerPhone = $buyerNumber;
        }
        if (! $customerPhone) {
            $rcvAreaCode = $receiver['phone']['area_code'] ?? null;
            $rcvNumber   = $receiver['phone']['number'] ?? null;
            if ($rcvAreaCode && $rcvNumber) {
                $customerPhone = '(' . $rcvAreaCode . ') ' . $rcvNumber;
            } elseif ($rcvNumber) {
                $customerPhone = $rcvNumber;
            }
        }

        // Enriched ML data
        $packId            = $ml['pack_id'] ?? null;
        $tags              = $ml['tags'] ?? [];
        $buyerFeedback     = $ml['feedback']['buyer'] ?? null;
        $isFulfillment     = in_array('fulfillment', $tags);

        DB::transaction(function () use (
            $account, $ml, $buyer, $orderStatus, $paymentStatus, $payment,
            $trackingCode, $shippingCost, $shippingAddress, $total, $subtotal,
            $customerName, $customerEmail, $customerPhone, $mlUserId, $receiver,
            $shippingMethod, $shippingMode, $estimatedDelivery, $shippingDeadline, $dateDelivered,
            $packId, $tags, $buyerFeedback, $isFulfillment, $shipment, $dateShipped
        ) {
            $customer = $this->upsertCustomer(
                $account, $customerName, $customerEmail, $mlUserId, $buyer, $receiver
            );

            $deliveredAt = null;
            if ($dateDelivered) {
                $deliveredAt = now()->parse($dateDelivered);
            } elseif ($orderStatus === OrderStatus::Delivered) {
                $deliveredAt = ! empty($ml['last_updated'])
                    ? now()->parse($ml['last_updated'])
                    : null;
            }

            $order = Order::updateOrCreate(
                [
                    'marketplace_account_id' => $account->id,
                    'external_id'            => (string) $ml['id'],
                ],
                [
                    'company_id'       => $account->company_id,
                    'customer_id'      => $customer?->id,
                    'status'           => $orderStatus,
                    'payment_status'   => $paymentStatus,
                    'payment_method'   => $payment['payment_method_id'] ?? $payment['payment_type'] ?? null,
                    'customer_name'    => $customerName,
                    'customer_email'   => $customerEmail,
                    'customer_phone'   => $customerPhone,
                    'shipping_address' => $shippingAddress,
                    'subtotal'         => $subtotal,
                    'shipping_cost'    => $shippingCost,
                    'discount'         => 0,
                    'total'            => $total,
                    'tracking_code'    => $trackingCode,
                    'shipping_method'  => $shippingMethod,
                    'paid_at'          => $paymentStatus === PaymentStatus::Paid
                        ? (! empty($payment['date_approved']) ? now()->parse($payment['date_approved']) : null)
                        : null,
                    'shipped_at'       => ! empty($dateShipped) ? now()->parse($dateShipped) : null,
                    'delivered_at'     => $deliveredAt,
                    'meta'             => [
                        'ml_order_id'           => $ml['id'],
                        'ml_status'             => $ml['status'],
                        'ml_payment_id'         => $payment['id'] ?? null,
                        'ml_payment_method'     => $payment['payment_method_id'] ?? null,
                        'ml_payment_type'       => $payment['payment_type'] ?? null,
                        'ml_installments'       => $payment['installments'] ?? null,
                        'ml_shipping_id'        => $ml['shipping']['id'] ?? null,
                        'ml_shipping_mode'      => $shippingMode,
                        'ml_shipping_status'    => $shipment['status'] ?? null,
                        'ml_estimated_delivery' => $estimatedDelivery,
                        'ml_shipping_deadline'  => $shippingDeadline,
                        'ml_buyer_id'           => $mlUserId,
                        'pack_id'               => $packId,
                        'ml_tags'               => $tags,
                        'ml_feedback'           => $buyerFeedback,
                        'is_fulfillment'        => $isFulfillment,
                    ],
                ]
            );

            // Upsert Order Items
            foreach ($ml['order_items'] ?? [] as $mlItem) {
                $product = null;
                $mlSku   = $mlItem['item']['seller_sku'] ?? null;

                if ($mlSku) {
                    $product = Product::where('sku', $mlSku)->first();
                }

                $unitPrice = (float) ($mlItem['unit_price'] ?? 0);
                $quantity  = (int) ($mlItem['quantity'] ?? 1);

                $existing = $order->items()
                    ->whereRaw("meta->>'ml_item_id' = ?", [$mlItem['item']['id'] ?? null])
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
                        'ml_item_id'          => $mlItem['item']['id'] ?? null,
                        'ml_category'         => $mlItem['item']['category_id'] ?? null,
                        'ml_variation_id'     => $mlItem['item']['variation_id'] ?? null,
                        'ml_variation_attrs'  => $mlItem['item']['variation_attributes'] ?? null,
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

        // Phone with DDD
        $phone = null;
        $areaCode = $buyer['phone']['area_code'] ?? null;
        $number   = $buyer['phone']['number'] ?? null;
        if ($areaCode && $number) {
            $phone = '(' . $areaCode . ') ' . $number;
        } elseif ($number) {
            $phone = $number;
        }
        if (! $phone) {
            $rcvArea = $receiver['phone']['area_code'] ?? null;
            $rcvNum  = $receiver['phone']['number'] ?? null;
            if ($rcvArea && $rcvNum) {
                $phone = '(' . $rcvArea . ') ' . $rcvNum;
            } elseif ($rcvNum) {
                $phone = $rcvNum;
            }
        }

        $newData = array_filter([
            'name'    => $name,
            'email'   => $email,
            'phone'   => $phone,
            'address' => ! empty($receiver) ? [
                'street'       => trim(($receiver['street_name'] ?? '') . ' ' . ($receiver['street_number'] ?? '')),
                'complement'   => $receiver['comment'] ?? null,
                'neighborhood' => $receiver['neighborhood']['name'] ?? null,
                'city'         => $receiver['city']['name'] ?? '',
                'state'        => $receiver['state']['name'] ?? '',
                'zip'          => $receiver['zip_code'] ?? '',
            ] : null,
            'meta'    => $mlUserId ? ['ml_user_id' => $mlUserId] : null,
        ]);

        if ($email) {
            $customer = Customer::where('company_id', $companyId)->where('email', $email)->first();
            if ($customer) {
                $customer->fill(array_filter($newData))->save();
                return $customer;
            }
        }

        if ($mlUserId) {
            $customer = Customer::where('company_id', $companyId)
                ->whereJsonContains('meta->ml_user_id', $mlUserId)
                ->first();
            if ($customer) {
                $customer->fill(array_filter($newData))->save();
                return $customer;
            }
        }

        return Customer::create(array_merge(['company_id' => $companyId], array_filter($newData)));
    }
}
