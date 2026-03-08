<?php

namespace App\Jobs;

use App\Models\Company;
use App\Models\DesignAssignment;
use App\Models\Order;
use App\Models\OrderTimeline;
use App\Models\User;
use App\Notifications\DesignAssignmentNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Distribui um pedido com produção para o próximo designer disponível.
 * Métodos: round_robin (padrão), random, manual.
 */
class AssignOrderToDesigner implements ShouldQueue
{
    use Queueable, InteractsWithQueue;

    public int $tries   = 3;
    public int $backoff = 10;

    public function __construct(public int $orderId) {}

    public function handle(): void
    {
        $order = Order::with('items.product')->find($this->orderId);

        if (! $order) {
            return;
        }

        // Só processa pedidos com itens que requerem produção
        $requiresProduction = $order->items->contains(
            fn ($item) => $item->product?->requires_production
        );

        if (! $requiresProduction) {
            return;
        }

        $company  = Company::find($order->company_id);
        $settings = $company?->settings ?? [];

        $distribution = $settings['designer_distribution'] ?? 'round_robin';
        $designerIds  = $settings['designer_ids'] ?? [];

        if (empty($designerIds)) {
            Log::warning("AssignOrderToDesigner: nenhum designer configurado para company #{$order->company_id}. Pedido #{$order->order_number} não atribuído.");
            return;
        }

        // Filtra designers ativos (não deletados)
        $activeDesigners = User::whereIn('id', $designerIds)
            ->where('company_id', $order->company_id)
            ->where('role', 'designer')
            ->whereNull('deleted_at')
            ->pluck('id')
            ->toArray();

        if (empty($activeDesigners)) {
            Log::warning("AssignOrderToDesigner: nenhum designer ativo para company #{$order->company_id}.");
            return;
        }

        $designerId = $this->selectDesigner($distribution, $activeDesigners, $settings, $company);

        DB::transaction(function () use ($order, $designerId, $company, $settings, $activeDesigners) {
            // Cria o assignment
            $assignment = DesignAssignment::create([
                'company_id'  => $order->company_id,
                'order_id'    => $order->id,
                'designer_id' => $designerId,
                'status'      => 'pending',
                'assigned_at' => now(),
            ]);

            // Loga na timeline
            $designer = User::find($designerId);
            OrderTimeline::log(
                $order,
                'design_assigned',
                'Design atribuído',
                "Pedido atribuído ao designer {$designer?->name} para desenvolvimento do mockup.",
                ['designer_id' => $designerId, 'designer_name' => $designer?->name, 'assignment_id' => $assignment->id],
                null,
            );

            // Notifica o designer
            try {
                $designer?->notify(new DesignAssignmentNotification($assignment));
            } catch (\Throwable $e) {
                Log::warning("Falha ao notificar designer #{$designerId}: " . $e->getMessage());
            }

            Log::info("AssignOrderToDesigner: pedido #{$order->order_number} → designer #{$designerId} ({$designer?->name}).");
        });
    }

    private function selectDesigner(string $method, array $designerIds, array $settings, Company $company): int
    {
        return match ($method) {
            'random'     => $designerIds[array_rand($designerIds)],
            'round_robin'=> $this->roundRobin($designerIds, $settings, $company),
            default      => $designerIds[0], // manual: primeiro da lista como fallback
        };
    }

    private function roundRobin(array $designerIds, array $settings, Company $company): int
    {
        $pointer = (int) ($settings['rr_pointer'] ?? 0);
        $count   = count($designerIds);

        // Garante que o ponteiro é válido
        if ($pointer >= $count) {
            $pointer = 0;
        }

        $selected = $designerIds[$pointer];

        // Avança o ponteiro para a próxima chamada
        $settings['rr_pointer'] = ($pointer + 1) % $count;
        $company->update(['settings' => $settings]);

        return $selected;
    }
}
