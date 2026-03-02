<?php

namespace App\Livewire\Stock;

use App\Models\Product;
use App\Models\StockItem;
use App\Models\StockLocation;
use Livewire\Component;
use Livewire\WithPagination;

class StockOverview extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filter = 'all'; // all, low, out
    public ?int $locationId = null;

    // Adjustment modal
    public bool $showAdjustModal = false;
    public ?int $adjustStockItemId = null;
    public string $adjustQuantity = '0';
    public string $adjustType = 'adjustment';
    public string $adjustReason = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openAdjustment(int $stockItemId): void
    {
        $this->adjustStockItemId = $stockItemId;
        $this->adjustQuantity = '0';
        $this->adjustType = 'adjustment';
        $this->adjustReason = '';
        $this->showAdjustModal = true;
    }

    public function saveAdjustment(): void
    {
        $this->validate([
            'adjustQuantity' => 'required|integer',
            'adjustReason' => 'required|string|max:255',
        ]);

        $stockItem = StockItem::findOrFail($this->adjustStockItemId);
        $qty = (int) $this->adjustQuantity;

        $stockItem->adjust($qty, $this->adjustType, $this->adjustReason);

        $this->showAdjustModal = false;
        session()->flash('success', 'Estoque ajustado com sucesso.');
    }

    public function render()
    {
        $locations = StockLocation::where('is_active', true)->get();

        $query = StockItem::query()
            ->with(['product', 'variant', 'location'])
            ->whereHas('product', function ($q) {
                if ($this->search) {
                    $q->search($this->search);
                }
            });

        if ($this->locationId) {
            $query->where('stock_location_id', $this->locationId);
        }

        if ($this->filter === 'low') {
            $query->whereRaw('quantity - reserved_quantity <= min_quantity')
                  ->where('min_quantity', '>', 0);
        } elseif ($this->filter === 'out') {
            $query->whereRaw('quantity - reserved_quantity <= 0');
        }

        $items = $query->orderBy('updated_at', 'desc')->paginate(25);

        return view('livewire.stock.stock-overview', [
            'items' => $items,
            'locations' => $locations,
        ]);
    }
}
