<?php

namespace App\Jobs;

use App\Enums\MarketplaceType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\MarketplaceAccount;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\Marketplaces\MercadoLivreService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncSingleOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public readonly int $accountId,
        public readonly string $externalOrderId,
    ) {}

    public function handle(): void
    {
        $account = MarketplaceAccount::find($this->accountId);

        if (! $account || ! $account->credentials) {
            Log::warning("SyncSingleOrder: conta {$this->accountId} não encontrada ou sem credenciais.");
            return;
        }

        if ($account->marketplace_type !== MarketplaceType::MercadoLivre) {
            return;
        }

        try {
            $service  = new MercadoLivreService($account);
            $mlOrder  = $service->getOrder($this->externalOrderId);

            if (empty($mlOrder['id'])) {
                Log::warning("SyncSingleOrder: pedido ML#{$this->externalOrderId} retornou vazio.");
                return;
            }

            $this->upsertOrder($account, $mlOrder, $service);

            Log::info("SyncSingleOrder: pedido ML#{$this->externalOrderId} sincronizado com sucesso.");

        } catch (\Throwable $e) {
            Log::error("SyncSingleOrder: erro pedido ML#{$this->externalOrderId}: " . $e->getMessage());
            throw $e;
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
        $trackingCode       = null;
        $shippingCost       = 0;
        $shippingMethod     = null;
        $shippingMode       = null;
        $estimatedDelivery  = null;
        $dateDelivered      = null;
        $dateShipped        = null;
        $shipment           = [];

        if (! empty($ml['shipping']['id'])) {
            $shipment          = $service->getShipping((string) $ml['shipping']['id']);
            $trackingCode      = $shipment['tracking_number'] ?? null;
            $shippingCost      = $shipment['shipping_option']['cost'] ?? 0;
            $shippingMethod    = $shipment['shipping_option']['name'] ?? null;
            $shippingMode      = $shipment['mode'] ?? null;
            $estimatedDelivery = $shipment['estimated_delivery_time']['date'] ?? null;
            $dateDelivered     = $shipment['date_delivered'] ?? null;

            // Determine shipped_at from shipment status transitions
            $shipmentStatus = $shipment['status'] ?? null;
            if (in_array($shipmentStatus, ['shipped', 'delivered', 'to_be_agreed'])) {
                // Try to get the actual shipped date from status history
                $dateShipped = $shipment['date_shipped'] ?? null;
                if (! $dateShipped) {
                    // Fallback: find it in status_history
                    foreach (($shipment['status_history'] ?? []) as $hist) {
                        if (($hist['status'] ?? '') === 'shipped') {
                            $dateShipped = $hist['date'] ?? null;
                            break;
                        }
                    }
                }
                $dateShipped = $dateShipped ?? ($trackingCode ? ($ml['last_updated'] ?? null) : null);
            } else {
                $dateShipped = null;
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

        // Phone: combine area_code + number from buyer, fallback to receiver_address
        $customerPhone = null;
        $buyerAreaCode = $buyer['phone']['area_code'] ?? null;
        $buyerNumber   = $buyer['phone']['number'] ?? null;
        if ($buyerAreaCode && $buyerNumber) {
            $customerPhone = '(' . $buyerAreaCode . ') ' . $buyerNumber;
        } elseif ($buyerNumber) {
            $customerPhone = $buyerNumber;
        }
        // Fallback: phone from shipment receiver_address
        if (! $customerPhone) {
            $rcvAreaCode = $receiver['phone']['area_code'] ?? null;
            $rcvNumber   = $receiver['phone']['number'] ?? null;
            if ($rcvAreaCode && $rcvNumber) {
                $customerPhone = '(' . $rcvAreaCode . ') ' . $rcvNumber;
            } elseif ($rcvNumber) {
                $customerPhone = $rcvNumber;
            }
        }

        // Pack ID (needed for messages)
        $packId = $ml['pack_id'] ?? null;

        // Tags
        $tags = $ml['tags'] ?? [];

        // Feedback
        $buyerFeedback = $ml['feedback']['buyer'] ?? null;

        DB::transaction(function () use (
            $account, $ml, $buyer, $orderStatus, $paymentStatus, $payment,
            $trackingCode, $shippingCost, $shippingAddress, $total, $subtotal,
            $customerName, $customerEmail, $customerPhone, $mlUserId, $receiver,
            $shippingMethod, $shippingMode, $estimatedDelivery, $dateDelivered,
            $packId, $tags, $buyerFeedback, $shipment, $dateShipped
        ) {
            $customer = $this->upsertCustomer(
                $account, $customerName, $customerEmail, $mlUserId, $buyer, $receiver
            );

            $deliveredAt = null;
            if ($dateDelivered) {
                $deliveredAt = now()->parse($dateDelivered);
            } elseif ($orderStatus === OrderStatus::Delivered) {
                $deliveredAt = now()->parse($ml['last_updated'] ?? now());
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
                    'shipped_at'       => $dateShipped ? now()->parse($dateShipped) : null,
                    'delivered_at'     => $deliveredAt,
                    'meta'             => [
                        'ml_order_id'          => $ml['id'],
                        'ml_status'            => $ml['status'],
                        'ml_payment_id'        => $payment['id'] ?? null,
                        'ml_payment_method'    => $payment['payment_method_id'] ?? null,
                        'ml_payment_type'      => $payment['payment_type'] ?? null,
                        'ml_installments'      => $payment['installments'] ?? null,
                        'ml_shipping_id'       => $ml['shipping']['id'] ?? null,
                        'ml_shipping_mode'     => $shippingMode,
                        'ml_shipping_status'   => $shipment['status'] ?? null,
                        'ml_estimated_delivery'=> $estimatedDelivery,
                        'ml_buyer_id'          => $mlUserId,
                        'pack_id'              => $packId,
                        'ml_tags'              => $tags,
                        'ml_feedback'          => $buyerFeedback,
                        'is_fulfillment'       => in_array('fulfillment', $tags),
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
                        'ml_item_id'       => $mlItem['item']['id'] ?? null,
                        'ml_category'      => $mlItem['item']['category_id'] ?? null,
                        'ml_variation_id'  => $mlItem['item']['variation_id'] ?? null,
                        'ml_variation_attrs' => $mlItem['item']['variation_attributes'] ?? null,
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

        // Build phone with DDD
        $phone = null;
        $areaCode = $buyer['phone']['area_code'] ?? null;
        $number   = $buyer['phone']['number'] ?? null;
        if ($areaCode && $number) {
            $phone = '(' . $areaCode . ') ' . $number;
        } elseif ($number) {
            $phone = $number;
        }
        // Fallback to receiver phone
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
