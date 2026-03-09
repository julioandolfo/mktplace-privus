<?php

namespace App\Livewire\Romaneios;

use App\Models\Order;
use App\Models\Romaneio;
use App\Models\RomaneioItem;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class RomaneioBoard extends Component
{
    public Romaneio $romaneio;

    public string $scanInput    = '';
    public string $scanMessage  = '';
    public string $scanStatus   = ''; // success | error | warning | info
    public array  $scanHistory  = [];

    public bool   $showCloseModal   = false;
    public bool   $forceClose       = false;

    public function mount(Romaneio $romaneio): void
    {
        abort_unless($romaneio->company_id === Auth::user()->company_id, 403);
        $this->romaneio = $romaneio->load(['items.order']);

        if ($romaneio->isClosed()) {
            session()->flash('warning', 'Este romaneio já está fechado.');
        }
    }

    public function scan(): void
    {
        $input = trim($this->scanInput);
        $this->scanInput = '';

        if (empty($input)) {
            return;
        }

        // Tenta decodificar JSON do QR code da etiqueta de volume
        $payload = null;
        if (str_starts_with($input, '{')) {
            $payload = json_decode($input, true);
        }

        // Modo QR: {"order_id":X,"vol":Y,"total":Z}
        if ($payload && isset($payload['order_id'])) {
            $this->processQrScan($payload);
            return;
        }

        // Modo texto: order_number digitado manualmente
        $this->processOrderNumberScan($input);
    }

    private function processQrScan(array $payload): void
    {
        $orderId = $payload['order_id'] ?? null;
        $vol     = $payload['vol'] ?? 1;
        $total   = $payload['total'] ?? 1;

        $romaneioItem = $this->romaneio->items->firstWhere('order_id', $orderId);

        // Modo 2: pedido não estava no romaneio — adiciona
        if (! $romaneioItem) {
            $order = Order::where('company_id', Auth::user()->company_id)->find($orderId);

            if (! $order) {
                $this->scanFeedback('error', "Pedido ID #{$orderId} não encontrado no sistema.");
                return;
            }

            $romaneioItem = RomaneioItem::create([
                'romaneio_id'     => $this->romaneio->id,
                'order_id'        => $orderId,
                'volumes'         => $total,
                'volumes_scanned' => 0,
                'items_detail'    => $order->items->map(fn ($i) => [
                    'order_item_id' => $i->id,
                    'quantity'      => $i->pending_quantity,
                ])->filter(fn ($d) => $d['quantity'] > 0)->values()->toArray(),
            ]);

            $this->romaneio->load(['items.order']);
            $this->scanFeedback('info', "Pedido {$order->order_number} adicionado ao romaneio (Modo 2).");
        }

        // Verifica se este volume já foi bipado
        if ($romaneioItem->volumes_scanned >= $vol) {
            $order = Order::find($orderId);
            $this->scanFeedback('warning', "Volume {$vol} do pedido {$order?->order_number} já foi bipado!");
            return;
        }

        // Registra scan
        $complete = $romaneioItem->scanVolume();
        $this->romaneio->load(['items.order']);

        $order = Order::find($orderId);
        $msg = "Vol {$vol}/{$total} — Pedido {$order?->order_number} (" .
               $romaneioItem->fresh()->volumes_scanned . "/" . $romaneioItem->volumes . " caixas)";

        if ($complete) {
            $this->scanFeedback('success', $msg . " ✓ COMPLETO");
        } else {
            $this->scanFeedback('success', $msg);
        }

        $this->addToHistory($msg, $order?->order_number ?? '');
    }

    private function processOrderNumberScan(string $orderNumber): void
    {
        $order = Order::where('company_id', Auth::user()->company_id)
            ->where('order_number', $orderNumber)
            ->first();

        if (! $order) {
            $this->scanFeedback('error', "Pedido \"{$orderNumber}\" não encontrado.");
            return;
        }

        $romaneioItem = $this->romaneio->items->firstWhere('order_id', $order->id);

        if (! $romaneioItem) {
            // Adiciona ao romaneio (Modo 2)
            $romaneioItem = RomaneioItem::create([
                'romaneio_id'     => $this->romaneio->id,
                'order_id'        => $order->id,
                'volumes'         => $order->expedition_volumes,
                'volumes_scanned' => 0,
                'items_detail'    => $order->items->map(fn ($i) => [
                    'order_item_id' => $i->id,
                    'quantity'      => $i->pending_quantity,
                ])->filter(fn ($d) => $d['quantity'] > 0)->values()->toArray(),
            ]);

            $this->romaneio->load(['items.order']);
        }

        if ($romaneioItem->isComplete()) {
            $this->scanFeedback('warning', "Pedido {$orderNumber} já está completamente bipado!");
            return;
        }

        $complete = $romaneioItem->scanVolume();
        $this->romaneio->load(['items.order']);
        $romaneioItem->refresh();

        $msg = "Pedido {$orderNumber} — vol {$romaneioItem->volumes_scanned}/{$romaneioItem->volumes}";
        $this->scanFeedback($complete ? 'success' : 'success', $msg . ($complete ? ' ✓ COMPLETO' : ''));
        $this->addToHistory($msg, $orderNumber);
    }

    private function scanFeedback(string $status, string $message): void
    {
        $this->scanStatus  = $status;
        $this->scanMessage = $message;
        $this->dispatch("scan-{$status}");
    }

    private function addToHistory(string $message, string $orderNumber): void
    {
        array_unshift($this->scanHistory, [
            'time'    => now()->format('H:i:s'),
            'message' => $message,
            'order'   => $orderNumber,
        ]);

        // Mantém apenas últimos 30
        $this->scanHistory = array_slice($this->scanHistory, 0, 30);
    }

    public function closeRomaneio(): void
    {
        if ($this->romaneio->isClosed()) {
            return;
        }

        \App\Jobs\CloseRomaneio::dispatch($this->romaneio->id, Auth::id());

        $this->showCloseModal = false;
        session()->flash('success', 'Romaneio sendo fechado. Pedidos serão atualizados em instantes.');

        $this->redirect(route('romaneios.index'));
    }

    public function render()
    {
        $this->romaneio->load(['items.order']);

        $totalPedidos    = $this->romaneio->items->count();
        $pedidosCompletos = $this->romaneio->items->filter(fn ($i) => $i->isComplete())->count();
        $totalVolumes    = $this->romaneio->total_volumes;
        $bipados         = $this->romaneio->total_volumes_scanned;

        return view('livewire.romaneios.romaneio-board', compact(
            'totalPedidos', 'pedidosCompletos', 'totalVolumes', 'bipados'
        ))->layout('layouts.app', [
            'header'   => "Bipagem — {$this->romaneio->name}",
            'subtitle' => "{$pedidosCompletos}/{$totalPedidos} pedidos concluídos",
        ]);
    }
}
