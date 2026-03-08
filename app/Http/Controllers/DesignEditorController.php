<?php

namespace App\Http\Controllers;

use App\Models\DesignAssignment;
use App\Models\DesignFile;
use App\Models\Order;
use App\Models\OrderTimeline;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class DesignEditorController extends Controller
{
    /**
     * Board do designer — lista todos os assignments.
     */
    public function board()
    {
        return view('designer.board');
    }

    /**
     * Abre o editor visual para um assignment.
     */
    public function edit(DesignAssignment $assignment)
    {
        $this->authorizeAssignment($assignment);

        $assignment->load([
            'order.items.product.images',
            'order.items.variant',
            'order.marketplaceAccount',
            'order.customer',
            'designer',
            'files',
        ]);

        // Coleta imagens de produto para uso como fundo no canvas
        $productImages = $assignment->order->items
            ->flatMap(fn ($item) => $item->product?->images ?? collect())
            ->unique('id')
            ->values();

        return view('designer.editor', compact('assignment', 'productImages'));
    }

    /**
     * Salva o estado do canvas (rascunho).
     */
    public function save(Request $request, DesignAssignment $assignment)
    {
        $this->authorizeAssignment($assignment);

        $validated = $request->validate([
            'canvas_state' => 'required|array',
            'notes'        => 'nullable|string|max:1000',
        ]);

        $assignment->update([
            'canvas_state' => $validated['canvas_state'],
            'notes'        => $validated['notes'] ?? $assignment->notes,
            'status'       => $assignment->status === 'pending' ? 'in_progress' : $assignment->status,
            'started_at'   => $assignment->started_at ?? now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Rascunho salvo.']);
    }

    /**
     * Inicia o trabalho no assignment.
     */
    public function start(Request $request, DesignAssignment $assignment)
    {
        $this->authorizeAssignment($assignment);

        $assignment->update([
            'status'     => 'in_progress',
            'started_at' => now(),
        ]);

        OrderTimeline::log(
            $assignment->order_id,
            'design_started',
            'Designer iniciou o trabalho',
            'Designer ' . Auth::user()->name . ' iniciou o desenvolvimento do mockup.',
        );

        return redirect()->route('designer.edit', $assignment)
            ->with('success', 'Trabalho iniciado!');
    }

    /**
     * Finaliza o design — salva PNG gerado, atualiza itens e order.
     */
    public function complete(Request $request, DesignAssignment $assignment)
    {
        $this->authorizeAssignment($assignment);

        $validated = $request->validate([
            'canvas_state'  => 'required|array',
            'mockup_base64' => 'required|string', // PNG base64 do canvas Fabric.js
            'notes'         => 'nullable|string|max:1000',
        ]);

        // Salva o PNG gerado
        $base64 = $validated['mockup_base64'];
        $base64 = preg_replace('/^data:image\/\w+;base64,/', '', $base64);
        $imageData = base64_decode($base64);

        $fileName   = "design-mockup-{$assignment->id}-" . now()->timestamp . '.png';
        $filePath   = "designs/{$assignment->order_id}/{$fileName}";

        Storage::disk('public')->put($filePath, $imageData);
        $fileUrl = Storage::disk('public')->url($filePath);

        // Cria DesignFile de mockup
        $designFile = DesignFile::create([
            'design_assignment_id' => $assignment->id,
            'uploaded_by'          => Auth::id(),
            'file_type'            => 'mockup',
            'file_name'            => $fileName,
            'file_path'            => $filePath,
            'file_url'             => $fileUrl,
            'mime_type'            => 'image/png',
            'file_size'            => strlen($imageData),
            'disk'                 => 'public',
            'is_production_file'   => true,
        ]);

        // Atualiza artwork_url em todos os itens do pedido
        $assignment->order->items()->update(['artwork_url' => $fileUrl]);

        // Atualiza o assignment
        $assignment->update([
            'canvas_state' => $validated['canvas_state'],
            'mockup_url'   => $fileUrl,
            'status'       => 'completed',
            'completed_at' => now(),
            'notes'        => $validated['notes'] ?? $assignment->notes,
        ]);

        // Recalcula pipeline do pedido (vai para InProduction ou ReadyToShip)
        $assignment->order->recalculatePipelineStatus();

        OrderTimeline::log(
            $assignment->order_id,
            'design_completed',
            'Design finalizado',
            'Designer ' . Auth::user()->name . ' finalizou o mockup e gerou os arquivos de produção.',
            ['mockup_url' => $fileUrl, 'file_id' => $designFile->id],
        );

        return response()->json([
            'success'    => true,
            'message'    => 'Design finalizado com sucesso!',
            'mockup_url' => $fileUrl,
            'redirect'   => route('designer.index'),
        ]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function authorizeAssignment(DesignAssignment $assignment): void
    {
        $user = Auth::user();

        // Admin pode tudo; designer só vê os próprios assignments
        if ($user->role !== 'admin' && $assignment->designer_id !== $user->id) {
            abort(403, 'Acesso negado a este assignment.');
        }

        // Garante que pertence à mesma empresa
        abort_unless($assignment->company_id === $user->company_id, 403);
    }
}
