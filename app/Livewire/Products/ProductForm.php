<?php

namespace App\Livewire\Products;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\StockItem;
use App\Models\StockLocation;
use Livewire\Component;
use Livewire\WithFileUploads;

class ProductForm extends Component
{
    use WithFileUploads;

    public ?Product $product = null;

    // Basic info
    public string $name = '';
    public string $sku = '';
    public string $type = 'simple';
    public string $status = 'draft';
    public string $description = '';
    public string $short_description = '';
    public ?int $category_id = null;
    public ?int $brand_id = null;

    // Pricing
    public string $price = '0.00';
    public string $cost_price = '';
    public string $compare_at_price = '';

    // Dimensions
    public string $weight = '';
    public string $width = '';
    public string $height = '';
    public string $length = '';

    // Fiscal
    public string $ncm = '';
    public string $cest = '';
    public string $ean_gtin = '';
    public string $origin = '';

    // SEO
    public string $seo_title = '';
    public string $seo_description = '';

    // Variants
    public array $variants = [];

    // Images
    public $newImages = [];
    public array $existingImages = [];

    // Stock
    public string $initial_stock = '0';
    public ?int $stock_location_id = null;

    public function mount(?Product $product = null): void
    {
        if ($product && $product->exists) {
            $this->product = $product;
            $this->fill($product->only([
                'name', 'sku', 'description', 'short_description',
                'category_id', 'brand_id', 'ncm', 'cest', 'ean_gtin', 'origin',
                'seo_title', 'seo_description',
            ]));
            $this->type = $product->type->value;
            $this->status = $product->status->value;
            $this->price = (string) $product->price;
            $this->cost_price = (string) ($product->cost_price ?? '');
            $this->compare_at_price = (string) ($product->compare_at_price ?? '');
            $this->weight = (string) ($product->weight ?? '');
            $this->width = (string) ($product->width ?? '');
            $this->height = (string) ($product->height ?? '');
            $this->length = (string) ($product->length ?? '');

            $this->variants = $product->variants->map(fn ($v) => [
                'id' => $v->id,
                'sku' => $v->sku,
                'name' => $v->name,
                'attributes' => $v->attributes ?? [],
                'price' => (string) ($v->price ?? ''),
                'cost_price' => (string) ($v->cost_price ?? ''),
                'is_active' => $v->is_active,
            ])->toArray();

            $this->existingImages = $product->images->map(fn ($img) => [
                'id' => $img->id,
                'path' => $img->path,
                'is_primary' => $img->is_primary,
            ])->toArray();
        }

        $this->stock_location_id = StockLocation::where('is_default', true)->first()?->id;
    }

    public function addVariant(): void
    {
        $this->variants[] = [
            'id' => null,
            'sku' => $this->sku . '-' . (count($this->variants) + 1),
            'name' => '',
            'attributes' => [],
            'price' => '',
            'cost_price' => '',
            'is_active' => true,
        ];
    }

    public function removeVariant(int $index): void
    {
        unset($this->variants[$index]);
        $this->variants = array_values($this->variants);
    }

    public function removeExistingImage(int $index): void
    {
        $image = $this->existingImages[$index] ?? null;
        if ($image && isset($image['id'])) {
            ProductImage::find($image['id'])?->delete();
        }
        unset($this->existingImages[$index]);
        $this->existingImages = array_values($this->existingImages);
    }

    public function setAsPrimary(int $index): void
    {
        foreach ($this->existingImages as &$img) {
            $img['is_primary'] = false;
        }
        if (isset($this->existingImages[$index])) {
            $this->existingImages[$index]['is_primary'] = true;
        }
    }

    public function save(): mixed
    {
        $rules = [
            'name' => 'required|string|max:255',
            'sku' => 'required|string|max:100|unique:products,sku' . ($this->product ? ',' . $this->product->id : ''),
            'type' => 'required|in:simple,variable,kit',
            'status' => 'required|in:draft,active,inactive,archived',
            'price' => 'required|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'compare_at_price' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string|max:500',
            'category_id' => 'nullable|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'weight' => 'nullable|numeric|min:0',
            'width' => 'nullable|numeric|min:0',
            'height' => 'nullable|numeric|min:0',
            'length' => 'nullable|numeric|min:0',
            'ncm' => 'nullable|string|max:10',
            'ean_gtin' => 'nullable|string|max:14',
            'variants.*.sku' => 'required_if:type,variable|string|max:100',
            'variants.*.name' => 'required_if:type,variable|string|max:255',
        ];

        $validated = $this->validate($rules);

        $data = collect($validated)->except(['variants', 'initial_stock'])->filter(fn ($v) => $v !== '')->toArray();

        if ($this->product) {
            $this->product->update($data);
            $product = $this->product;
        } else {
            $product = Product::create($data);
        }

        // Save variants
        if ($this->type === 'variable') {
            $existingIds = [];
            foreach ($this->variants as $variantData) {
                if (! empty($variantData['id'])) {
                    $variant = $product->variants()->find($variantData['id']);
                    $variant?->update([
                        'sku' => $variantData['sku'],
                        'name' => $variantData['name'],
                        'attributes' => $variantData['attributes'] ?? [],
                        'price' => $variantData['price'] ?: null,
                        'cost_price' => $variantData['cost_price'] ?: null,
                        'is_active' => $variantData['is_active'],
                    ]);
                    $existingIds[] = $variant->id;
                } else {
                    $variant = $product->variants()->create([
                        'sku' => $variantData['sku'],
                        'name' => $variantData['name'],
                        'attributes' => $variantData['attributes'] ?? [],
                        'price' => $variantData['price'] ?: null,
                        'cost_price' => $variantData['cost_price'] ?: null,
                        'is_active' => $variantData['is_active'] ?? true,
                        'sort_order' => count($existingIds),
                    ]);
                    $existingIds[] = $variant->id;
                }
            }
            $product->variants()->whereNotIn('id', $existingIds)->delete();
        }

        // Handle image uploads
        if ($this->newImages) {
            $sortOrder = count($this->existingImages);
            foreach ($this->newImages as $image) {
                $path = $image->store('products', 'public');
                $product->images()->create([
                    'path' => $path,
                    'sort_order' => $sortOrder++,
                    'is_primary' => $sortOrder === 1 && empty($this->existingImages),
                ]);
            }
        }

        // Update primary image flags
        foreach ($this->existingImages as $imgData) {
            if (isset($imgData['id'])) {
                ProductImage::where('id', $imgData['id'])->update([
                    'is_primary' => $imgData['is_primary'] ?? false,
                ]);
            }
        }

        // Initial stock for new products
        if (! $this->product && $this->initial_stock > 0 && $this->stock_location_id) {
            $stockItem = StockItem::create([
                'product_id' => $product->id,
                'stock_location_id' => $this->stock_location_id,
                'quantity' => (int) $this->initial_stock,
                'min_quantity' => 0,
            ]);
            $stockItem->adjust(
                (int) $this->initial_stock,
                'in',
                'Estoque inicial ao cadastrar produto'
            );
            // Reset since adjust adds on top
            $stockItem->update(['quantity' => (int) $this->initial_stock]);
        }

        session()->flash('success', $this->product ? 'Produto atualizado.' : 'Produto cadastrado.');
        return $this->redirect(route('products.index'), navigate: false);
    }

    public function render()
    {
        return view('livewire.products.product-form', [
            'categories' => Category::orderBy('name')->get(),
            'brands' => Brand::orderBy('name')->get(),
            'statuses' => ProductStatus::cases(),
            'types' => ProductType::cases(),
            'stockLocations' => StockLocation::where('is_active', true)->get(),
        ]);
    }
}
