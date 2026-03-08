<?php

namespace App\Livewire\Designer;

use App\Models\DesignAssignment;
use App\Models\Order;
use App\Jobs\AssignOrderToDesigner;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class DesignerBoard extends Component
{
    use WithPagination;

    public string $activeTab  = 'pending';
    public string $search     = '';
    public bool   $isAdmin    = false;
    public ?int   $filterDesigner = null;

    protected $queryString = [
        'activeTab'      => ['except' => 'pending'],
        'search'         => ['except' => ''],
        'filterDesigner' => ['except' => ''],
    ];

    public function mount(): void
    {
        $this->isAdmin = Auth::user()->role === 'admin';
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    /**
     * Admin pode reatribuir um assignment para outro designer.
     */
    public function reassign(int $assignmentId, int $designerId): void
    {
        abort_unless($this->isAdmin, 403);

        $assignment = DesignAssignment::where('company_id', Auth::user()->company_id)->findOrFail($assignmentId);
        $assignment->update([
            'designer_id' => $designerId,
            'status'      => 'pending',
            'started_at'  => null,
        ]);

        session()->flash('success', 'Assignment reatribuído.');
    }

    /**
     * Admin: dispara manualmente o assignment de um pedido (sem auto-trigger).
     */
    public function manualAssign(int $orderId): void
    {
        abort_unless($this->isAdmin, 403);

        AssignOrderToDesigner::dispatch($orderId);
        session()->flash('success', 'Distribuição iniciada via round-robin.');
    }

    public function render()
    {
        $user      = Auth::user();
        $companyId = $user->company_id;

        $query = DesignAssignment::query()
            ->with(['order.items.product.primaryImage', 'order.marketplaceAccount', 'designer'])
            ->where('company_id', $companyId)
            ->when(! $this->isAdmin, fn ($q) => $q->where('designer_id', $user->id))
            ->when($this->isAdmin && $this->filterDesigner, fn ($q) => $q->where('designer_id', $this->filterDesigner))
            ->when($this->search, fn ($q) => $q->whereHas('order', fn ($oq) =>
                $oq->where('order_number', 'like', "%{$this->search}%")
                   ->orWhere('customer_name', 'ilike', "%{$this->search}%")
            ));

        $tabCounts = [
            'pending'     => (clone $query)->where('status', 'pending')->count(),
            'in_progress' => (clone $query)->where('status', 'in_progress')->count(),
            'revision'    => (clone $query)->where('status', 'revision')->count(),
            'completed'   => (clone $query)->where('status', 'completed')->count(),
        ];

        $assignments = $query
            ->where('status', $this->activeTab)
            ->orderBy('assigned_at')
            ->paginate(12);

        // Para admin: lista de designers para filtro/reatribuição
        $designers = $this->isAdmin
            ? \App\Models\User::where('company_id', $companyId)
                ->where('role', 'designer')
                ->get(['id', 'name'])
            : collect();

        // Pedidos aguardando atribuição (sem assignment ativo) — só admin
        $unassignedOrders = collect();
        if ($this->isAdmin) {
            $assignedOrderIds = DesignAssignment::where('company_id', $companyId)
                ->whereIn('status', ['pending', 'in_progress', 'revision'])
                ->pluck('order_id');

            $unassignedOrders = Order::where('company_id', $companyId)
                ->where('pipeline_status', 'awaiting_production')
                ->whereNotIn('id', $assignedOrderIds)
                ->with(['marketplaceAccount'])
                ->limit(10)
                ->get();
        }

        return view('livewire.designer.designer-board', compact(
            'assignments', 'tabCounts', 'designers', 'unassignedOrders'
        ));
    }
}
