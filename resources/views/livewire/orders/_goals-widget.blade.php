{{-- Widget de Metas & Bonificação --}}
@if($dailyGoal)
<div class="grid grid-cols-1 lg:grid-cols-3 gap-3 mb-4" x-data="{ showRanking: false }">
    {{-- Meta do dia --}}
    <div class="card p-4">
        <div class="flex items-center justify-between mb-2">
            <h3 class="text-xs font-semibold text-gray-500 dark:text-zinc-400 uppercase tracking-wide">
                Meta do Dia
            </h3>
            <span class="text-lg font-bold text-gray-900 dark:text-white">
                {{ $dailyGoal['processed_today'] }}/{{ $dailyGoal['effective_daily'] }}
            </span>
        </div>
        <div class="w-full bg-gray-200 dark:bg-zinc-700 rounded-full h-2.5 mb-2">
            @php
                $pct = $dailyGoal['progress_percent'];
                $barColor = $pct >= 100 ? 'bg-emerald-500' : ($pct >= 60 ? 'bg-primary-500' : ($pct >= 30 ? 'bg-amber-500' : 'bg-red-500'));
            @endphp
            <div class="{{ $barColor }} h-2.5 rounded-full transition-all duration-500" style="width: {{ $pct }}%"></div>
        </div>
        <div class="flex items-center justify-between text-xs text-gray-500 dark:text-zinc-400">
            <span>{{ $dailyGoal['pending_orders'] }} pendentes</span>
            <span>{{ $dailyGoal['working_days_left'] }} dias úteis restantes</span>
        </div>
        @if($dailyGoal['progress_percent'] >= 100)
            <div class="mt-2 text-xs font-semibold text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                <x-heroicon-s-check-circle class="w-3.5 h-3.5" /> Meta do dia atingida!
            </div>
        @endif
    </div>

    {{-- Progresso do mês --}}
    @if($monthProgress)
    <div class="card p-4">
        <div class="flex items-center justify-between mb-2">
            <h3 class="text-xs font-semibold text-gray-500 dark:text-zinc-400 uppercase tracking-wide">
                Mês Atual
            </h3>
            <span class="text-sm font-bold text-primary-600 dark:text-primary-400">
                {{ $monthProgress['value_formatted'] }}
            </span>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($monthProgress['total_points']) }}</p>
                <p class="text-xs text-gray-500 dark:text-zinc-400">pontos</p>
            </div>
            <div>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $monthProgress['total_orders'] }}</p>
                <p class="text-xs text-gray-500 dark:text-zinc-400">pedidos processados</p>
            </div>
        </div>
    </div>
    @endif

    {{-- Mini ranking --}}
    <div class="card p-4">
        <div class="flex items-center justify-between mb-2">
            <h3 class="text-xs font-semibold text-gray-500 dark:text-zinc-400 uppercase tracking-wide">
                Ranking Operadores
            </h3>
            @if(count($operatorRanking) > 3)
            <button @click="showRanking = !showRanking" class="text-xs text-primary-600 dark:text-primary-400 hover:underline">
                <span x-text="showRanking ? 'Menos' : 'Ver todos'"></span>
            </button>
            @endif
        </div>
        @if(empty($operatorRanking))
            <p class="text-sm text-gray-400 dark:text-zinc-500">Nenhum ponto registrado este mês.</p>
        @else
            <div class="space-y-1.5">
                @foreach($operatorRanking as $i => $rank)
                <div class="{{ $i >= 3 ? '' : '' }}" x-show="{{ $i < 3 }} || showRanking" x-transition>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            @if($i === 0)
                                <span class="text-amber-500 text-sm">🥇</span>
                            @elseif($i === 1)
                                <span class="text-gray-400 text-sm">🥈</span>
                            @elseif($i === 2)
                                <span class="text-amber-700 text-sm">🥉</span>
                            @else
                                <span class="text-xs text-gray-400 w-5 text-center">{{ $i + 1 }}º</span>
                            @endif
                            <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $rank['operator_name'] }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-xs text-gray-500 dark:text-zinc-400">{{ number_format($rank['total_points']) }} pts</span>
                            <span class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">{{ $rank['value_formatted'] }}</span>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        @endif
        <a href="{{ route('expedition.bonuses') }}" class="block text-center text-xs text-primary-600 dark:text-primary-400 hover:underline mt-2">
            Ver relatório completo →
        </a>
    </div>
</div>
@endif
