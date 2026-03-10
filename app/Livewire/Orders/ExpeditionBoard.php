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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
    //  DB-agnostic helpers: suporta SQLite e PostgreSQL
    // ----------------------------------------------------------------

    protected function isPostgres(): bool
    {
        return DB::getDriverName() === 'pgsql';
    }

    /**
     * Extrai a data (YYYY-MM-DD) de um campo JSON de deadline.
     * SQLite: SUBSTR(json_extract(meta,'$.ml_shipping_deadline'), 1, 10)
     * PostgreSQL: (meta->>'ml_shipping_deadline')::timestamptz::date
     */
    protected function deadlineDateSql(): string
    {
        if ($this->isPostgres()) {
            return "(meta->>'ml_shipping_deadline')::timestamptz::date";
        }

        // SQLite: ISO string "2026-03-11T23:59:59.000-03:00" → substr = "2026-03-11"
        return "SUBSTR(json_extract(meta, '$.ml_shipping_deadline'), 1, 10)";
    }

    /** Null check para o campo deadline, compatível com ambos os bancos */
    protected function deadlineNotNullSql(): string
    {
        if ($this->isPostgres()) {
            return "meta->>'ml_shipping_deadline' IS NOT NULL";
        }
        return "json_extract(meta, '$.ml_shipping_deadline') IS NOT NULL";
    }

    /** IS NULL para o campo deadline (inverso de notNull) */
    protected function deadlineIsNullSql(): string
    {
        if ($this->isPostgres()) {
            return "meta->>'ml_shipping_deadline' IS NULL";
        }
        return "json_extract(meta, '$.ml_shipping_deadline') IS NULL";
    }

    /**
     * Retorna o SQL completo para ordenação por deadline + paid_at.
     * Usa NULLS LAST para colocar pedidos sem deadline no final.
     * Elimina CASE WHEN para evitar incompatibilidade de tipos no PostgreSQL.
     */
    protected function deadlineOrderBySql(): string
    {
        if ($this->isPostgres()) {
            return "(meta->>'ml_shipping_deadline')::timestamptz ASC NULLS LAST, paid_at DESC NULLS LAST";
        }

        // SQLite 3.30+ suporta NULLS LAST
        return "json_extract(meta, '$.ml_shipping_deadline') ASC NULLS LAST, paid_at DESC NULLS LAST";
    }

    // ----------------------------------------------------------------
    //  Base query com filtro de company_id seguro
    // ----------------------------------------------------------------

    protected function baseQuery()
    {
        $query = Order::query();

        $companyId = Auth::user()?->company_id;
        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        return $query->when($this->filterAccount, fn ($q) => $q->where('marketplace_account_id', $this->filterAccount));
    }

    // ----------------------------------------------------------------
    //  Tab counts
    // ----------------------------------------------------------------

    protected function getTabCounts(): array
    {
        $today    = now()->toDateString();
        $tomorrow = now()->addDay()->toDateString();
        $friday   = now()->endOfWeek(Carbon::FRIDAY)->toDateString();

        $expeditionPipeline = array_map(fn ($s) => $s->value, PipelineStatus::expeditionStatuses());
        $dl   = $this->deadlineDateSql();
        $notnull = $this->deadlineNotNullSql();

        return [
            'in_production' => (clone $this->baseQuery())
                ->whereIn('pipeline_status', array_map(fn ($s) => $s->value, PipelineStatus::productionStatuses()))
                ->count(),

            'overdue' => (clone $this->baseQuery())
                ->whereIn('pipeline_status', $expeditionPipeline)
                ->whereRaw($notnull)
                ->whereRaw("{$dl} < ?", [$today])
                ->count(),

            'today' => (clone $this->baseQuery())
                ->whereIn('pipeline_status', $expeditionPipeline)
                ->whereRaw($notnull)
                ->whereRaw("{$dl} = ?", [$today])
                ->count(),

            'tomorrow' => (clone $this->baseQuery())
                ->whereIn('pipeline_status', $expeditionPipeline)
                ->whereRaw($notnull)
                ->whereRaw("{$dl} = ?", [$tomorrow])
                ->count(),

            'this_week' => (clone $this->baseQuery())
                ->whereIn('pipeline_status', $expeditionPipeline)
                ->whereRaw($notnull)
                ->whereRaw("{$dl} > ? AND {$dl} <= ?", [$tomorrow, $friday])
                ->count(),

            'later' => (clone $this->baseQuery())
                ->whereIn('pipeline_status', $expeditionPipeline)
                ->where(fn ($q) => $q
                    ->whereRaw($this->deadlineIsNullSql())
                    ->orWhereRaw("{$dl} > ?", [$friday])
                )
                ->count(),

            'partial' => (clone $this->baseQuery())
                ->where('pipeline_status', PipelineStatus::PartiallyShipped->value)
                ->count(),

            'shipped' => (clone $this->baseQuery())
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
        $dl      = $this->deadlineDateSql();
        $notnull = $this->deadlineNotNullSql();

        $query = $this->baseQuery()
            ->with(['items.product.primaryImage', 'items.product.images', 'items.variant', 'marketplaceAccount', 'invoices'])
            ->when($this->search, fn ($q) => $q->search($this->search))
            ->when($this->filterType, fn ($q) => $q->whereHas('marketplaceAccount', fn ($mq) =>
                $mq->where('marketplace_type', $this->filterType)
            ));

        match ($this->activeTab) {
            'in_production' => $query->whereIn('pipeline_status',
                array_map(fn ($s) => $s->value, PipelineStatus::productionStatuses())
            ),

            'overdue' => $query
                ->whereIn('pipeline_status', $expeditionPipeline)
                ->whereRaw($notnull)
                ->whereRaw("{$dl} < ?", [$today]),

            'today' => $query
                ->whereIn('pipeline_status', $expeditionPipeline)
                ->whereRaw($notnull)
                ->whereRaw("{$dl} = ?", [$today]),

            'tomorrow' => $query
                ->whereIn('pipeline_status', $expeditionPipeline)
                ->whereRaw($notnull)
                ->whereRaw("{$dl} = ?", [$tomorrow]),

            'this_week' => $query
                ->whereIn('pipeline_status', $expeditionPipeline)
                ->whereRaw($notnull)
                ->whereRaw("{$dl} > ? AND {$dl} <= ?", [$tomorrow, $friday]),

            'later' => $query
                ->whereIn('pipeline_status', $expeditionPipeline)
                ->where(fn ($q) => $q
                    ->whereRaw($this->deadlineIsNullSql())
                    ->orWhereRaw("{$dl} > ?", [$friday])
                ),

            'partial' => $query->where('pipeline_status', PipelineStatus::PartiallyShipped->value),

            'shipped' => $query->where('pipeline_status', PipelineStatus::Shipped->value),

            default => $query->whereIn('pipeline_status', $expeditionPipeline),
        };

        return $query->orderByRaw($this->deadlineOrderBySql());
    }

    // ----------------------------------------------------------------
    //  Actions — Volumes
    // ----------------------------------------------------------------

    public function setVolume(int $orderId, int $volumes): void
    {
        $order = $this->scopedOrder($orderId);
        $meta  = $order->meta ?? [];
        $meta['expedition_volumes'] = max(1, $volumes);
        $order->update(['meta' => $meta]);

        $this->orderVolumes[$orderId] = $meta['expedition_volumes'];
    }

    // ----------------------------------------------------------------
    //  Actions — Packing
    // ----------------------------------------------------------------

    protected function scopedOrder(int $orderId): Order
    {
        $q = Order::query();
        if ($cid = Auth::user()?->company_id) {
            $q->where('company_id', $cid);
        }
        return $q->findOrFail($orderId);
    }

    public function markPacked(int $orderId): void
    {
        $order = $this->scopedOrder($orderId);
        $order->update(['pipeline_status' => PipelineStatus::Packed]);
        $this->dispatch('order-updated', id: $orderId);
        session()->flash('success', "Pedido {$order->order_number} marcado como embalado.");
    }

    public function markBulkPacked(): void
    {
        if (empty($this->selectedOrders)) {
            return;
        }

        $q = Order::whereIn('id', $this->selectedOrders);
        if ($cid = Auth::user()?->company_id) {
            $q->where('company_id', $cid);
        }
        $orders = $q->get();

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
        $order = $this->scopedOrder($orderId);
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

    /**
     * Cria um romaneio vazio (modo bipagem) sem abrir modal.
     * Nome gerado automaticamente e redireciona direto para o board de bipagem.
     */
    public function createEmptyRomaneio(): void
    {
        $user = Auth::user();

        $romaneio = Romaneio::create([
            'company_id' => $user?->company_id,
            'created_by' => $user->id,
            'name'       => 'ROM-' . now()->format('d/m/Y H:i'),
            'status'     => 'open',
        ]);

        $this->redirect(route('romaneios.board', $romaneio));
    }

    public function createRomaneio()
    {
        $this->validate(['romaneioName' => 'required|string|max:100']);

        $user = Auth::user();

        $romaneio = Romaneio::create([
            'company_id' => $user?->company_id,
            'created_by' => $user->id,
            'name'       => $this->romaneioName,
            'status'     => 'open',
        ]);

        if ($this->romaneioMode === 1 && ! empty($this->selectedOrders)) {
            $q = Order::whereIn('id', $this->selectedOrders);
            if ($user->company_id) {
                $q->where('company_id', $user->company_id);
            }
            $orders = $q->with('items')->get();

            foreach ($orders as $order) {
                $volumes = (int) ($order->meta['expedition_volumes'] ?? 1);

                $romaneio->items()->create([
                    'order_id'        => $order->id,
                    'volumes'         => $volumes,
                    'volumes_scanned' => 0,
                    'items_detail'    => $order->items->map(fn ($item) => [
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

        return $this->redirect(route('romaneios.show', $romaneio));
    }

    /**
     * Reverte pedido enviado para "Pronto para Envio" — permite reprocessar
     * (gerar nova etiqueta MelhorEnvios, corrigir endereço, etc.)
     */
    public function revertToReadyToShip(int $orderId): void
    {
        $order = $this->scopedOrder($orderId);
        $order->update([
            'status'          => OrderStatus::Paid,
            'pipeline_status' => PipelineStatus::Packed,
            'shipped_at'      => null,
        ]);
        session()->flash('success', "Pedido {$order->order_number} reaberto para re-envio.");
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
        $this->redirect(route('romaneios.etiquetas-avulso', ['orders' => $ids]));
    }

    // ----------------------------------------------------------------
    //  Render
    // ----------------------------------------------------------------

    /**
     * Na primeira renderização, se a aba atual não tem pedidos,
     * seleciona automaticamente a primeira aba que tenha.
     */
    protected function autoSelectTab(array $tabCounts): void
    {
        if ($tabCounts[$this->activeTab] ?? 0 > 0) {
            return;
        }

        $priority = ['overdue', 'today', 'tomorrow', 'this_week', 'later', 'in_production', 'partial', 'shipped'];

        foreach ($priority as $tab) {
            if (($tabCounts[$tab] ?? 0) > 0) {
                $this->activeTab = $tab;
                return;
            }
        }
    }

    public function render()
    {
        $tabCounts = $this->getTabCounts();

        $this->autoSelectTab($tabCounts);

        try {
            $orders = $this->buildQuery()->paginate(20);
        } catch (\Throwable $e) {
            Log::channel('expedition')->error('ExpeditionBoard query error', [
                'tab'     => $this->activeTab,
                'message' => $e->getMessage(),
                'line'    => $e->getFile() . ':' . $e->getLine(),
            ]);
            $orders = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20);
        }

        foreach ($orders as $order) {
            if (! isset($this->orderVolumes[$order->id])) {
                $this->orderVolumes[$order->id] = $order->expedition_volumes;
            }
        }

        $accountQuery = MarketplaceAccount::active()->orderBy('account_name');
        if ($cid = Auth::user()?->company_id) {
            $accountQuery->where('company_id', $cid);
        }

        return view('livewire.orders.expedition-board', [
            'orders'    => $orders,
            'accounts'  => $accountQuery->get(),
            'tabCounts' => $tabCounts,
            'types'     => MarketplaceType::cases(),
        ]);
    }
}
