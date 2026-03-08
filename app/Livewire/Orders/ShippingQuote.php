<?php

namespace App\Livewire\Orders;

use App\Models\MelhorEnviosAccount;
use App\Models\Order;
use App\Models\ShipmentLabel;
use App\Services\MelhorEnviosService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Widget de cotação e compra de etiqueta Melhor Envios.
 * Pode ser embutido em qualquer página que receba um Order.
 */
class ShippingQuote extends Component
{
    public Order $order;

    public float $weight   = 0.5;
    public float $width    = 12;
    public float $height   = 4;
    public float $length   = 17;

    public bool   $loading  = false;
    public array  $quotes   = [];
    public string $error    = '';

    public ?string $selectedQuoteKey  = null;
    public bool    $purchasing        = false;
    public bool    $purchased         = false;

    /** @var ShipmentLabel|null */
    public $existingLabel = null;

    public function mount(Order $order): void
    {
        $this->order = $order;

        // Carrega dimensões dos defaults da conta ME vinculada
        $meAccount = $order->marketplaceAccount?->melhorEnviosAccount;
        if ($meAccount) {
            $defaults = $meAccount->default_package ?? [];
            $this->weight = $defaults['weight'] ?? 0.5;
            $this->width  = $defaults['width']  ?? 12;
            $this->height = $defaults['height'] ?? 4;
            $this->length = $defaults['length'] ?? 17;
        }

        $this->existingLabel = ShipmentLabel::where('order_id', $order->id)
            ->whereIn('status', ['purchased', 'printed'])
            ->latest()
            ->first();
    }

    public function calculateQuote(): void
    {
        $this->loading = true;
        $this->quotes  = [];
        $this->error   = '';

        $meAccount = $this->order->marketplaceAccount?->melhorEnviosAccount;

        if (! $meAccount) {
            $this->error   = 'Nenhuma conta Melhor Envios vinculada a este canal.';
            $this->loading = false;
            return;
        }

        try {
            $service = new MelhorEnviosService($meAccount);
            $results = $service->quoteForOrder($this->order, [
                'weight' => $this->weight,
                'width'  => $this->width,
                'height' => $this->height,
                'length' => $this->length,
            ]);

            // Filtra apenas as opções válidas e sem erro
            $this->quotes = collect($results)
                ->filter(fn ($q) => empty($q['error']) && isset($q['price']))
                ->sortBy('price')
                ->values()
                ->toArray();

        } catch (\Throwable $e) {
            $this->error = 'Erro na cotação: ' . $e->getMessage();
        } finally {
            $this->loading = false;
        }
    }

    public function selectQuote(string $key): void
    {
        $this->selectedQuoteKey = $key;
    }

    public function purchaseLabel(): void
    {
        if ($this->selectedQuoteKey === null) {
            return;
        }

        $quote = $this->quotes[$this->selectedQuoteKey] ?? null;

        if (! $quote) {
            return;
        }

        $this->purchasing = true;
        $this->error      = '';

        $meAccount = $this->order->marketplaceAccount?->melhorEnviosAccount;

        if (! $meAccount) {
            $this->error     = 'Conta ME não encontrada.';
            $this->purchasing = false;
            return;
        }

        try {
            $service = new MelhorEnviosService($meAccount);
            $label   = $service->purchaseLabel($this->order, $quote, [
                'weight' => $this->weight,
                'width'  => $this->width,
                'height' => $this->height,
                'length' => $this->length,
            ]);

            $this->existingLabel = $label;
            $this->purchased     = true;
            $this->quotes        = [];

            session()->flash('success', "Etiqueta {$quote['company']['name']} — {$quote['name']} comprada com sucesso!");
        } catch (\Throwable $e) {
            $this->error = 'Erro na compra: ' . $e->getMessage();
        } finally {
            $this->purchasing = false;
        }
    }

    public function render()
    {
        return view('livewire.orders.shipping-quote');
    }
}
