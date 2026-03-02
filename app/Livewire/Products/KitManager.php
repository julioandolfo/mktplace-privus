<?php

namespace App\Livewire\Products;

use App\Enums\ProductType;
use App\Models\Product;
use App\Models\ProductKitItem;
use Livewire\Component;

class KitManager extends Component
{
    public Product $product;
    public array $components = [];
    public string $searchComponent = '';
    public array $searchResults = [];

    public function mount(Product $product): void
    {
        $this->product = $product;
        $this->loadComponents();
    }

    public function loadComponents(): void
    {
        $this->components = $this->product->kitItems()
            ->with(['componentProduct', 'componentVariant'])
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->component_product_id,
                'variant_id' => $item->component_variant_id,
                'name' => $item->component_name,
                'sku' => $item->componentVariant?->sku ?? $item->componentProduct->sku,
                'quantity' => $item->quantity,
                'price' => (float) ($item->componentVariant?->price ?? $item->componentProduct->price),
            ])
            ->toArray();
    }

    public function updatedSearchComponent(): void
    {
        if (strlen($this->searchComponent) < 2) {
            $this->searchResults = [];
            return;
        }

        $this->searchResults = Product::query()
            ->search($this->searchComponent)
            ->where('id', '!=', $this->product->id)
            ->where('type', '!=', ProductType::Kit)
            ->limit(10)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'price' => (float) $p->price,
            ])
            ->toArray();
    }

    public function addComponent(int $productId): void
    {
        $exists = collect($this->components)->firstWhere('product_id', $productId);
        if ($exists) {
            session()->flash('error', 'Este produto ja faz parte do kit.');
            return;
        }

        $product = Product::findOrFail($productId);

        $item = ProductKitItem::create([
            'kit_product_id' => $this->product->id,
            'component_product_id' => $productId,
            'quantity' => 1,
            'sort_order' => count($this->components),
        ]);

        $this->components[] = [
            'id' => $item->id,
            'product_id' => $productId,
            'variant_id' => null,
            'name' => $product->name,
            'sku' => $product->sku,
            'quantity' => 1,
            'price' => (float) $product->price,
        ];

        $this->searchComponent = '';
        $this->searchResults = [];
    }

    public function updateQuantity(int $index, int $quantity): void
    {
        if ($quantity < 1) {
            return;
        }

        $this->components[$index]['quantity'] = $quantity;

        if (isset($this->components[$index]['id'])) {
            ProductKitItem::find($this->components[$index]['id'])?->update(['quantity' => $quantity]);
        }
    }

    public function removeComponent(int $index): void
    {
        if (isset($this->components[$index]['id'])) {
            ProductKitItem::find($this->components[$index]['id'])?->delete();
        }
        unset($this->components[$index]);
        $this->components = array_values($this->components);
    }

    public function getKitTotalProperty(): float
    {
        return collect($this->components)->sum(fn ($c) => $c['price'] * $c['quantity']);
    }

    public function render()
    {
        return view('livewire.products.kit-manager');
    }
}
