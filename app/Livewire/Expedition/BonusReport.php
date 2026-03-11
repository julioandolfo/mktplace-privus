<?php

namespace App\Livewire\Expedition;

use App\Models\ExpeditionBonusConfig;
use App\Models\ExpeditionGoal;
use App\Models\ExpeditionGoalOperator;
use App\Models\ExpeditionPointsLog;
use App\Services\ExpeditionBonusService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class BonusReport extends Component
{
    public string $selectedMonth = '';
    public bool   $showCloseConfirm = false;

    public function mount(): void
    {
        $this->selectedMonth = now()->format('Y-m');
    }

    public function closeMonth(): void
    {
        $companyId = Auth::user()->company_id;
        $month = Carbon::parse($this->selectedMonth . '-01');

        app(ExpeditionBonusService::class)->closeMonth($companyId, $month);

        $this->showCloseConfirm = false;
        session()->flash('success', "Mês {$month->format('m/Y')} fechado com sucesso.");
    }

    public function reopenMonth(): void
    {
        $companyId = Auth::user()->company_id;
        $monthDate = Carbon::parse($this->selectedMonth . '-01')->startOfMonth()->toDateString();

        $goal = ExpeditionGoal::where('company_id', $companyId)
            ->where('month', $monthDate)
            ->first();

        if ($goal) {
            $goal->update(['is_locked' => false]);
            session()->flash('success', 'Mês reaberto.');
        }
    }

    public function exportCsv()
    {
        $companyId = Auth::user()->company_id;
        $month = Carbon::parse($this->selectedMonth . '-01');
        $from = $month->copy()->startOfMonth();
        $to   = $month->copy()->endOfMonth();

        $ranking = app(ExpeditionBonusService::class)->operatorRanking($companyId, $from, $to);

        $csv = "Operador,Funcao,Pedidos,Itens,Pontos,Valor\n";
        foreach ($ranking as $row) {
            $csv .= "\"{$row['operator_name']}\",\"{$row['operator_role']}\",{$row['total_orders']},{$row['total_items']},{$row['total_points']},\"{$row['value_formatted']}\"\n";
        }

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, "bonificacao-{$this->selectedMonth}.csv", [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function render()
    {
        $companyId = Auth::user()->company_id;
        $month = Carbon::parse($this->selectedMonth . '-01');
        $from = $month->copy()->startOfMonth();
        $to   = $month->copy()->endOfMonth();

        $config = ExpeditionBonusConfig::forCompany($companyId);

        // Verificar se mês está fechado
        $goal = ExpeditionGoal::where('company_id', $companyId)
            ->where('month', $from->toDateString())
            ->first();

        $isLocked = $goal?->is_locked ?? false;

        // Se fechado, usar snapshot; senão, calcular em tempo real
        if ($isLocked && $goal) {
            $ranking = $goal->operators()
                ->with('operator:id,name,role')
                ->orderByDesc('total_points')
                ->get()
                ->map(fn ($row) => [
                    'operator_id'     => $row->operator_id,
                    'operator_name'   => $row->operator?->name ?? 'Desconhecido',
                    'operator_role'   => $row->operator?->role,
                    'total_points'    => $row->total_points,
                    'total_orders'    => $row->total_orders,
                    'total_items'     => $row->total_items,
                    'value_cents'     => $row->value_cents,
                    'value_formatted' => $row->value_formatted,
                ])
                ->all();

            $totalPoints = $goal->total_points_earned;
            $totalValue  = $goal->total_value_formatted;
        } else {
            $ranking = app(ExpeditionBonusService::class)->operatorRanking($companyId, $from, $to);
            $totalPoints = array_sum(array_column($ranking, 'total_points'));
            $totalValueCents = $totalPoints * $config->points_value_cents;
            $totalValue = 'R$ ' . number_format($totalValueCents / 100, 2, ',', '.');
        }

        // Evolução diária (pontos por dia no mês)
        $dailyData = ExpeditionPointsLog::where('company_id', $companyId)
            ->whereBetween('reference_date', [$from->toDateString(), $to->toDateString()])
            ->select([
                'reference_date',
                DB::raw('SUM(points) as day_points'),
                DB::raw('COUNT(DISTINCT order_id) as day_orders'),
            ])
            ->groupBy('reference_date')
            ->orderBy('reference_date')
            ->get();

        return view('livewire.expedition.bonus-report', [
            'config'      => $config,
            'ranking'     => $ranking,
            'totalPoints' => $totalPoints,
            'totalValue'  => $totalValue,
            'isLocked'    => $isLocked,
            'goal'        => $goal,
            'dailyData'   => $dailyData,
        ]);
    }
}
