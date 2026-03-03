<?php

namespace App\Livewire\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use Livewire\Component;
use Livewire\WithPagination;

class ProductionBoard extends Component
{
    use WithPagination;

    public string $search = '';
    public string $status = '';
    public string $sortField = 'created_at';
    public string $sortDirection = 'asc';

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

    public function moveToProduction(int $id): void
    {
        $order = Order::findOrFail($id);
        $order->update(['status' => OrderStatus::InProduction]);
        session()->flash('success', "Pedido {$order->order_number} movido para producao.");
    }

    public function markProduced(int $id): void
    {
        $order = Order::findOrFail($id);
        $order->update(['status' => OrderStatus::Produced]);
        session()->flash('success', "Pedido {$order->order_number} marcado como produzido.");
    }

    public function markReadyToShip(int $id): void
    {
        $order = Order::findOrFail($id);
        $order->update(['status' => OrderStatus::ReadyToShip]);
        session()->flash('success', "Pedido {$order->order_number} pronto para envio.");
    }

    public function render()
    {
        $productionStatuses = [
            OrderStatus::Confirmed,
            OrderStatus::InProduction,
            OrderStatus::Produced,
        ];

        $orders = Order::query()
            ->with(['items'])
            ->whereIn('status', $this->status
                ? [OrderStatus::from($this->status)]
                : $productionStatuses
            )
            ->when($this->search, fn ($q) => $q->search($this->search))
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(25);

        return view('livewire.orders.production-board', [
            'orders' => $orders,
            'statuses' => collect($productionStatuses),
            'counts' => [
                'confirmed' => Order::where('status', OrderStatus::Confirmed)->count(),
                'in_production' => Order::where('status', OrderStatus::InProduction)->count(),
                'produced' => Order::where('status', OrderStatus::Produced)->count(),
            ],
        ]);
    }
}
