<?php

namespace App\Livewire\Orders;

use App\Enums\MarketplaceType;
use App\Enums\OrderStatus;
use App\Enums\PipelineStatus;
use App\Models\ExpeditionOperator;
use App\Models\MarketplaceAccount;
use App\Models\MarketplaceListing;
use App\Models\Order;
use App\Models\OrderTimeline;
use App\Models\Romaneio;
use App\Models\ShipmentLabel;
use App\Services\ExpeditionBonusService;
use App\Services\MelhorEnviosService;
use App\Services\WebmaniaService;
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
    public string $filterStep   = '';

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

    // ── Modal de Conferência de Embalagem ────────────────────────────────────
    public bool   $showPackingModal = false;
    public ?int   $packingOrderId   = null;
    public array  $packingItems     = []; // [{id, name, sku, quantity, img_url}]
    public array  $packingChecks    = []; // "item_id" => qty_confirmada
    public string $packingNotes     = '';

    // ── Modal de Emissão NF-e ────────────────────────────────────────────────
    public bool   $showNfeModal     = false;
    public ?int   $nfeOrderId       = null;
    public string $nfeMethod        = 'webmaniabr'; // 'native' | 'webmaniabr'
    public string $nfeAccountMethod = 'webmaniabr'; // metodo configurado na conta
    public bool   $nfeHasWebmania   = false;
    public bool   $nfeIsMarketplaceNative = false; // conta suporta emissao nativa
    public array  $nfeFiscalPending = []; // itens do pedido sem dados fiscais [{item_id, ml_item_id, name, sku}]
    public array  $nfeFiscalForm    = []; // form data por ml_item_id => {ncm, origin_type, origin_detail, cost, sku, title}
    public bool   $nfeFiscalChecking = false;
    public string $nfeNatureOp      = 'Venda';
    public string $nfeInfoFisco     = '';
    public string $nfeInfoConsumer  = '';
    public bool   $nfeHomologation  = false;
    public bool   $nfeLoading       = false;

    // ── Modal de Cotação Frete (genéricos/WooCommerce) ───────────────────────
    public bool   $showShippingModal  = false;
    public ?int   $shippingOrderId    = null;
    public float  $shippingWeight     = 0.5;
    public float  $shippingWidth      = 12;
    public float  $shippingHeight     = 4;
    public float  $shippingLength     = 17;
    public array  $shippingQuotes     = [];
    public ?string $shippingSelectedKey = null;
    public string $shippingError      = '';
    public bool   $shippingLoading    = false;
    public bool   $shippingPurchasing = false;

    // ── Operador de Expedição selecionado ──────────────────────────────────
    public ?int $selectedOperatorId = null;

    protected $queryString = [
        'activeTab'     => ['except' => 'today'],
        'search'        => ['except' => ''],
        'filterAccount' => ['except' => ''],
        'filterStep'    => ['except' => ''],
    ];

    public function updatingSearch(): void    { $this->resetPage(); }
    public function updatingActiveTab(): void { $this->resetPage(); $this->selectedOrders = []; $this->selectAll = false; }
    public function updatingFilterAccount(): void { $this->resetPage(); }
    public function updatingFilterStep(): void { $this->resetPage(); }

    // ----------------------------------------------------------------
    //  DB-agnostic helpers: suporta SQLite e PostgreSQL
    // ----------------------------------------------------------------

    protected function isPostgres(): bool
    {
        return DB::getDriverName() === 'pgsql';
    }

    protected function deadlineDateSql(): string
    {
        if ($this->isPostgres()) {
            return "(meta->>'ml_shipping_deadline')::timestamptz::date";
        }
        return "SUBSTR(json_extract(meta, '$.ml_shipping_deadline'), 1, 10)";
    }

    protected function deadlineNotNullSql(): string
    {
        if ($this->isPostgres()) {
            return "meta->>'ml_shipping_deadline' IS NOT NULL";
        }
        return "json_extract(meta, '$.ml_shipping_deadline') IS NOT NULL";
    }

    protected function deadlineIsNullSql(): string
    {
        if ($this->isPostgres()) {
            return "meta->>'ml_shipping_deadline' IS NULL";
        }
        return "json_extract(meta, '$.ml_shipping_deadline') IS NULL";
    }

    protected function deadlineOrderBySql(): string
    {
        if ($this->isPostgres()) {
            return "(meta->>'ml_shipping_deadline')::timestamptz ASC NULLS LAST, paid_at DESC NULLS LAST";
        }
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
    //  Query helper — busca expandida (número, cliente, produto, SKU)
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
            ->with(['items.product.primaryImage', 'items.product.images', 'items.variant', 'marketplaceAccount', 'invoices', 'shipmentLabels'])
            ->when($this->search, function ($q) {
                $term = $this->search;
                $like = $this->isPostgres() ? 'ilike' : 'like';
                $q->where(function ($sub) use ($term, $like) {
                    $sub->where('order_number', $like, "%{$term}%")
                        ->orWhere('customer_name', $like, "%{$term}%")
                        ->orWhere('customer_email', $like, "%{$term}%")
                        ->orWhere('customer_document', $like, "%{$term}%")
                        ->orWhere('tracking_code', $like, "%{$term}%")
                        ->orWhereHas('items', fn ($iq) =>
                            $iq->where('name', $like, "%{$term}%")
                               ->orWhere('sku', $like, "%{$term}%")
                        );
                });
            })
            ->when($this->filterType, fn ($q) => $q->whereHas('marketplaceAccount', fn ($mq) =>
                $mq->where('marketplace_type', $this->filterType)
            ))
            ->when($this->filterStep, function ($q) {
                match ($this->filterStep) {
                    // A conferir / embalar
                    'to_pack' => $q->whereIn('pipeline_status', [
                        PipelineStatus::ReadyToShip->value,
                        PipelineStatus::Packing->value,
                    ]),

                    // Embalado (aguardando próximo passo)
                    'packed' => $q->where('pipeline_status', PipelineStatus::Packed->value),

                    // Precisa emitir NF-e (embalado, sem NF-e aprovada)
                    'to_invoice' => $q->where('pipeline_status', PipelineStatus::Packed->value)
                        ->whereDoesntHave('invoices', fn ($iq) =>
                            $iq->where('status', 'approved')
                        )
                        ->whereDoesntHave('invoices', fn ($iq) =>
                            $iq->whereIn('status', ['pending', 'processing'])
                        ),

                    // NF-e em processamento
                    'invoicing' => $q->whereHas('invoices', fn ($iq) =>
                        $iq->whereIn('status', ['pending', 'processing'])
                    ),

                    // NF-e aprovada, pronto para despachar
                    'to_ship' => $q->where('pipeline_status', PipelineStatus::Packed->value)
                        ->whereHas('invoices', fn ($iq) =>
                            $iq->where('status', 'approved')
                        ),

                    default => null,
                };
            });

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

        $opName = $this->operatorName();
        OrderTimeline::log($order, 'shipped', "Pedido marcado como enviado" . ($opName ? " (por {$opName})" : ''), null, [
            'operator_id'   => $this->selectedOperatorId,
            'operator_name' => $opName,
        ]);

        // Atribuir pontos de despacho
        $bonusPoints = 0;
        if ($this->selectedOperatorId) {
            $bonusPoints = app(ExpeditionBonusService::class)->awardShippingPoints($order, $this->selectedOperatorId);
        }

        $bonusMsg = $bonusPoints > 0 ? " (+{$bonusPoints} pts)" : '';
        session()->flash('success', "Pedido {$order->order_number} marcado como enviado.{$bonusMsg}");
    }

    /**
     * Marca pedidos em lote como enviados.
     */
    public function markBulkShipped(): void
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
            $order->update([
                'status'          => OrderStatus::Shipped,
                'pipeline_status' => PipelineStatus::Shipped,
                'shipped_at'      => now(),
            ]);
            OrderTimeline::log($order, 'shipped', "Pedido marcado como enviado (lote)");
        }

        $count = $orders->count();
        $this->selectedOrders = [];
        $this->selectAll = false;
        session()->flash('success', "{$count} pedido(s) marcado(s) como enviado(s).");
    }

    // ----------------------------------------------------------------
    //  Conferência de Embalagem (Modal)
    // ----------------------------------------------------------------

    public function openPackingModal(int $orderId): void
    {
        // Fecha outros modais que possam estar abertos
        $this->closeAllModals();

        $order = $this->scopedOrder($orderId);
        $order->load(['items.product.primaryImage', 'items.product.images']);

        // Carrega última conferência para pré-preencher quantidades
        $lastCheck = OrderTimeline::where('order_id', $orderId)
            ->where('event_type', 'packing_checked')
            ->latest('happened_at')
            ->first();

        $previousChecks = $lastCheck
            ? collect($lastCheck->data['items'] ?? [])
                ->keyBy('item_id')
                ->map(fn ($i) => (int) ($i['qty_confirmed'] ?? 0))
                ->all()
            : [];

        $this->packingItems  = [];
        $this->packingChecks = [];

        foreach ($order->items as $item) {
            $imgUrl = $item->artwork_url
                ?? $item->product?->primaryImage?->url
                ?? $item->product?->images->first()?->url;

            $this->packingItems[] = [
                'id'       => $item->id,
                'name'     => $item->name,
                'sku'      => $item->sku,
                'quantity' => $item->quantity,
                'img_url'  => $imgUrl,
            ];

            $this->packingChecks[(string) $item->id] = $previousChecks[$item->id] ?? $item->quantity;
        }

        $this->packingOrderId  = $orderId;
        $this->packingNotes    = '';
        $this->showPackingModal = true;
        $this->preselectOperator();
        $this->resetErrorBag();
    }

    public function confirmPacking(bool $force = false): void
    {
        $order = $this->scopedOrder($this->packingOrderId);
        $order->load('items');

        $itemsData      = [];
        $totalOrdered   = 0;
        $totalConfirmed = 0;
        $allConfirmed   = true;

        foreach ($order->items as $item) {
            $qtyConfirmed = max(0, (int) ($this->packingChecks[(string) $item->id] ?? 0));
            $difference   = $item->quantity - $qtyConfirmed;

            $itemsData[] = [
                'item_id'       => $item->id,
                'name'          => $item->name,
                'sku'           => $item->sku,
                'qty_ordered'   => $item->quantity,
                'qty_confirmed' => $qtyConfirmed,
                'difference'    => $difference,
            ];

            $totalOrdered   += $item->quantity;
            $totalConfirmed += $qtyConfirmed;

            if ($qtyConfirmed < $item->quantity) {
                $allConfirmed = false;
            }
        }

        if (! $force && ! $allConfirmed) {
            $missing = $totalOrdered - $totalConfirmed;
            $this->addError('packingChecks', "Faltam {$missing} unidade(s). Clique em 'Forcar (parcial)' para confirmar mesmo assim, ou ajuste as quantidades.");
            return;
        }

        $status = $allConfirmed ? 'complete' : 'partial';
        $title  = $allConfirmed
            ? "Embalagem conferida - {$totalConfirmed}/{$totalOrdered} unid."
            : "Conferencia parcial - {$totalConfirmed}/{$totalOrdered} unid.";

        $opName = $this->operatorName();

        OrderTimeline::log(
            $order,
            'packing_checked',
            $title . ($opName ? " (por {$opName})" : ''),
            $this->packingNotes,
            [
                'status'          => $status,
                'total_ordered'   => $totalOrdered,
                'total_confirmed' => $totalConfirmed,
                'items'           => $itemsData,
                'forced'          => $force,
                'operator_id'     => $this->selectedOperatorId,
                'operator_name'   => $opName,
            ]
        );

        $order->update(['pipeline_status' => PipelineStatus::Packed]);

        // Atribuir pontos de embalagem
        $bonusPoints = 0;
        if ($this->selectedOperatorId) {
            $bonusPoints = app(ExpeditionBonusService::class)->awardPackingPoints($order, $this->selectedOperatorId);
        }

        $this->showPackingModal = false;
        $this->packingOrderId  = null;
        $this->packingChecks   = [];
        $this->packingItems    = [];
        $this->packingNotes    = '';
        $this->resetErrorBag();

        $bonusMsg = $bonusPoints > 0 ? " (+{$bonusPoints} pts)" : '';
        session()->flash('success', "Pedido {$order->order_number} embalado. {$title}{$bonusMsg}");
    }

    public function closePackingModal(): void
    {
        $this->showPackingModal = false;
        $this->packingOrderId  = null;
        $this->packingChecks   = [];
        $this->packingItems    = [];
        $this->packingNotes    = '';
        $this->resetErrorBag();
    }

    // ----------------------------------------------------------------
    //  NF-e Emission (Modal) — via Webmaniabr
    // ----------------------------------------------------------------

    public function openNfeModal(int $orderId): void
    {
        $this->closeAllModals();

        $order = $this->scopedOrder($orderId);
        $account = $order->marketplaceAccount;

        if (! $account) {
            session()->flash('error', 'Este pedido nao possui conta de marketplace vinculada.');
            return;
        }

        $accountMethod = $account->nfe_method ?? 'webmaniabr';
        $hasWebmania   = (bool) $account->webmania_account_id;
        $isMl          = $account->marketplace_type === \App\Enums\MarketplaceType::MercadoLivre;
        $isShopee      = $account->marketplace_type === \App\Enums\MarketplaceType::Shopee;
        $supportsNative = $isMl || $isShopee;

        // Se metodo e 'none', nao faz nada
        if ($accountMethod === 'none') {
            session()->flash('info', 'Emissao de NF-e desabilitada para a conta "' . ($account->account_name ?? 'sem nome') . '".');
            return;
        }

        // Se metodo e webmaniabr exclusivo e nao tem webmania vinculada
        if ($accountMethod === 'webmaniabr' && ! $hasWebmania) {
            session()->flash('error', 'Nenhuma conta Webmaniabr vinculada a conta "' . ($account->account_name ?? 'sem nome') . '". Vincule em Configuracoes > Contas & Expedicao.');
            return;
        }

        // Se metodo e nativo mas marketplace nao suporta
        if ($accountMethod === 'native' && ! $supportsNative) {
            session()->flash('error', 'Emissao nativa nao disponivel para este tipo de marketplace. Configure Webmaniabr em Configuracoes > Contas & Expedicao.');
            return;
        }

        // Determina qual metodo mostrar como padrao
        $defaultMethod = $accountMethod;
        if ($accountMethod === 'both') {
            $defaultMethod = $supportsNative ? 'native' : 'webmaniabr';
        }

        $this->nfeOrderId          = $orderId;
        $this->nfeMethod           = $defaultMethod;
        $this->nfeAccountMethod    = $accountMethod;
        $this->nfeHasWebmania      = $hasWebmania;
        $this->nfeIsMarketplaceNative = $supportsNative;
        $this->nfeFiscalPending    = [];
        $this->nfeFiscalForm       = [];
        $this->nfeFiscalChecking   = false;
        $this->nfeNatureOp         = 'Venda';
        $this->nfeInfoFisco        = '';
        $this->nfeInfoConsumer     = '';
        $this->nfeHomologation     = false;
        $this->nfeLoading          = false;
        $this->showNfeModal        = true;
        $this->preselectOperator();
        $this->resetErrorBag();

        // Para emissao nativa ML, verificar dados fiscais dos itens
        if ($isMl && in_array($defaultMethod, ['native', 'both'])) {
            $this->checkFiscalData($order, $account);
        }
    }

    /**
     * Verifica dados fiscais dos itens do pedido via API do ML.
     * Itens sem dados fiscais sao listados em $nfeFiscalPending.
     */
    protected function checkFiscalData(Order $order, MarketplaceAccount $account): void
    {
        $this->nfeFiscalChecking = true;
        $order->load(['items.product']);

        try {
            $service = new \App\Services\Marketplaces\MercadoLivreService($account);
            $pending = [];
            $form    = [];

            // Coletar ml_item_ids unicos dos itens do pedido
            $checkedItems = [];
            foreach ($order->items as $item) {
                $mlItemId = $item->meta['ml_item_id'] ?? null;
                if (! $mlItemId || isset($checkedItems[$mlItemId])) {
                    continue;
                }
                $checkedItems[$mlItemId] = true;

                try {
                    $fiscal = $service->getItemFiscalData($mlItemId);
                    $canInvoice = $service->canInvoiceItem($mlItemId);

                    if (empty($fiscal) || empty($fiscal['sku']) || ! ($canInvoice['can_invoice'] ?? false)) {
                        $product = $item->product;
                        $pending[] = [
                            'ml_item_id' => $mlItemId,
                            'name'       => $item->name,
                            'sku'        => $item->sku ?? $product?->sku ?? '',
                            'item_id'    => $item->id,
                        ];
                        $form[$mlItemId] = [
                            'sku'           => $item->sku ?? $product?->sku ?? $mlItemId,
                            'title'         => $item->name ?? '',
                            'cost'          => (string) ($product?->cost ?? $item->unit_price ?? '0'),
                            'ncm'           => $product?->ncm ?? '',
                            'origin_type'   => 'reseller',
                            'origin_detail' => '0',
                        ];
                    }
                } catch (\Throwable $e) {
                    Log::warning("checkFiscalData item {$mlItemId}: " . $e->getMessage());
                    // Considerar como pendente em caso de erro
                    $product = $item->product;
                    $pending[] = [
                        'ml_item_id' => $mlItemId,
                        'name'       => $item->name,
                        'sku'        => $item->sku ?? $product?->sku ?? '',
                        'item_id'    => $item->id,
                    ];
                    $form[$mlItemId] = [
                        'sku'           => $item->sku ?? $product?->sku ?? $mlItemId,
                        'title'         => $item->name ?? '',
                        'cost'          => (string) ($product?->cost ?? $item->unit_price ?? '0'),
                        'ncm'           => $product?->ncm ?? '',
                        'origin_type'   => 'reseller',
                        'origin_detail' => '0',
                    ];
                }
            }

            $this->nfeFiscalPending = $pending;
            $this->nfeFiscalForm    = $form;
        } catch (\Throwable $e) {
            Log::error("checkFiscalData order #{$order->id}: " . $e->getMessage());
        } finally {
            $this->nfeFiscalChecking = false;
        }
    }

    /**
     * Salva dados fiscais pendentes via API do ML e re-verifica.
     * Chamado pelo formulário de dados fiscais no modal de NF-e.
     */
    public function saveFiscalData(): void
    {
        if (! $this->nfeOrderId || empty($this->nfeFiscalPending)) {
            return;
        }

        $this->nfeLoading = true;
        $order   = $this->scopedOrder($this->nfeOrderId);
        $account = $order->marketplaceAccount;

        if (! $account) {
            $this->nfeLoading = false;
            return;
        }

        $service = new \App\Services\Marketplaces\MercadoLivreService($account);
        $errors  = [];

        foreach ($this->nfeFiscalPending as $item) {
            $mlItemId = $item['ml_item_id'];
            $formData = $this->nfeFiscalForm[$mlItemId] ?? null;

            if (! $formData) {
                continue;
            }

            // Validar campos obrigatorios
            if (empty($formData['ncm']) || strlen($formData['ncm']) !== 8) {
                $errors[] = "{$item['name']}: NCM deve ter 8 digitos.";
                continue;
            }
            if (empty($formData['cost']) || (float) $formData['cost'] <= 0) {
                $errors[] = "{$item['name']}: Custo deve ser maior que zero.";
                continue;
            }

            $payload = [
                'sku'              => $formData['sku'],
                'title'            => $formData['title'],
                'type'             => 'single',
                'cost'             => (float) $formData['cost'],
                'measurement_unit' => 'UN',
                'tax_information'  => [
                    'ncm'           => $formData['ncm'],
                    'origin_type'   => $formData['origin_type'] ?? 'reseller',
                    'origin_detail' => $formData['origin_detail'] ?? '0',
                ],
            ];

            try {
                $service->createFiscalData($payload);
                $service->linkFiscalDataToItem($formData['sku'], $mlItemId);
            } catch (\Throwable $e) {
                // Se ja existe, tentar atualizar
                if (str_contains($e->getMessage(), '400') || str_contains($e->getMessage(), 'already')) {
                    try {
                        $service->updateFiscalData($formData['sku'], $payload);
                    } catch (\Throwable $e2) {
                        $errors[] = "{$item['name']}: " . $e2->getMessage();
                    }
                } else {
                    $errors[] = "{$item['name']}: " . $e->getMessage();
                }
            }
        }

        if (! empty($errors)) {
            $this->addError('nfe', implode("\n", $errors));
            $this->nfeLoading = false;
            return;
        }

        // Re-verificar dados fiscais
        $this->checkFiscalData($order, $account);
        $this->nfeLoading = false;

        if (empty($this->nfeFiscalPending)) {
            session()->flash('success', 'Dados fiscais salvos com sucesso. Agora voce pode emitir a NF-e.');
        }
    }

    public function emitNfe(string $action = 'emit'): void
    {
        if (! $this->nfeOrderId) {
            return;
        }

        // Se tem dados fiscais pendentes na emissao nativa, bloquear
        if ($this->nfeMethod === 'native' && ! empty($this->nfeFiscalPending)) {
            $this->addError('nfe', 'Preencha os dados fiscais pendentes antes de emitir a NF-e.');
            return;
        }

        // Se metodo nativo, delegar para emissao nativa do marketplace
        if ($this->nfeMethod === 'native') {
            $this->emitNfeNative();
            return;
        }

        // Metodo Webmaniabr
        $this->emitNfeWebmaniabr($action);
    }

    /**
     * Emissao nativa via Faturador integrado do Mercado Livre.
     * A API do ML emite a NF-e diretamente na SEFAZ — não precisa de chave de acesso.
     *
     * @see https://developers.mercadolivre.com.br/pt_br/api-fiscal-faturamento-de-venda
     */
    protected function emitNfeNative(): void
    {
        $this->nfeLoading = true;

        $order   = $this->scopedOrder($this->nfeOrderId);
        $account = $order->marketplaceAccount;

        try {
            $isMl     = $account->marketplace_type === \App\Enums\MarketplaceType::MercadoLivre;
            $isShopee = $account->marketplace_type === \App\Enums\MarketplaceType::Shopee;

            if ($isMl) {
                $mlOrderId = $order->external_id ?? ($order->meta['ml_order_id'] ?? null);

                if (! $mlOrderId) {
                    $this->addError('nfe', 'Pedido sem ID externo do Mercado Livre. Nao e possivel emitir NF-e.');
                    $this->nfeLoading = false;
                    return;
                }

                $service = new \App\Services\Marketplaces\MercadoLivreService($account);
                $result  = $service->emitInvoice([$mlOrderId]);

                // Extrair dados da resposta do Faturador ML
                $invoiceId   = $result['id'] ?? null;
                $accessKey   = $result['access_key'] ?? null;
                $invoiceNum  = $result['invoice_number'] ?? null;
                $status      = $result['status'] ?? 'processing';

                // Salvar invoice no banco
                \App\Models\Invoice::updateOrCreate(
                    ['order_id' => $order->id, 'external_id' => $invoiceId ?? $mlOrderId],
                    [
                        'company_id'        => $order->company_id,
                        'status'            => $status === 'authorized' ? 'approved' : 'processing',
                        'type'              => 'nfe',
                        'number'            => $invoiceNum,
                        'access_key'        => $accessKey,
                        'customer_name'     => $order->customer_name ?? '',
                        'customer_document' => $order->customer_document,
                        'total'             => $order->total ?? 0,
                        'total_products'    => $order->subtotal ?? 0,
                        'total_shipping'    => $order->shipping_cost ?? 0,
                        'total_discount'    => $order->discount ?? 0,
                        'total_tax'         => 0,
                        'nature_operation'  => 'Venda',
                        'meta'              => ['ml_invoice' => $result, 'emission_method' => 'native_ml'],
                    ]
                );

                $this->showNfeModal = false;
                $this->nfeOrderId   = null;
                $this->nfeLoading   = false;

                $msg = $invoiceNum
                    ? "NF-e nº {$invoiceNum} emitida com sucesso via Faturador Mercado Livre."
                    : "NF-e enviada para emissao via Faturador Mercado Livre (status: {$status}).";
                session()->flash('success', $msg);
            } else {
                $this->addError('nfe', 'Emissao nativa ainda nao implementada para este marketplace.');
                $this->nfeLoading = false;
            }
        } catch (\Throwable $e) {
            Log::error("emitNfeNative order #{$order->id}: " . $e->getMessage());
            $this->addError('nfe', 'Erro na emissao nativa: ' . $e->getMessage());
            $this->nfeLoading = false;
        }
    }

    /**
     * Emissao via Webmaniabr (fluxo existente).
     */
    protected function emitNfeWebmaniabr(string $action = 'emit'): void
    {
        $this->nfeLoading = true;

        $order = $this->scopedOrder($this->nfeOrderId);
        $account = $order->marketplaceAccount;

        if (! $account?->webmania_account_id) {
            $this->addError('nfe', 'Conta Webmaniabr nao vinculada a esta conta de marketplace.');
            $this->nfeLoading = false;
            return;
        }

        $order->load(['items.product', 'company', 'marketplaceAccount.webmaniaAccount']);
        $webmaniaAccount = $account->webmaniaAccount;

        try {
            $service = new WebmaniaService($webmaniaAccount);

            $overrides = [];
            if (! empty($this->nfeNatureOp)) {
                $overrides['natureza_operacao'] = $this->nfeNatureOp;
            }
            if (! empty($this->nfeInfoFisco)) {
                $overrides['informacoes_adicionais']['fisco'] = $this->nfeInfoFisco;
            }
            if (! empty($this->nfeInfoConsumer)) {
                $overrides['informacoes_adicionais']['contribuinte'] = $this->nfeInfoConsumer;
            }
            if ($this->nfeHomologation) {
                $overrides['homologacao'] = true;
            }

            if ($action === 'preview') {
                $result = $service->preview($order, $overrides);
                $this->showNfeModal = false;
                $this->nfeOrderId   = null;
                $this->nfeLoading   = false;
                session()->flash('success', 'Pre-visualizacao gerada. PDF: ' . ($result['danfe'] ?? 'N/A'));
            } else {
                $result = $service->emit($order, $overrides);
                $this->showNfeModal = false;
                $this->nfeOrderId   = null;
                $this->nfeLoading   = false;
                session()->flash('success', "NF-e no {$result['numero']} emitida com sucesso.");
            }
        } catch (\Throwable $e) {
            Log::error("emitNfe order #{$order->id}: " . $e->getMessage());
            $this->addError('nfe', 'Erro na emissao: ' . $e->getMessage());
            $this->nfeLoading = false;
        }
    }

    public function closeNfeModal(): void
    {
        $this->showNfeModal        = false;
        $this->nfeOrderId          = null;
        $this->nfeMethod           = 'webmaniabr';
        $this->nfeAccountMethod    = 'webmaniabr';
        $this->nfeHasWebmania      = false;
        $this->nfeIsMarketplaceNative = false;
        $this->nfeFiscalPending    = [];
        $this->nfeFiscalForm       = [];
        $this->nfeFiscalChecking   = false;
        $this->nfeLoading          = false;
        $this->resetErrorBag();
    }

    // ----------------------------------------------------------------
    //  Shipping Quote Modal — Melhor Envios (for generic/WooCommerce)
    // ----------------------------------------------------------------

    public function openShippingModal(int $orderId): void
    {
        $this->closeAllModals();

        $order   = $this->scopedOrder($orderId);
        $account = $order->marketplaceAccount;

        $meAccount = $account?->melhorEnviosAccount;
        if (! $meAccount) {
            session()->flash('error', 'Nenhuma conta Melhor Envios vinculada a este canal.');
            return;
        }

        $defaults = $meAccount->default_package ?? [];
        $this->shippingOrderId    = $orderId;
        $this->shippingWeight     = $defaults['weight'] ?? 0.5;
        $this->shippingWidth      = $defaults['width']  ?? 12;
        $this->shippingHeight     = $defaults['height'] ?? 4;
        $this->shippingLength     = $defaults['length'] ?? 17;
        $this->shippingQuotes     = [];
        $this->shippingSelectedKey = null;
        $this->shippingError      = '';
        $this->shippingLoading    = false;
        $this->shippingPurchasing = false;
        $this->showShippingModal  = true;
        $this->preselectOperator();

        // Verifica se ja tem etiqueta
        $existing = ShipmentLabel::where('order_id', $orderId)
            ->whereIn('status', ['purchased', 'printed'])
            ->latest()
            ->first();

        if ($existing) {
            session()->flash('info', "Este pedido ja possui etiqueta: {$existing->carrier} - {$existing->service} (R\$ " . number_format($existing->cost, 2, ',', '.') . ")");
        }
    }

    public function calculateShippingQuote(): void
    {
        $this->shippingLoading = true;
        $this->shippingQuotes  = [];
        $this->shippingError   = '';

        $order     = $this->scopedOrder($this->shippingOrderId);
        $meAccount = $order->marketplaceAccount?->melhorEnviosAccount;

        if (! $meAccount) {
            $this->shippingError   = 'Conta Melhor Envios nao encontrada.';
            $this->shippingLoading = false;
            return;
        }

        try {
            $service = new MelhorEnviosService($meAccount);
            $results = $service->quoteForOrder($order, [
                'weight' => $this->shippingWeight,
                'width'  => $this->shippingWidth,
                'height' => $this->shippingHeight,
                'length' => $this->shippingLength,
            ]);

            $this->shippingQuotes = collect($results)
                ->filter(fn ($q) => empty($q['error']) && isset($q['price']))
                ->sortBy('price')
                ->values()
                ->toArray();

        } catch (\Throwable $e) {
            $this->shippingError = 'Erro na cotacao: ' . $e->getMessage();
        } finally {
            $this->shippingLoading = false;
        }
    }

    public function selectShippingQuote(string $key): void
    {
        $this->shippingSelectedKey = $key;
    }

    public function purchaseShippingLabel(): void
    {
        if ($this->shippingSelectedKey === null) {
            return;
        }

        $quote = $this->shippingQuotes[$this->shippingSelectedKey] ?? null;
        if (! $quote) {
            return;
        }

        $this->shippingPurchasing = true;
        $this->shippingError      = '';

        $order     = $this->scopedOrder($this->shippingOrderId);
        $meAccount = $order->marketplaceAccount?->melhorEnviosAccount;

        if (! $meAccount) {
            $this->shippingError     = 'Conta ME nao encontrada.';
            $this->shippingPurchasing = false;
            return;
        }

        try {
            $service = new MelhorEnviosService($meAccount);
            $label   = $service->purchaseLabel($order, $quote, [
                'weight' => $this->shippingWeight,
                'width'  => $this->shippingWidth,
                'height' => $this->shippingHeight,
                'length' => $this->shippingLength,
            ]);

            // Atualiza tracking_code no pedido
            if ($label->tracking_code) {
                $order->update(['tracking_code' => $label->tracking_code]);
            }

            $this->showShippingModal  = false;
            $this->shippingOrderId    = null;
            $this->shippingPurchasing = false;

            session()->flash('success', "Etiqueta {$quote['company']['name']} - {$quote['name']} comprada com sucesso!");

        } catch (\Throwable $e) {
            $this->shippingError     = 'Erro na compra: ' . $e->getMessage();
            $this->shippingPurchasing = false;
        }
    }

    public function closeShippingModal(): void
    {
        $this->showShippingModal  = false;
        $this->shippingOrderId    = null;
        $this->shippingQuotes     = [];
        $this->shippingSelectedKey = null;
        $this->shippingError      = '';
        $this->shippingLoading    = false;
        $this->shippingPurchasing = false;
    }

    // ----------------------------------------------------------------
    //  Helper: pré-seleciona operador padrão
    // ----------------------------------------------------------------

    protected function preselectOperator(): void
    {
        if (! $this->selectedOperatorId) {
            $default = ExpeditionOperator::defaultForCompany(Auth::user()?->company_id);
            $this->selectedOperatorId = $default?->id;
        }
    }

    protected function operatorName(): ?string
    {
        if (! $this->selectedOperatorId) {
            return null;
        }
        return ExpeditionOperator::find($this->selectedOperatorId)?->name;
    }

    // ----------------------------------------------------------------
    //  Helper: fecha todos os modais
    // ----------------------------------------------------------------

    protected function closeAllModals(): void
    {
        $this->closePackingModal();
        $this->showNfeModal      = false;
        $this->nfeOrderId        = null;
        $this->showShippingModal = false;
        $this->shippingOrderId   = null;
        $this->showRomaneioModal = false;
        $this->showBulkPackModal = false;
    }

    // ----------------------------------------------------------------
    //  Actions — Selecao e Romaneio
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

        $this->redirectRoute('romaneios.board', ['romaneio' => $romaneio->id], navigate: true);
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
     */
    public function revertToReadyToShip(int $orderId): void
    {
        $order = $this->scopedOrder($orderId);
        $order->update([
            'status'          => OrderStatus::Paid,
            'pipeline_status' => PipelineStatus::Packed,
            'shipped_at'      => null,
        ]);

        OrderTimeline::log($order, 'status_changed', "Pedido reaberto para re-envio");

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
     * Na primeira renderizacao, se a aba atual nao tem pedidos,
     * seleciona automaticamente a primeira aba que tenha.
     */
    protected function autoSelectTab(array $tabCounts): void
    {
        if (($tabCounts[$this->activeTab] ?? 0) > 0) {
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

        // Mapa external_id -> MarketplaceListing para imagens e links internos
        $mlItemIds = $orders->flatMap(fn ($o) => $o->items->pluck('meta')
            ->map(fn ($m) => $m['ml_item_id'] ?? null)
            ->filter()
        )->unique()->values()->all();

        $listingsMap = collect();
        if (! empty($mlItemIds)) {
            $listingsMap = MarketplaceListing::whereIn('external_id', $mlItemIds)
                ->select(['id', 'external_id', 'title', 'meta'])
                ->get()
                ->keyBy('external_id');
        }

        // Mapa order_id -> ultimo evento de conferencia de embalagem
        $orderIds = $orders->pluck('id')->all();
        $packingHistoryMap = collect();
        if (! empty($orderIds)) {
            $packingHistoryMap = OrderTimeline::whereIn('order_id', $orderIds)
                ->where('event_type', 'packing_checked')
                ->with('performer:id,name')
                ->latest('happened_at')
                ->get()
                ->groupBy('order_id')
                ->map(fn ($events) => $events->first());
        }

        // Mapa order_id -> ShipmentLabel ativa
        $labelsMap = collect();
        if (! empty($orderIds)) {
            $labelsMap = ShipmentLabel::whereIn('order_id', $orderIds)
                ->whereIn('status', ['purchased', 'printed'])
                ->latest()
                ->get()
                ->keyBy('order_id');
        }

        $accountQuery = MarketplaceAccount::active()->orderBy('account_name');
        if ($cid = Auth::user()?->company_id) {
            $accountQuery->where('company_id', $cid);
        }

        $expeditionOperators = ExpeditionOperator::forCompany(Auth::user()?->company_id);

        // Dados de metas e bonificação
        $bonusService = app(ExpeditionBonusService::class);
        $companyId = Auth::user()?->company_id;
        $dailyGoal     = $companyId ? $bonusService->calculateDailyGoal($companyId) : null;
        $operatorRanking = $companyId ? $bonusService->operatorRanking($companyId) : [];
        $monthProgress  = $companyId ? $bonusService->monthProgress($companyId) : null;

        return view('livewire.orders.expedition-board', [
            'orders'              => $orders,
            'accounts'            => $accountQuery->get(),
            'tabCounts'           => $tabCounts,
            'types'               => MarketplaceType::cases(),
            'listingsMap'         => $listingsMap,
            'packingHistoryMap'   => $packingHistoryMap,
            'labelsMap'           => $labelsMap,
            'expeditionOperators' => $expeditionOperators,
            'dailyGoal'           => $dailyGoal,
            'operatorRanking'     => $operatorRanking,
            'monthProgress'       => $monthProgress,
        ]);
    }
}
