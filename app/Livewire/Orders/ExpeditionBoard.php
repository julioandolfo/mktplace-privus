<?php

namespace App\Livewire\Orders;

use App\Enums\MarketplaceType;
use App\Enums\OrderStatus;
use App\Enums\PipelineStatus;
use App\Models\MarketplaceAccount;
use App\Models\MarketplaceListing;
use App\Models\Order;
use App\Models\OrderTimeline;
use App\Models\Romaneio;
use App\Models\ShipmentLabel;
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

        OrderTimeline::log($order, 'shipped', "Pedido marcado como enviado");

        session()->flash('success', "Pedido {$order->order_number} marcado como enviado.");
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

        OrderTimeline::log(
            $order,
            'packing_checked',
            $title,
            $this->packingNotes,
            [
                'status'          => $status,
                'total_ordered'   => $totalOrdered,
                'total_confirmed' => $totalConfirmed,
                'items'           => $itemsData,
                'forced'          => $force,
            ]
        );

        $order->update(['pipeline_status' => PipelineStatus::Packed]);

        $this->showPackingModal = false;
        $this->packingOrderId  = null;
        $this->packingChecks   = [];
        $this->packingItems    = [];
        $this->packingNotes    = '';
        $this->resetErrorBag();

        session()->flash('success', "Pedido {$order->order_number} embalado. {$title}");
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

        if (! $account?->webmania_account_id) {
            session()->flash('error', 'Nenhuma conta Webmaniabr vinculada a este canal.');
            return;
        }

        $this->nfeOrderId      = $orderId;
        $this->nfeNatureOp     = 'Venda';
        $this->nfeInfoFisco    = '';
        $this->nfeInfoConsumer = '';
        $this->nfeHomologation = false;
        $this->nfeLoading      = false;
        $this->showNfeModal    = true;
        $this->resetErrorBag();
    }

    public function emitNfe(string $action = 'emit'): void
    {
        if (! $this->nfeOrderId) {
            return;
        }

        $this->nfeLoading = true;

        $order = $this->scopedOrder($this->nfeOrderId);
        $account = $order->marketplaceAccount;

        if (! $account?->webmania_account_id) {
            $this->addError('nfe', 'Conta Webmaniabr nao vinculada.');
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
        $this->showNfeModal = false;
        $this->nfeOrderId   = null;
        $this->nfeLoading   = false;
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

        return view('livewire.orders.expedition-board', [
            'orders'            => $orders,
            'accounts'          => $accountQuery->get(),
            'tabCounts'         => $tabCounts,
            'types'             => MarketplaceType::cases(),
            'listingsMap'       => $listingsMap,
            'packingHistoryMap' => $packingHistoryMap,
            'labelsMap'         => $labelsMap,
        ]);
    }
}
