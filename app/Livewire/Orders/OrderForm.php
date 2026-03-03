<?php

namespace App\Livewire\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Livewire\Component;

class OrderForm extends Component
{
    public ?Order $order = null;

    // Order info
    public string $status = 'pending';
    public string $payment_status = 'pending';
    public string $payment_method = '';

    // Customer
    public string $customer_name = '';
    public string $customer_email = '';
    public string $customer_phone = '';
    public string $customer_document = '';

    // Shipping address
    public string $shipping_zipcode = '';
    public string $shipping_street = '';
    public string $shipping_number = '';
    public string $shipping_complement = '';
    public string $shipping_neighborhood = '';
    public string $shipping_city = '';
    public string $shipping_state = '';

    // Totals
    public string $shipping_cost = '0.00';
    public string $discount = '0.00';

    // Tracking
    public string $tracking_code = '';
    public string $shipping_method = '';
    public string $notes = '';
    public string $internal_notes = '';

    // Items
    public array $items = [];
    public string $productSearch = '';
    public array $productResults = [];

    public function mount(?Order $order = null): void
    {
        if ($order && $order->exists) {
            $this->order = $order;
            $this->status = $order->status->value;
            $this->payment_status = $order->payment_status->value;
            $this->payment_method = $order->payment_method ?? '';
            $this->customer_name = $order->customer_name;
            $this->customer_email = $order->customer_email ?? '';
            $this->customer_phone = $order->customer_phone ?? '';
            $this->customer_document = $order->customer_document ?? '';
            $this->shipping_cost = (string) $order->shipping_cost;
            $this->discount = (string) $order->discount;
            $this->tracking_code = $order->tracking_code ?? '';
            $this->shipping_method = $order->shipping_method ?? '';
            $this->notes = $order->notes ?? '';
            $this->internal_notes = $order->internal_notes ?? '';

            $address = $order->shipping_address ?? [];
            $this->shipping_zipcode = $address['zipcode'] ?? '';
            $this->shipping_street = $address['street'] ?? '';
            $this->shipping_number = $address['number'] ?? '';
            $this->shipping_complement = $address['complement'] ?? '';
            $this->shipping_neighborhood = $address['neighborhood'] ?? '';
            $this->shipping_city = $address['city'] ?? '';
            $this->shipping_state = $address['state'] ?? '';

            $this->items = $order->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'variant_id' => $item->variant_id,
                'name' => $item->name,
                'sku' => $item->sku ?? '',
                'quantity' => $item->quantity,
                'unit_price' => (string) $item->unit_price,
                'discount' => (string) $item->discount,
            ])->toArray();
        }
    }

    public function updatedProductSearch(): void
    {
        if (strlen($this->productSearch) < 2) {
            $this->productResults = [];
            return;
        }

        $this->productResults = Product::query()
            ->search($this->productSearch)
            ->active()
            ->with('variants')
            ->limit(10)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'price' => (string) $p->price,
                'variants' => $p->variants->map(fn ($v) => [
                    'id' => $v->id,
                    'name' => $v->name,
                    'sku' => $v->sku,
                    'price' => (string) ($v->price ?? $p->price),
                ])->toArray(),
            ])
            ->toArray();
    }

    public function addProduct(int $productId, ?int $variantId = null): void
    {
        $product = Product::find($productId);
        if (! $product) {
            return;
        }

        $name = $product->name;
        $sku = $product->sku;
        $price = (string) $product->price;

        if ($variantId) {
            $variant = $product->variants()->find($variantId);
            if ($variant) {
                $name .= ' - ' . $variant->name;
                $sku = $variant->sku;
                $price = (string) ($variant->price ?? $product->price);
            }
        }

        $this->items[] = [
            'id' => null,
            'product_id' => $productId,
            'variant_id' => $variantId,
            'name' => $name,
            'sku' => $sku,
            'quantity' => 1,
            'unit_price' => $price,
            'discount' => '0.00',
        ];

        $this->productSearch = '';
        $this->productResults = [];
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function getSubtotalProperty(): float
    {
        return collect($this->items)->sum(function ($item) {
            return ((float) $item['unit_price'] * (int) $item['quantity']) - (float) $item['discount'];
        });
    }

    public function getTotalProperty(): float
    {
        return $this->subtotal + (float) $this->shipping_cost - (float) $this->discount;
    }

    public function save(): mixed
    {
        $rules = [
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'nullable|email|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'customer_document' => 'nullable|string|max:18',
            'status' => 'required|in:' . implode(',', array_column(OrderStatus::cases(), 'value')),
            'payment_status' => 'required|in:' . implode(',', array_column(PaymentStatus::cases(), 'value')),
            'payment_method' => 'nullable|string|max:50',
            'shipping_cost' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'tracking_code' => 'nullable|string|max:100',
            'shipping_method' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:2000',
            'internal_notes' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.name' => 'required|string|max:255',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
        ];

        $validated = $this->validate($rules);

        $shippingAddress = array_filter([
            'zipcode' => $this->shipping_zipcode,
            'street' => $this->shipping_street,
            'number' => $this->shipping_number,
            'complement' => $this->shipping_complement,
            'neighborhood' => $this->shipping_neighborhood,
            'city' => $this->shipping_city,
            'state' => $this->shipping_state,
        ]);

        $orderData = [
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'payment_method' => $this->payment_method ?: null,
            'customer_name' => $this->customer_name,
            'customer_email' => $this->customer_email ?: null,
            'customer_phone' => $this->customer_phone ?: null,
            'customer_document' => $this->customer_document ?: null,
            'shipping_address' => ! empty($shippingAddress) ? $shippingAddress : null,
            'shipping_cost' => (float) $this->shipping_cost,
            'discount' => (float) $this->discount,
            'tracking_code' => $this->tracking_code ?: null,
            'shipping_method' => $this->shipping_method ?: null,
            'notes' => $this->notes ?: null,
            'internal_notes' => $this->internal_notes ?: null,
            'subtotal' => $this->subtotal,
            'total' => $this->total,
        ];

        // Set timestamps based on status changes
        if ($this->payment_status === 'paid' && (! $this->order || ! $this->order->paid_at)) {
            $orderData['paid_at'] = now();
        }
        if ($this->status === 'shipped' && (! $this->order || ! $this->order->shipped_at)) {
            $orderData['shipped_at'] = now();
        }
        if ($this->status === 'delivered' && (! $this->order || ! $this->order->delivered_at)) {
            $orderData['delivered_at'] = now();
        }
        if ($this->status === 'cancelled' && (! $this->order || ! $this->order->cancelled_at)) {
            $orderData['cancelled_at'] = now();
        }

        if ($this->order) {
            $this->order->update($orderData);
            $order = $this->order;
        } else {
            $order = Order::create($orderData);
        }

        // Sync items
        $existingIds = [];
        foreach ($this->items as $itemData) {
            $itemTotal = ((float) $itemData['unit_price'] * (int) $itemData['quantity']) - (float) ($itemData['discount'] ?? 0);

            if (! empty($itemData['id'])) {
                $item = $order->items()->find($itemData['id']);
                $item?->update([
                    'product_id' => $itemData['product_id'] ?? null,
                    'variant_id' => $itemData['variant_id'] ?? null,
                    'name' => $itemData['name'],
                    'sku' => $itemData['sku'] ?? null,
                    'quantity' => (int) $itemData['quantity'],
                    'unit_price' => (float) $itemData['unit_price'],
                    'discount' => (float) ($itemData['discount'] ?? 0),
                    'total' => $itemTotal,
                ]);
                $existingIds[] = $item->id;
            } else {
                $newItem = $order->items()->create([
                    'product_id' => $itemData['product_id'] ?? null,
                    'variant_id' => $itemData['variant_id'] ?? null,
                    'name' => $itemData['name'],
                    'sku' => $itemData['sku'] ?? null,
                    'quantity' => (int) $itemData['quantity'],
                    'unit_price' => (float) $itemData['unit_price'],
                    'discount' => (float) ($itemData['discount'] ?? 0),
                    'total' => $itemTotal,
                ]);
                $existingIds[] = $newItem->id;
            }
        }

        // Remove items that were deleted from the form
        $order->items()->whereNotIn('id', $existingIds)->delete();

        session()->flash('success', $this->order ? 'Pedido atualizado.' : 'Pedido cadastrado.');
        return $this->redirect(route('orders.index'), navigate: false);
    }

    public function render()
    {
        return view('livewire.orders.order-form', [
            'statuses' => OrderStatus::cases(),
            'paymentStatuses' => PaymentStatus::cases(),
        ]);
    }
}
