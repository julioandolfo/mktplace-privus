<?php

namespace App\Livewire\Orders;

use App\Enums\PipelineStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class PackingScreen extends Component
{
    public Order $order;

    public string $scanInput     = '';
    public array  $scannedItems  = []; // [order_item_id => qty_scanned]
    public array  $scanHistory   = []; // [{time, name, qty}]
    public string $scanMessage   = '';
    public string $scanStatus    = ''; // success | error | warning

    public function mount(Order $order): void
    {
        abort_unless($order->company_id === Auth::user()->company_id, 403);

        $this->order = $order->load(['items.product.primaryImage', 'items.variant']);

        // Inicializa scanned com zero
        foreach ($this->order->items as $item) {
            $this->scannedItems[$item->id] = 0;
        }
    }

    public function scan(): void
    {
        $input = trim($this->scanInput);
        $this->scanInput = '';

        if (empty($input)) {
            return;
        }

        // Busca produto por EAN ou SKU
        $product = Product::where('ean_gtin', $input)->first()
            ?? Product::where('sku', $input)->first();

        $variant = null;
        if (! $product) {
            $variant = ProductVariant::where('ean_gtin', $input)->first()
                ?? ProductVariant::where('sku', $input)->first();
        }

        // Verifica se pertence ao pedido
        $orderItem = null;
        foreach ($this->order->items as $item) {
            if ($product && $item->product_id === $product->id && ! $item->variant_id) {
                $orderItem = $item;
                break;
            }
            if ($variant && $item->variant_id === $variant->id) {
                $orderItem = $item;
                break;
            }
            if ($product && $item->product_id === $product->id) {
                $orderItem = $item;
                break;
            }
        }

        if (! $orderItem) {
            $this->scanStatus  = 'error';
            $this->scanMessage = "Item \"{$input}\" não encontrado neste pedido!";
            $this->dispatch('scan-error');
            return;
        }

        $alreadyScanned = $this->scannedItems[$orderItem->id] ?? 0;
        $needed         = $orderItem->pending_quantity;

        if ($alreadyScanned >= $needed) {
            $this->scanStatus  = 'warning';
            $this->scanMessage = "\"{$orderItem->name}\" já foi completamente conferido!";
            $this->dispatch('scan-warning');
            return;
        }

        $this->scannedItems[$orderItem->id] = $alreadyScanned + 1;
        $this->scanHistory = array_merge([[
            'time' => now()->format('H:i:s'),
            'name' => $orderItem->name,
            'qty'  => $this->scannedItems[$orderItem->id],
            'of'   => $needed,
        ]], $this->scanHistory);

        $this->scanStatus  = 'success';
        $this->scanMessage = "\"{$orderItem->name}\" conferido ({$this->scannedItems[$orderItem->id]}/{$needed})";
        $this->dispatch('scan-success');
    }

    public function manualCheck(int $itemId): void
    {
        $item = $this->order->items->firstWhere('id', $itemId);
        if (! $item) return;

        $this->scannedItems[$itemId] = $item->pending_quantity;
        $this->scanStatus  = 'success';
        $this->scanMessage = "\"{$item->name}\" marcado manualmente como conferido.";
    }

    public function completePacking(): void
    {
        $this->order->update([
            'pipeline_status' => PipelineStatus::Packed,
        ]);

        session()->flash('success', "Pedido {$this->order->order_number} embalado e conferido com sucesso!");
        $this->redirect(route('expedition.index'));
    }

    public function getIsAllScannedProperty(): bool
    {
        foreach ($this->order->items as $item) {
            if (($this->scannedItems[$item->id] ?? 0) < $item->pending_quantity) {
                return false;
            }
        }
        return true;
    }

    public function getTotalScannedProperty(): int
    {
        return array_sum($this->scannedItems);
    }

    public function getTotalNeededProperty(): int
    {
        return $this->order->items->sum('pending_quantity');
    }

    public function render()
    {
        return view('livewire.orders.packing-screen')
            ->layout('components.layouts.app', [
                'header'   => "Conferência — {$this->order->order_number}",
                'subtitle' => $this->order->customer_name,
            ]);
    }
}
