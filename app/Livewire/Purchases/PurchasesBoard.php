<?php

namespace App\Livewire\Purchases;

use App\Models\Product;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Supplier;
use App\Services\PurchaseService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class PurchasesBoard extends Component
{
    use WithPagination;

    // Filtros
    public string $filterStatus = 'pending';
    public string $search       = '';

    // Modal: nova solicitação manual
    public bool   $showNewForm       = false;
    public string $newTitle          = '';
    public string $newNotes          = '';
    public array  $newItems          = [];

    // Modal: marcar como comprado
    public bool   $showPurchaseForm  = false;
    public ?int   $purchasingId      = null;
    public ?int   $purchaseSupplierId = null;

    // Modal: visualizar detalhes
    public bool   $showDetail        = false;
    public ?int   $detailId          = null;

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    // ── Nova solicitação manual ──────────────────────────────────────

    public function openNewForm(): void
    {
        $this->newTitle = '';
        $this->newNotes = '';
        $this->newItems = [
            ['product_id' => '', 'description' => '', 'quantity' => 1, 'unit_cost' => '', 'link' => ''],
        ];
        $this->showNewForm = true;
    }

    public function addNewItem(): void
    {
        $this->newItems[] = ['product_id' => '', 'description' => '', 'quantity' => 1, 'unit_cost' => '', 'link' => ''];
    }

    public function removeNewItem(int $index): void
    {
        unset($this->newItems[$index]);
        $this->newItems = array_values($this->newItems);
    }

    public function updatedNewItems($value, $key): void
    {
        // Auto-fill description when product_id is selected
        $parts = explode('.', $key);
        if (count($parts) === 2 && $parts[1] === 'product_id' && $value) {
            $product = Product::find($value);
            if ($product) {
                $index = (int) $parts[0];
                $this->newItems[$index]['description'] = $product->name;
                if (! $this->newItems[$index]['unit_cost'] && $product->cost_price) {
                    $this->newItems[$index]['unit_cost'] = number_format($product->cost_price, 2, '.', '');
                }
            }
        }
    }

    public function saveNewRequest(): void
    {
        $this->validate([
            'newTitle'             => 'required|string|max:200',
            'newItems'             => 'required|array|min:1',
            'newItems.*.description' => 'required|string|max:255',
            'newItems.*.quantity'  => 'required|integer|min:1',
        ]);

        $companyId = Auth::user()->company_id;

        $pr = PurchaseRequest::create([
            'company_id' => $companyId,
            'status'     => 'pending',
            'title'      => $this->newTitle,
            'notes'      => $this->newNotes ?: null,
        ]);

        foreach ($this->newItems as $item) {
            PurchaseRequestItem::create([
                'purchase_request_id' => $pr->id,
                'product_id'          => $item['product_id'] ?: null,
                'description'         => $item['description'],
                'quantity'            => $item['quantity'],
                'unit_cost_cents'     => (int) (($item['unit_cost'] ?? 0) * 100),
                'link'                => $item['link'] ?: null,
                'status'              => 'pending',
            ]);
        }

        $pr->recalculateTotal();

        $this->showNewForm = false;
        session()->flash('success', 'Solicitação de compra criada.');
    }

    // ── Marcar como comprado ─────────────────────────────────────────

    public function openPurchaseForm(int $id): void
    {
        $this->purchasingId       = $id;
        $this->purchaseSupplierId = null;
        $this->showPurchaseForm   = true;
    }

    public function confirmPurchase(): void
    {
        $this->validate([
            'purchaseSupplierId' => 'required|exists:suppliers,id',
        ]);

        $pr = PurchaseRequest::where('company_id', Auth::user()->company_id)
            ->findOrFail($this->purchasingId);

        app(PurchaseService::class)->markPurchased($pr, $this->purchaseSupplierId, Auth::id());

        $this->showPurchaseForm = false;
        $this->purchasingId     = null;
        session()->flash('success', 'Compra confirmada.');
    }

    // ── Cancelar ─────────────────────────────────────────────────────

    public function cancelRequest(int $id): void
    {
        $pr = PurchaseRequest::where('company_id', Auth::user()->company_id)->findOrFail($id);
        app(PurchaseService::class)->cancel($pr);
        session()->flash('success', 'Solicitação cancelada.');
    }

    // ── Reabrir ──────────────────────────────────────────────────────

    public function reopenRequest(int $id): void
    {
        $pr = PurchaseRequest::where('company_id', Auth::user()->company_id)->findOrFail($id);
        $pr->update(['status' => 'pending', 'purchased_at' => null, 'purchased_by' => null, 'supplier_id' => null]);
        $pr->items()->update(['status' => 'pending']);
        session()->flash('success', 'Solicitação reaberta.');
    }

    // ── Detalhe ──────────────────────────────────────────────────────

    public function openDetail(int $id): void
    {
        $this->detailId  = $id;
        $this->showDetail = true;
    }

    // ── Render ───────────────────────────────────────────────────────

    public function render()
    {
        $companyId = Auth::user()->company_id;

        $query = PurchaseRequest::where('company_id', $companyId)
            ->with(['order:id,order_number', 'supplier:id,name', 'items', 'purchasedByUser:id,name']);

        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('title', 'like', "%{$this->search}%")
                  ->orWhereHas('order', fn ($oq) => $oq->where('order_number', 'like', "%{$this->search}%"));
            });
        }

        $requests = $query->latest()->paginate(25);

        // Contadores
        $counts = PurchaseRequest::where('company_id', $companyId)
            ->selectRaw("status, COUNT(*) as cnt")
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->all();

        $suppliers = Supplier::forCompany($companyId);

        $products = Product::orderBy('name')
            ->select('id', 'name', 'sku', 'cost_price')
            ->limit(500)
            ->get();

        // Detalhe
        $detailRequest = $this->detailId
            ? PurchaseRequest::with(['items.product:id,name,sku', 'order:id,order_number', 'supplier', 'purchasedByUser:id,name'])
                ->find($this->detailId)
            : null;

        return view('livewire.purchases.purchases-board', [
            'requests'      => $requests,
            'counts'        => $counts,
            'suppliers'     => $suppliers,
            'products'      => $products,
            'detailRequest' => $detailRequest,
        ]);
    }
}
