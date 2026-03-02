<?php

namespace App\Livewire\Products;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\Category;
use App\Models\Product;
use Livewire\Component;
use Livewire\WithPagination;

class ProductList extends Component
{
    use WithPagination;

    public string $search = '';
    public string $status = '';
    public string $type = '';
    public string $category = '';
    public string $sortField = 'created_at';
    public string $sortDirection = 'desc';

    protected $queryString = [
        'search' => ['except' => ''],
        'status' => ['except' => ''],
        'type' => ['except' => ''],
        'category' => ['except' => ''],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function deleteProduct(int $id): void
    {
        Product::findOrFail($id)->delete();
        session()->flash('success', 'Produto removido com sucesso.');
    }

    public function duplicateProduct(int $id): void
    {
        $original = Product::with('variants')->findOrFail($id);
        $new = $original->replicate();
        $new->name = $original->name . ' (Copia)';
        $new->sku = $original->sku . '-copy-' . time();
        $new->slug = null;
        $new->status = ProductStatus::Draft;
        $new->save();

        foreach ($original->variants as $variant) {
            $newVariant = $variant->replicate();
            $newVariant->product_id = $new->id;
            $newVariant->sku = $variant->sku . '-copy-' . time();
            $newVariant->save();
        }

        session()->flash('success', 'Produto duplicado com sucesso.');
    }

    public function render()
    {
        $products = Product::query()
            ->with(['category', 'brand', 'primaryImage', 'stockItems'])
            ->when($this->search, fn ($q) => $q->search($this->search))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->type, fn ($q) => $q->where('type', $this->type))
            ->when($this->category, fn ($q) => $q->where('category_id', $this->category))
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(25);

        return view('livewire.products.product-list', [
            'products' => $products,
            'categories' => Category::orderBy('name')->get(),
            'statuses' => ProductStatus::cases(),
            'types' => ProductType::cases(),
        ]);
    }
}
