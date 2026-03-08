<?php

namespace App\Http\Controllers;

use App\Enums\PipelineStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Romaneio;
use App\Models\RomaneioItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderDispatchController extends Controller
{
    /**
     * Marca o pedido como Embalado (packed) manualmente, sem conferência de bipe.
     * Pode ser chamado via botão na expedition board.
     */
    public function markPacked(Order $order)
    {
        abort_unless($order->company_id === Auth::user()->company_id, 403);

        $order->update(['pipeline_status' => PipelineStatus::Packed]);

        return back()->with('success', "Pedido {$order->order_number} marcado como embalado.");
    }

    /**
     * Cria um despacho parcial: define quais itens/quantidades serão enviados
     * agora e cria um romaneio avulso ou adiciona ao existente.
     *
     * Payload esperado:
     * {
     *   "items": [{"order_item_id": 1, "quantity": 2}, ...],
     *   "volumes": 1,
     *   "romaneio_id": 5  (opcional — se omitido, cria novo romaneio)
     * }
     */
    public function partial(Request $request, Order $order)
    {
        abort_unless($order->company_id === Auth::user()->company_id, 403);

        $validated = $request->validate([
            'items'                     => 'required|array|min:1',
            'items.*.order_item_id'     => 'required|integer',
            'items.*.quantity'          => 'required|integer|min:1',
            'volumes'                   => 'nullable|integer|min:1',
            'romaneio_id'               => 'nullable|integer|exists:romaneios,id',
        ]);

        DB::transaction(function () use ($validated, $order) {
            // Valida qtds
            $itemsDetail = [];
            foreach ($validated['items'] as $detail) {
                $orderItem = OrderItem::where('order_id', $order->id)->findOrFail($detail['order_item_id']);
                $pending   = $orderItem->pending_quantity;

                if ($detail['quantity'] > $pending) {
                    abort(422, "Quantidade maior que pendente para o item #{$orderItem->id} (pendente: {$pending}).");
                }

                $itemsDetail[] = [
                    'order_item_id' => $orderItem->id,
                    'quantity'      => $detail['quantity'],
                ];
            }

            // Romaneio
            if (! empty($validated['romaneio_id'])) {
                $romaneio = Romaneio::where('company_id', Auth::user()->company_id)
                    ->findOrFail($validated['romaneio_id']);
            } else {
                $romaneio = Romaneio::create([
                    'company_id' => Auth::user()->company_id,
                    'created_by' => Auth::id(),
                    'name'       => "Despacho Parcial #{$order->order_number} " . now()->format('d/m H:i'),
                    'status'     => 'open',
                ]);
            }

            // Cria ou atualiza RomaneioItem
            RomaneioItem::updateOrCreate(
                ['romaneio_id' => $romaneio->id, 'order_id' => $order->id],
                [
                    'volumes'      => $validated['volumes'] ?? 1,
                    'items_detail' => $itemsDetail,
                ]
            );

            // Atualiza pipeline
            $order->update(['pipeline_status' => PipelineStatus::Packed]);
        });

        return back()->with('success', "Despacho parcial configurado para o pedido {$order->order_number}.");
    }

    /**
     * Cancela as unidades restantes de um item (não enviadas).
     */
    public function cancelRemaining(Order $order, OrderItem $item)
    {
        abort_unless($order->company_id === Auth::user()->company_id, 403);
        abort_unless($item->order_id === $order->id, 403);

        $pending = $item->pending_quantity;

        if ($pending <= 0) {
            return back()->with('warning', 'Não há unidades pendentes para cancelar.');
        }

        $item->update(['cancelled_quantity' => $item->quantity - $item->shipped_quantity]);

        $order->recalculatePipelineStatus();

        return back()->with('success', "{$pending} unidade(s) de \"{$item->name}\" marcada(s) como cancelada(s).");
    }
}
