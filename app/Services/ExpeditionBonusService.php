<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PipelineStatus;
use App\Models\ExpeditionBonusConfig;
use App\Models\ExpeditionGoal;
use App\Models\ExpeditionGoalOperator;
use App\Models\ExpeditionPointsLog;
use App\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ExpeditionBonusService
{
    /**
     * Atribui pontos de embalagem/conferência ao operador.
     */
    public function awardPackingPoints(Order $order, int $operatorId): int
    {
        $config = $this->getConfig($order->company_id);
        if (! $config?->is_active) {
            return 0;
        }

        // Remove pontos anteriores de packing deste pedido/operador (re-conferência)
        ExpeditionPointsLog::where('order_id', $order->id)
            ->where('event_type', 'packing')
            ->delete();

        $totalPoints = 0;
        $order->loadMissing('items.product');

        foreach ($order->items as $item) {
            $points = $this->pointsForItem($item, $config);
            if ($points <= 0) {
                continue;
            }

            ExpeditionPointsLog::create([
                'company_id'    => $order->company_id,
                'operator_id'   => $operatorId,
                'order_id'      => $order->id,
                'order_item_id' => $item->id,
                'event_type'    => 'packing',
                'points'        => $points,
                'reference_date' => now()->toDateString(),
            ]);

            $totalPoints += $points;
        }

        return $totalPoints;
    }

    /**
     * Atribui pontos de despacho ao operador.
     */
    public function awardShippingPoints(Order $order, int $operatorId): int
    {
        $config = $this->getConfig($order->company_id);
        if (! $config?->is_active) {
            return 0;
        }

        // Remove pontos anteriores de shipping deste pedido (reenvio)
        ExpeditionPointsLog::where('order_id', $order->id)
            ->where('event_type', 'shipping')
            ->delete();

        $totalPoints = 0;
        $order->loadMissing('items.product');

        foreach ($order->items as $item) {
            $points = $this->pointsForItem($item, $config);
            if ($points <= 0) {
                continue;
            }

            ExpeditionPointsLog::create([
                'company_id'    => $order->company_id,
                'operator_id'   => $operatorId,
                'order_id'      => $order->id,
                'order_item_id' => $item->id,
                'event_type'    => 'shipping',
                'points'        => $points,
                'reference_date' => now()->toDateString(),
            ]);

            $totalPoints += $points;
        }

        return $totalPoints;
    }

    /**
     * Calcula pontos de um item: (pontos_do_produto) × quantidade
     */
    protected function pointsForItem($item, ExpeditionBonusConfig $config): int
    {
        $perUnit = $item->product?->expedition_points ?? $config->default_product_points;

        return $perUnit * $item->quantity;
    }

    /**
     * Calcula a meta diária automática para a empresa.
     */
    public function calculateDailyGoal(int $companyId): array
    {
        $config = $this->getConfig($companyId);
        $bufferDays = $config?->deadline_buffer_days ?? 1;

        // Pedidos pendentes de expedição
        $pendingOrders = Order::where('company_id', $companyId)
            ->whereIn('pipeline_status', PipelineStatus::expeditionStatuses())
            ->where('status', '!=', OrderStatus::Cancelled)
            ->count();

        // Dias úteis restantes no mês (exclui sábados e domingos)
        $today = now();
        $endOfMonth = $today->copy()->endOfMonth();
        $workingDaysLeft = 0;
        $cursor = $today->copy();

        while ($cursor->lte($endOfMonth)) {
            if (! $cursor->isWeekend()) {
                $workingDaysLeft++;
            }
            $cursor->addDay();
        }

        $workingDaysLeft = max(1, $workingDaysLeft - $bufferDays);
        $dailyGoal = (int) ceil($pendingOrders / $workingDaysLeft);

        // Pedidos processados hoje
        $processedToday = Order::where('company_id', $companyId)
            ->where(function ($q) {
                $q->whereDate('shipped_at', today())
                  ->orWhere(function ($q2) {
                      $q2->where('pipeline_status', PipelineStatus::Packed)
                         ->whereDate('updated_at', today());
                  });
            })
            ->count();

        // Verificar se há meta manual (override)
        $goal = ExpeditionGoal::where('company_id', $companyId)
            ->where('month', $today->startOfMonth()->toDateString())
            ->first();

        $manualDailyGoal = $goal?->daily_order_goal;
        $effectiveGoal = ($manualDailyGoal && $manualDailyGoal > 0) ? $manualDailyGoal : $dailyGoal;

        return [
            'pending_orders'    => $pendingOrders,
            'working_days_left' => $workingDaysLeft,
            'calculated_daily'  => $dailyGoal,
            'manual_daily'      => $manualDailyGoal,
            'effective_daily'   => $effectiveGoal,
            'processed_today'   => $processedToday,
            'progress_percent'  => $effectiveGoal > 0 ? min(100, round($processedToday / $effectiveGoal * 100)) : 0,
        ];
    }

    /**
     * Ranking de operadores no período (pontos, pedidos, R$).
     */
    public function operatorRanking(int $companyId, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $from = $from ?? now()->startOfMonth();
        $to   = $to ?? now()->endOfMonth();

        $config = $this->getConfig($companyId);
        $pointsValueCents = $config?->points_value_cents ?? 10;

        $rows = ExpeditionPointsLog::where('company_id', $companyId)
            ->whereBetween('reference_date', [$from->toDateString(), $to->toDateString()])
            ->select([
                'operator_id',
                DB::raw('SUM(points) as total_points'),
                DB::raw('COUNT(DISTINCT order_id) as total_orders'),
                DB::raw('COUNT(CASE WHEN order_item_id IS NOT NULL THEN 1 END) as total_items'),
            ])
            ->groupBy('operator_id')
            ->orderByDesc('total_points')
            ->with('operator:id,name,role')
            ->get();

        return $rows->map(function ($row) use ($pointsValueCents) {
            return [
                'operator_id'   => $row->operator_id,
                'operator_name' => $row->operator?->name ?? 'Desconhecido',
                'operator_role' => $row->operator?->role,
                'total_points'  => (int) $row->total_points,
                'total_orders'  => (int) $row->total_orders,
                'total_items'   => (int) $row->total_items,
                'value_cents'   => (int) $row->total_points * $pointsValueCents,
                'value_formatted' => 'R$ ' . number_format(($row->total_points * $pointsValueCents) / 100, 2, ',', '.'),
            ];
        })->values()->all();
    }

    /**
     * Progresso do mês atual.
     */
    public function monthProgress(int $companyId): array
    {
        $from = now()->startOfMonth();
        $to   = now()->endOfMonth();

        $totalPoints = ExpeditionPointsLog::where('company_id', $companyId)
            ->whereBetween('reference_date', [$from->toDateString(), $to->toDateString()])
            ->sum('points');

        $totalOrders = ExpeditionPointsLog::where('company_id', $companyId)
            ->whereBetween('reference_date', [$from->toDateString(), $to->toDateString()])
            ->distinct('order_id')
            ->count('order_id');

        $config = $this->getConfig($companyId);

        return [
            'total_points'     => (int) $totalPoints,
            'total_orders'     => (int) $totalOrders,
            'value_cents'      => (int) $totalPoints * ($config?->points_value_cents ?? 10),
            'value_formatted'  => 'R$ ' . number_format(($totalPoints * ($config?->points_value_cents ?? 10)) / 100, 2, ',', '.'),
        ];
    }

    /**
     * Fecha o mês: trava a meta e cria snapshot por operador.
     */
    public function closeMonth(int $companyId, Carbon $month): ExpeditionGoal
    {
        $from = $month->copy()->startOfMonth();
        $to   = $month->copy()->endOfMonth();

        $config = $this->getConfig($companyId);
        $pointsValueCents = $config?->points_value_cents ?? 10;

        $goal = ExpeditionGoal::firstOrCreate(
            ['company_id' => $companyId, 'month' => $from->toDateString()],
            [
                'total_pending_orders' => 0,
                'working_days'         => 22,
                'daily_order_goal'     => 0,
            ]
        );

        $totalPoints = ExpeditionPointsLog::where('company_id', $companyId)
            ->whereBetween('reference_date', [$from->toDateString(), $to->toDateString()])
            ->sum('points');

        $goal->update([
            'total_points_earned' => $totalPoints,
            'total_value_cents'   => $totalPoints * $pointsValueCents,
            'is_locked'           => true,
        ]);

        // Snapshot por operador
        $ranking = $this->operatorRanking($companyId, $from, $to);
        foreach ($ranking as $row) {
            ExpeditionGoalOperator::updateOrCreate(
                ['goal_id' => $goal->id, 'operator_id' => $row['operator_id']],
                [
                    'total_points' => $row['total_points'],
                    'total_orders' => $row['total_orders'],
                    'total_items'  => $row['total_items'],
                    'value_cents'  => $row['value_cents'],
                ]
            );
        }

        return $goal->load('operators.operator');
    }

    protected function getConfig(int $companyId): ?ExpeditionBonusConfig
    {
        return ExpeditionBonusConfig::forCompany($companyId);
    }
}
