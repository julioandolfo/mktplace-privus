<?php

namespace App\Livewire\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use Livewire\Component;
use Livewire\WithPagination;

class ExpeditionBoard extends Component
{
    use WithPagination;

    public string $search = '';
    public string $status = '';
    public string $sortField = 'created_at';
    public string $sortDirection = 'asc';

    // Tracking modal
    public bool $showTrackingModal = false;
    public ?int $trackingOrderId = null;
    public string $trackingCode = '';
    public string $shippingMethod = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'status' => ['except' => ''],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function openTrackingModal(int $id): void
    {
        $order = Order::findOrFail($id);
        $this->trackingOrderId = $order->id;
        $this->trackingCode = $order->tracking_code ?? '';
        $this->shippingMethod = $order->shipping_method ?? '';
        $this->showTrackingModal = true;
    }

    public function saveTracking(): void
    {
        $this->validate([
            'trackingCode' => 'required|string|max:100',
            'shippingMethod' => 'nullable|string|max:100',
        ]);

        $order = Order::findOrFail($this->trackingOrderId);
        $order->update([
            'tracking_code' => $this->trackingCode,
            'shipping_method' => $this->shippingMethod ?: null,
        ]);

        $this->showTrackingModal = false;
        $this->reset(['trackingOrderId', 'trackingCode', 'shippingMethod']);
        session()->flash('success', "Rastreio atualizado para pedido {$order->order_number}.");
    }

    public function markShipped(int $id): void
    {
        $order = Order::findOrFail($id);

        if (! $order->tracking_code) {
            $this->openTrackingModal($id);
            return;
        }

        $order->update([
            'status' => OrderStatus::Shipped,
            'shipped_at' => now(),
        ]);
        session()->flash('success', "Pedido {$order->order_number} marcado como enviado.");
    }

    public function markDelivered(int $id): void
    {
        $order = Order::findOrFail($id);
        $order->update([
            'status' => OrderStatus::Delivered,
            'delivered_at' => now(),
        ]);
        session()->flash('success', "Pedido {$order->order_number} marcado como entregue.");
    }

    public function render()
    {
        $expeditionStatuses = [
            OrderStatus::ReadyToShip,
            OrderStatus::Shipped,
        ];

        $orders = Order::query()
            ->with(['items'])
            ->whereIn('status', $this->status
                ? [OrderStatus::from($this->status)]
                : $expeditionStatuses
            )
            ->when($this->search, fn ($q) => $q->search($this->search))
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(25);

        return view('livewire.orders.expedition-board', [
            'orders' => $orders,
            'statuses' => collect($expeditionStatuses),
            'counts' => [
                'ready_to_ship' => Order::where('status', OrderStatus::ReadyToShip)->count(),
                'shipped' => Order::where('status', OrderStatus::Shipped)->count(),
            ],
        ]);
    }
}
