<?php

namespace App\Observers;

use App\Models\DesignAssignment;
use App\Models\OrderTimeline;

class DesignAssignmentObserver
{
    public function updated(DesignAssignment $assignment): void
    {
        // Quando designer inicia o trabalho
        if ($assignment->wasChanged('started_at') && $assignment->started_at) {
            OrderTimeline::log(
                $assignment->order_id,
                'design_started',
                'Designer iniciou o trabalho',
                'Designer ' . ($assignment->designer->name ?? 'N/A') . ' abriu o editor e começou a trabalhar no mockup.',
                ['designer_id' => $assignment->designer_id],
                $assignment->designer_id,
            );
        }

        // Quando design é concluído
        if ($assignment->wasChanged('status') && $assignment->status === 'completed') {
            // Aciona recalculatePipelineStatus no pedido
            if ($assignment->relationLoaded('order')) {
                $assignment->order->recalculatePipelineStatus();
            } else {
                $assignment->load('order');
                $assignment->order->recalculatePipelineStatus();
            }
        }

        // Quando colocado em revisão
        if ($assignment->wasChanged('status') && $assignment->status === 'revision') {
            OrderTimeline::log(
                $assignment->order_id,
                'design_revision',
                'Design enviado para revisão',
                'O design foi marcado para revisão.' . ($assignment->revision_notes ? " Observação: {$assignment->revision_notes}" : ''),
                [],
                null,
            );
        }
    }
}
