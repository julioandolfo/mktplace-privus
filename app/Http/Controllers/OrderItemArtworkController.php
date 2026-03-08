<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderItemArtworkController extends Controller
{
    /**
     * Salva a URL de arte e produção de um item do pedido.
     * PATCH /orders/{order}/items/{item}/artwork
     */
    public function update(Request $request, Order $order, OrderItem $item)
    {
        abort_unless($order->company_id === Auth::user()->company_id, 403);
        abort_unless($item->order_id === $order->id, 403);

        $validated = $request->validate([
            'artwork_url'      => 'nullable|url|max:2000',
            'artwork_approved' => 'nullable|boolean',
            'production_notes' => 'nullable|string|max:500',
            'production_status'=> 'nullable|in:not_required,pending,in_progress,complete',
        ]);

        $updateData = [];

        if (array_key_exists('artwork_url', $validated)) {
            $updateData['artwork_url'] = $validated['artwork_url'] ?: null;
        }
        if (isset($validated['artwork_approved'])) {
            $updateData['artwork_approved'] = (bool) $validated['artwork_approved'];
        }
        if (array_key_exists('production_notes', $validated)) {
            $updateData['production_notes'] = $validated['production_notes'];
        }
        if (isset($validated['production_status'])) {
            $updateData['production_status'] = $validated['production_status'];
            if ($validated['production_status'] === 'complete') {
                $updateData['production_completed_at'] = now();
            }
        }

        $item->update($updateData);
        $order->recalculatePipelineStatus();

        return back()->with('success', 'Item atualizado.');
    }
}
