<?php

namespace App\Livewire\Orders;

use App\Enums\PipelineStatus;
use App\Enums\ProductionStatus;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class ProductionBoard extends Component
{
    use WithPagination;

    public string $search      = '';
    public string $filterStatus = '';
    public string $sortField    = 'paid_at';
    public string $sortDirection = 'asc';

    protected $queryString = [
        'search'       => ['except' => ''],
        'filterStatus' => ['except' => ''],
    ];

    public function updatingSearch(): void     { $this->resetPage(); }
    public function updatingFilterStatus(): void { $this->resetPage(); }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField     = $field;
            $this->sortDirection = 'asc';
        }
    }

    // ─── Ações por pedido ────────────────────────────────────────────────────

    /**
     * Move o pedido inteiro para "Em Produção" (pipeline_status = in_production).
     * Marca todos os itens que requerem produção como in_progress.
     */
    public function startProduction(int $orderId): void
    {
        $order = Order::where('company_id', Auth::user()->company_id)->findOrFail($orderId);

        $order->items()
            ->where('production_status', ProductionStatus::Pending->value)
            ->update(['production_status' => ProductionStatus::InProgress->value]);

        $order->update(['pipeline_status' => PipelineStatus::InProduction]);
        $order->recalculatePipelineStatus();

        session()->flash('success', "Pedido {$order->order_number} iniciado em produção.");
    }

    /**
     * Atualiza o production_status de um item específico.
     */
    public function updateItemStatus(int $itemId, string $status): void
    {
        $item = OrderItem::whereHas('order', fn ($q) =>
            $q->where('company_id', Auth::user()->company_id)
        )->findOrFail($itemId);

        $item->update([
            'production_status'    => ProductionStatus::from($status),
            'production_completed_at' => $status === ProductionStatus::Complete->value ? now() : null,
        ]);

        $item->order->recalculatePipelineStatus();

        session()->flash('success', "Item atualizado para: " . ProductionStatus::from($status)->label());
    }

    /**
     * Salva a URL de arte/mockup de um item.
     */
    public function saveArtwork(int $itemId, string $url): void
    {
        $item = OrderItem::whereHas('order', fn ($q) =>
            $q->where('company_id', Auth::user()->company_id)
        )->findOrFail($itemId);

        $item->update(['artwork_url' => $url ?: null]);

        session()->flash('success', 'Arte salva.');
    }

    /**
     * Toggle de aprovação de arte.
     */
    public function toggleArtworkApproved(int $itemId): void
    {
        $item = OrderItem::whereHas('order', fn ($q) =>
            $q->where('company_id', Auth::user()->company_id)
        )->findOrFail($itemId);

        $item->update(['artwork_approved' => ! $item->artwork_approved]);
    }

    /**
     * Marca todos os itens como concluídos e move para expedição.
     */
    public function completeProduction(int $orderId): void
    {
        $order = Order::where('company_id', Auth::user()->company_id)->findOrFail($orderId);

        $order->items()
            ->whereIn('production_status', [
                ProductionStatus::Pending->value,
                ProductionStatus::InProgress->value,
            ])
            ->update([
                'production_status'    => ProductionStatus::Complete->value,
                'production_completed_at' => now(),
            ]);

        $order->recalculatePipelineStatus();

        session()->flash('success', "Pedido {$order->order_number} concluído — pronto para expedição.");
    }

    // ─── Render ──────────────────────────────────────────────────────────────

    public function render()
    {
        $productionPipelines = [
            PipelineStatus::AwaitingProduction->value,
            PipelineStatus::InProduction->value,
        ];

        $filterPipelines = $this->filterStatus
            ? [$this->filterStatus]
            : $productionPipelines;

        $orders = Order::query()
            ->with([
                'items.product.primaryImage',
                'items.variant',
                'marketplaceAccount',
                'customer',
            ])
            ->where('company_id', Auth::user()->company_id)
            ->whereIn('pipeline_status', $filterPipelines)
            ->when($this->search, fn ($q) => $q->search($this->search))
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(15);

        $counts = [
            'awaiting_production' => Order::where('company_id', Auth::user()->company_id)
                ->where('pipeline_status', PipelineStatus::AwaitingProduction->value)->count(),
            'in_production' => Order::where('company_id', Auth::user()->company_id)
                ->where('pipeline_status', PipelineStatus::InProduction->value)->count(),
        ];

        return view('livewire.orders.production-board', compact('orders', 'counts'));
    }
}
