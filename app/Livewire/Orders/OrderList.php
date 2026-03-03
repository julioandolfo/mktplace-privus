<?php

namespace App\Livewire\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use Livewire\Component;
use Livewire\WithPagination;

class OrderList extends Component
{
    use WithPagination;

    public string $search = '';
    public string $status = '';
    public string $paymentStatus = '';
    public string $sortField = 'created_at';
    public string $sortDirection = 'desc';

    protected $queryString = [
        'search' => ['except' => ''],
        'status' => ['except' => ''],
        'paymentStatus' => ['except' => ''],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingPaymentStatus(): void
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

    public function cancelOrder(int $id): void
    {
        $order = Order::findOrFail($id);

        if (! $order->is_cancellable) {
            session()->flash('error', 'Este pedido nao pode ser cancelado.');
            return;
        }

        $order->update([
            'status' => OrderStatus::Cancelled,
            'cancelled_at' => now(),
        ]);

        session()->flash('success', 'Pedido cancelado com sucesso.');
    }

    public function deleteOrder(int $id): void
    {
        $order = Order::findOrFail($id);

        if ($order->status !== OrderStatus::Cancelled) {
            session()->flash('error', 'Apenas pedidos cancelados podem ser removidos.');
            return;
        }

        $order->delete();
        session()->flash('success', 'Pedido removido com sucesso.');
    }

    public function render()
    {
        $orders = Order::query()
            ->with(['items', 'marketplaceAccount'])
            ->when($this->search, fn ($q) => $q->search($this->search))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->paymentStatus, fn ($q) => $q->where('payment_status', $this->paymentStatus))
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(25);

        return view('livewire.orders.order-list', [
            'orders' => $orders,
            'statuses' => OrderStatus::cases(),
            'paymentStatuses' => PaymentStatus::cases(),
        ]);
    }
}
