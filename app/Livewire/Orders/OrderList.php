<?php

namespace App\Livewire\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\MarketplaceAccount;
use App\Models\Order;
use Livewire\Component;
use Livewire\WithPagination;

class OrderList extends Component
{
    use WithPagination;

    public string $search          = '';
    public string $status          = '';
    public string $paymentStatus   = '';
    public string $marketplaceId   = '';
    public string $dateFrom        = '';
    public string $dateTo          = '';
    public string $shippedFrom     = '';
    public string $shippedTo       = '';
    public string $hasTracking     = '';
    public string $isFulfillment   = '';
    public string $sortField       = 'paid_at';
    public string $sortDirection   = 'desc';

    protected $queryString = [
        'search'        => ['except' => ''],
        'status'        => ['except' => ''],
        'paymentStatus' => ['except' => ''],
        'marketplaceId' => ['except' => ''],
        'dateFrom'      => ['except' => ''],
        'dateTo'        => ['except' => ''],
        'shippedFrom'   => ['except' => ''],
        'shippedTo'     => ['except' => ''],
        'hasTracking'   => ['except' => ''],
        'isFulfillment' => ['except' => ''],
    ];

    public function updatingSearch(): void        { $this->resetPage(); }
    public function updatingStatus(): void        { $this->resetPage(); }
    public function updatingPaymentStatus(): void { $this->resetPage(); }
    public function updatingMarketplaceId(): void { $this->resetPage(); }
    public function updatingDateFrom(): void      { $this->resetPage(); }
    public function updatingDateTo(): void        { $this->resetPage(); }
    public function updatingShippedFrom(): void   { $this->resetPage(); }
    public function updatingShippedTo(): void     { $this->resetPage(); }
    public function updatingHasTracking(): void   { $this->resetPage(); }
    public function updatingIsFulfillment(): void { $this->resetPage(); }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField     = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function clearFilters(): void
    {
        $this->reset([
            'search', 'status', 'paymentStatus', 'marketplaceId',
            'dateFrom', 'dateTo', 'shippedFrom', 'shippedTo',
            'hasTracking', 'isFulfillment',
        ]);
        $this->resetPage();
    }

    public function cancelOrder(int $id): void
    {
        $order = Order::findOrFail($id);

        if (! $order->is_cancellable) {
            session()->flash('error', 'Este pedido nao pode ser cancelado.');
            return;
        }

        $order->update([
            'status'       => OrderStatus::Cancelled,
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
            ->when($this->marketplaceId, fn ($q) => $q->where('marketplace_account_id', $this->marketplaceId))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('paid_at', '>=', $this->dateFrom))
            ->when($this->dateTo,   fn ($q) => $q->whereDate('paid_at', '<=', $this->dateTo))
            ->when($this->shippedFrom, fn ($q) => $q->whereDate('shipped_at', '>=', $this->shippedFrom))
            ->when($this->shippedTo,   fn ($q) => $q->whereDate('shipped_at', '<=', $this->shippedTo))
            ->when($this->hasTracking === '1', fn ($q) => $q->whereNotNull('tracking_code'))
            ->when($this->hasTracking === '0', fn ($q) => $q->whereNull('tracking_code'))
            ->when($this->isFulfillment === '1', fn ($q) => $q->whereRaw("meta->>'is_fulfillment' = 'true'"))
            ->when($this->isFulfillment === '0', fn ($q) => $q->whereRaw("(meta->>'is_fulfillment' IS NULL OR meta->>'is_fulfillment' = 'false')"))
            ->when(
                $this->sortField === 'paid_at',
                fn ($q) => $q->orderByRaw("paid_at {$this->sortDirection} NULLS LAST")->orderBy('id', 'desc'),
                fn ($q) => $q->orderBy($this->sortField, $this->sortDirection)->orderBy('id', 'desc'),
            )
            ->paginate(25);

        $marketplaceAccounts = MarketplaceAccount::orderBy('account_name')->get(['id', 'account_name', 'marketplace_type']);

        $hasActiveFilters = $this->search || $this->status || $this->paymentStatus
            || $this->marketplaceId || $this->dateFrom || $this->dateTo
            || $this->shippedFrom || $this->shippedTo || $this->hasTracking !== ''
            || $this->isFulfillment !== '';

        return view('livewire.orders.order-list', [
            'orders'             => $orders,
            'statuses'           => OrderStatus::cases(),
            'paymentStatuses'    => PaymentStatus::cases(),
            'marketplaceAccounts'=> $marketplaceAccounts,
            'hasActiveFilters'   => $hasActiveFilters,
        ]);
    }
}
