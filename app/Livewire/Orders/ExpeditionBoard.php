<?php

namespace App\Livewire\Orders;

use App\Enums\MarketplaceType;
use App\Enums\OrderStatus;
use App\Enums\PipelineStatus;
use App\Models\MarketplaceAccount;
use App\Models\Order;
use App\Models\Romaneio;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class ExpeditionBoard extends Component
{
    use WithPagination;

    public string $activeTab    = 'today';
    public string $search       = '';
    public string $filterAccount = '';
    public string $filterType   = '';

    public array $selectedOrders = [];
    public bool  $selectAll      = false;

    // Volumes por pedido (inline no board): [order_id => int]
    public array $orderVolumes = [];

    // Modal de criar romaneio
    public bool   $showRomaneioModal = false;
    public string $romaneioName      = '';
    public int    $romaneioMode      = 1; // 1 = com selecionados | 2 = vazio (bipagem)

    // Modal de confirmar marcar embalado em lote
    public bool  $showBulkPackModal = false;

    protected $queryString = [
        'activeTab'     => ['except' => 'today'],
        'search'        => ['except' => ''],
        'filterAccount' => ['except' => ''],
    ];

    public function updatingSearch(): void    { $this->resetPage(); }
    public function updatingActiveTab(): void { $this->resetPage(); $this->selectedOrders = []; $this->selectAll = false; }
    public function updatingFilterAccount(): void { $this->resetPage(); }

    // ----------------------------------------------------------------
    //  Tab counts
    // ----------------------------------------------------------------

    protected function getTabCounts(): array
    {
        $base = Order::query()
            ->where('company_id', Auth::user()->company_id)
            ->when($this->filterAccount, fn ($q) => $q->where('marketplace_account_id', $this->filterAccount));

        $today    = now()->toDateString();
        $tomorrow = now()->addDay()->toDateString();
        $friday   = now()->endOfWeek(Carbon::FRIDAY)->toDateString();

        $expeditionPipeline = array_map(fn ($s) => $s->value, PipelineStatus::expeditionStatuses());

        return [
            'in_production' => (clone $base)
                ->whereIn('pipeline_status', array_map(fn ($s) => $s->value, PipelineStatus::productionStatuses()))
                ->count(),

            'overdue' => (clone $base)
                ->whereIn('pipeline_status', $expeditionPipeline)
                ->whereRaw("meta->>'ml_shipping_deadline' IS NOT NULL")
                ->whereRaw("(meta->>'ml_shipping_deadline')::timestamptz::date < ?", [$today])
                ->count(),

            'today' => (clone $base)
                ->whereIn('pipeline_status', $expeditionPipeline)
                ->whereRaw("meta->>'ml_shipping_deadline' IS NOT NULL")
                ->whereRaw("(meta->>'ml_shipping_deadline')::timestamptz::date = ?", [$today])
                ->count(),

            'tomorrow' => (clone $base)
                ->whereIn('pipeline_status', $expeditionPipeline)
                ->whereRaw("meta->>'ml_shipping_deadline' IS NOT NULL")
                ->whereRaw("(meta->>'ml_shipping_deadline')::timestamptz::date = ?", [$tomorrow])
                ->count(),

            'this_week' => (clone $base)
                ->whereIn('pipeline_status', $expeditionPipeline)
                ->whereRaw("meta->>'ml_shipping_deadline' IS NOT NULL")
                ->whereRaw(
                    "(meta->>'ml_shipping_deadline')::timestamptz::date > ? AND (meta->>'ml_shipping_deadline')::timestamptz::date <= ?",
                    [$tomorrow, $friday]
                )
                ->count(),

            'later' => (clone $base)
                ->whereIn('pipeline_status', $expeditionPipeline)
                ->where(fn ($q) => $q
                    ->whereRaw("meta IS NULL OR meta->>'ml_shipping_deadline' IS NULL")
                    ->orWhereRaw("(meta->>'ml_shipping_deadline')::timestamptz::date > ?", [$friday])
                )
                ->count(),

            'partial' => (clone $base)
                ->where('pipeline_status', PipelineStatus::PartiallyShipped->value)
                ->count(),

            'shipped' => (clone $base)
                ->where('pipeline_status', PipelineStatus::Shipped->value)
                ->count(),
        ];
    }

    // ----------------------------------------------------------------
    //  Query helper
    // ----------------------------------------------------------------

    protected function buildQuery()
    {
        $today    = now()->toDateString();
        $tomorrow = now()->addDay()->toDateString();
        $friday   = now()->endOfWeek(Carbon::FRIDAY)->toDateString();

        $expeditionPipeline = array_map(fn ($s) => $s->value, PipelineStatus::expeditionStatuses());

        $query = Order::query()
            ->with(['items.product', 'items.variant', 'marketplaceAccount'])
            ->where('company_id', Auth::user()->company_id)
            ->when($this->search, fn ($q) => $q->search($this->search))
            ->when($this->filterAccount, fn ($q) => $q->where('marketplace_account_id', $this->filterAccount))
            ->when($this->filterType, fn ($q) => $q->whereHas('marketplaceAccount', fn ($mq) =>
                $mq->where('marketplace_type', $this->filterType)
            ));

        match ($this->activeTab) {
            'in_production' => $query->whereIn('pipeline_status',
                array_map(fn ($s) => $s->value, PipelineStatus::productionStatuses())
            ),

            'overdue' => $query
                ->whereIn('pipeline_status', $expeditionPipeline)
                ->whereRaw("meta->>'ml_shipping_deadline' IS NOT NULL")
                ->whereRaw("(meta->>'ml_shipping_deadline')::timestamptz::date < ?", [$today]),

            'today' => $query
                ->whereIn('pipeline_status', $expeditionPipeline)
                ->whereRaw("meta->>'ml_shipping_deadline' IS NOT NULL")
                ->whereRaw("(meta->>'ml_shipping_deadline')::timestamptz::date = ?", [$today]),

            'tomorrow' => $query
                ->whereIn('pipeline_status', $expeditionPipeline)
                ->whereRaw("meta->>'ml_shipping_deadline' IS NOT NULL")
                ->whereRaw("(meta->>'ml_shipping_deadline')::timestamptz::date = ?", [$tomorrow]),

            'this_week' => $query
                ->whereIn('pipeline_status', $expeditionPipeline)
                ->whereRaw("meta->>'ml_shipping_deadline' IS NOT NULL")
                ->whereRaw(
                    "(meta->>'ml_shipping_deadline')::timestamptz::date > ? AND (meta->>'ml_shipping_deadline')::timestamptz::date <= ?",
                    [$tomorrow, $friday]
                ),

            'later' => $query
                ->whereIn('pipeline_status', $expeditionPipeline)
                ->where(fn ($q) => $q
                    ->whereRaw("meta IS NULL OR meta->>'ml_shipping_deadline' IS NULL")
                    ->orWhereRaw("(meta->>'ml_shipping_deadline')::timestamptz::date > ?", [$friday])
                ),

            'partial' => $query->where('pipeline_status', PipelineStatus::PartiallyShipped->value),

            'shipped' => $query->where('pipeline_status', PipelineStatus::Shipped->value),

            default => $query->whereIn('pipeline_status', $expeditionPipeline),
        };

        return $query->orderByRaw("
            CASE
                WHEN meta IS NOT NULL AND meta->>'ml_shipping_deadline' IS NOT NULL
                THEN (meta->>'ml_shipping_deadline')::timestamptz::date
                ELSE '9999-12-31'::date
            END ASC
        ")->orderByDesc('paid_at');
    }

    // ----------------------------------------------------------------
    //  Actions — Volumes
    // ----------------------------------------------------------------

    public function setVolume(int $orderId, int $volumes): void
    {
        $order = Order::where('company_id', Auth::user()->company_id)->findOrFail($orderId);
        $meta  = $order->meta ?? [];
        $meta['expedition_volumes'] = max(1, $volumes);
        $order->update(['meta' => $meta]);

        $this->orderVolumes[$orderId] = $meta['expedition_volumes'];
    }

    // ----------------------------------------------------------------
    //  Actions — Packing
    // ----------------------------------------------------------------

    public function markPacked(int $orderId): void
    {
        $order = Order::where('company_id', Auth::user()->company_id)->findOrFail($orderId);
        $order->update(['pipeline_status' => PipelineStatus::Packed]);
        $this->dispatch('order-updated', id: $orderId);
        session()->flash('success', "Pedido {$order->order_number} marcado como embalado.");
    }

    public function markBulkPacked(): void
    {
        if (empty($this->selectedOrders)) {
            return;
        }

        $orders = Order::where('company_id', Auth::user()->company_id)
            ->whereIn('id', $this->selectedOrders)
            ->get();

        foreach ($orders as $order) {
            $order->update(['pipeline_status' => PipelineStatus::Packed]);
        }

        $count = $orders->count();
        $this->selectedOrders = [];
        $this->selectAll = false;
        $this->showBulkPackModal = false;
        session()->flash('success', "{$count} pedido(s) marcado(s) como embalado(s).");
    }

    public function markShipped(int $orderId): void
    {
        $order = Order::where('company_id', Auth::user()->company_id)->findOrFail($orderId);
        $order->update([
            'status'          => OrderStatus::Shipped,
            'pipeline_status' => PipelineStatus::Shipped,
            'shipped_at'      => now(),
        ]);
        session()->flash('success', "Pedido {$order->order_number} marcado como enviado.");
    }

    // ----------------------------------------------------------------
    //  Actions — Seleção e Romaneio
    // ----------------------------------------------------------------

    public function updatedSelectAll(bool $value): void
    {
        if ($value) {
            $this->selectedOrders = $this->buildQuery()
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->toArray();
        } else {
            $this->selectedOrders = [];
        }
    }

    public function openRomaneioModal(int $mode = 1): void
    {
        if ($mode === 1 && empty($this->selectedOrders)) {
            session()->flash('warning', 'Selecione ao menos um pedido para criar o romaneio.');
            return;
        }

        $this->romaneioMode = $mode;
        $this->romaneioName = 'ROM-' . now()->format('d/m/Y H:i');
        $this->showRomaneioModal = true;
    }

    public function createRomaneio()
    {
        $this->validate(['romaneioName' => 'required|string|max:100']);

        $user = Auth::user();

        $romaneio = Romaneio::create([
            'company_id' => $user->company_id,
            'created_by' => $user->id,
            'name'       => $this->romaneioName,
            'status'     => 'open',
        ]);

        // Modo 1: adiciona pedidos selecionados
        if ($this->romaneioMode === 1 && ! empty($this->selectedOrders)) {
            $orders = Order::where('company_id', $user->company_id)
                ->whereIn('id', $this->selectedOrders)
                ->get();

            foreach ($orders as $order) {
                $volumes = (int) ($order->meta['expedition_volumes'] ?? 1);

                $romaneio->items()->create([
                    'order_id'     => $order->id,
                    'volumes'      => $volumes,
                    'volumes_scanned' => 0,
                    'items_detail' => $order->items->map(fn ($item) => [
                        'order_item_id' => $item->id,
                        'quantity'      => $item->pending_quantity,
                    ])->filter(fn ($d) => $d['quantity'] > 0)->values()->toArray(),
                ]);
            }
        }

        $this->showRomaneioModal = false;
        $this->selectedOrders   = [];
        $this->selectAll        = false;
        $this->romaneioName     = '';

        return redirect()->route('romaneios.show', $romaneio);
    }

    // ----------------------------------------------------------------
    //  Actions — Etiquetas internas (PDF volumes)
    // ----------------------------------------------------------------

    public function printVolumeLabels(): void
    {
        if (empty($this->selectedOrders)) {
            session()->flash('warning', 'Selecione ao menos um pedido para imprimir etiquetas.');
            return;
        }

        $ids = implode(',', $this->selectedOrders);
        $this->redirect(route('romaneios.pdf.etiquetas', ['orders' => $ids]));
    }

    // ----------------------------------------------------------------
    //  Render
    // ----------------------------------------------------------------

    public function render()
    {
        $orders = $this->buildQuery()->paginate(20);

        // Preenche volumes locais a partir do meta dos pedidos
        foreach ($orders as $order) {
            if (! isset($this->orderVolumes[$order->id])) {
                $this->orderVolumes[$order->id] = $order->expedition_volumes;
            }
        }

        $accounts = MarketplaceAccount::where('company_id', Auth::user()->company_id)
            ->active()
            ->orderBy('account_name')
            ->get();

        $tabCounts = $this->getTabCounts();

        return view('livewire.orders.expedition-board', [
            'orders'    => $orders,
            'accounts'  => $accounts,
            'tabCounts' => $tabCounts,
            'types'     => MarketplaceType::cases(),
        ]);
    }
}
