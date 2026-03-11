<div>
    {{-- Filtro de mês --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-6">
        <div class="flex items-center gap-3">
            <input type="month" wire:model.live="selectedMonth" class="form-input w-48">
            @if($isLocked)
                <span class="inline-flex items-center gap-1 text-xs font-bold px-2.5 py-1 rounded-full bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400">
                    <x-heroicon-s-lock-closed class="w-3 h-3" /> Fechado
                </span>
            @else
                <span class="inline-flex items-center gap-1 text-xs font-bold px-2.5 py-1 rounded-full bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400">
                    <x-heroicon-s-lock-open class="w-3 h-3" /> Em aberto
                </span>
            @endif
        </div>
        <div class="flex items-center gap-2">
            <button wire:click="exportCsv" class="btn-secondary text-sm">
                <x-heroicon-o-arrow-down-tray class="w-4 h-4" />
                Exportar CSV
            </button>
            @if($isLocked)
                <button wire:click="reopenMonth" wire:confirm="Reabrir este mês? Os valores poderão mudar." class="btn-secondary text-sm text-amber-600">
                    <x-heroicon-o-lock-open class="w-4 h-4" />
                    Reabrir
                </button>
            @else
                <button wire:click="$set('showCloseConfirm', true)" class="btn-primary text-sm">
                    <x-heroicon-o-lock-closed class="w-4 h-4" />
                    Fechar Mês
                </button>
            @endif
        </div>
    </div>

    {{-- Totais --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="card p-5 text-center">
            <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ number_format($totalPoints) }}</p>
            <p class="text-sm text-gray-500 dark:text-zinc-400 mt-1">Pontos Totais</p>
        </div>
        <div class="card p-5 text-center">
            <p class="text-3xl font-bold text-emerald-600 dark:text-emerald-400">{{ $totalValue }}</p>
            <p class="text-sm text-gray-500 dark:text-zinc-400 mt-1">Valor Total</p>
        </div>
        <div class="card p-5 text-center">
            <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ $config->points_value_formatted }}</p>
            <p class="text-sm text-gray-500 dark:text-zinc-400 mt-1">Valor por Ponto</p>
        </div>
    </div>

    {{-- Ranking de operadores --}}
    <x-ui.card title="Ranking de Operadores" :padding="false">
        @if(empty($ranking))
            <div class="text-center py-10">
                <x-heroicon-o-trophy class="w-10 h-10 text-gray-300 dark:text-zinc-600 mx-auto mb-2" />
                <p class="text-gray-500 dark:text-zinc-400">Nenhum ponto registrado neste mês.</p>
            </div>
        @else
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="w-12">#</th>
                        <th>Operador</th>
                        <th>Função</th>
                        <th class="text-right">Pedidos</th>
                        <th class="text-right">Itens</th>
                        <th class="text-right">Pontos</th>
                        <th class="text-right">Valor</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($ranking as $i => $row)
                    <tr>
                        <td>
                            @if($i === 0)
                                <span class="text-lg">🥇</span>
                            @elseif($i === 1)
                                <span class="text-lg">🥈</span>
                            @elseif($i === 2)
                                <span class="text-lg">🥉</span>
                            @else
                                <span class="text-sm text-gray-400">{{ $i + 1 }}º</span>
                            @endif
                        </td>
                        <td>
                            <span class="font-semibold text-gray-900 dark:text-white">{{ $row['operator_name'] }}</span>
                        </td>
                        <td>
                            @if($row['operator_role'])
                                <span class="text-sm text-gray-500 dark:text-zinc-400">{{ $row['operator_role'] }}</span>
                            @else
                                <span class="text-gray-300 dark:text-zinc-600">—</span>
                            @endif
                        </td>
                        <td class="text-right font-medium">{{ $row['total_orders'] }}</td>
                        <td class="text-right font-medium">{{ $row['total_items'] }}</td>
                        <td class="text-right">
                            <span class="font-bold text-primary-600 dark:text-primary-400">{{ number_format($row['total_points']) }}</span>
                        </td>
                        <td class="text-right">
                            <span class="font-bold text-emerald-600 dark:text-emerald-400">{{ $row['value_formatted'] }}</span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-gray-200 dark:border-zinc-600">
                        <td colspan="5" class="text-right font-semibold text-gray-700 dark:text-zinc-300">Total</td>
                        <td class="text-right font-bold text-gray-900 dark:text-white">{{ number_format($totalPoints) }}</td>
                        <td class="text-right font-bold text-emerald-600 dark:text-emerald-400">{{ $totalValue }}</td>
                    </tr>
                </tfoot>
            </table>
        @endif
    </x-ui.card>

    {{-- Evolução diária --}}
    @if($dailyData->isNotEmpty())
    <x-ui.card title="Evolução Diária" class="mt-6">
        <div class="overflow-x-auto">
            <div class="flex items-end gap-1 h-32 min-w-max">
                @php
                    $maxPts = $dailyData->max('day_points') ?: 1;
                @endphp
                @foreach($dailyData as $day)
                    @php
                        $barH = max(4, round($day->day_points / $maxPts * 100));
                        $date = \Carbon\Carbon::parse($day->reference_date);
                    @endphp
                    <div class="flex flex-col items-center gap-1 group relative" style="min-width: 28px;">
                        <div class="text-xs text-gray-400 dark:text-zinc-500 opacity-0 group-hover:opacity-100 transition-opacity absolute -top-5">
                            {{ $day->day_points }}pts
                        </div>
                        <div class="w-5 bg-primary-500 dark:bg-primary-400 rounded-t hover:bg-primary-600 transition-colors"
                             style="height: {{ $barH }}%" title="{{ $date->format('d/m') }}: {{ $day->day_points }} pts / {{ $day->day_orders }} pedidos"></div>
                        <span class="text-[10px] text-gray-400 dark:text-zinc-500">{{ $date->format('d') }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </x-ui.card>
    @endif

    {{-- Modal confirmação fechamento --}}
    @if($showCloseConfirm)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
        <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-xl w-full max-w-sm mx-4 p-6">
            <div class="flex items-center gap-3 mb-4">
                <div class="flex items-center justify-center w-10 h-10 rounded-full bg-amber-100 dark:bg-amber-900/30">
                    <x-heroicon-o-lock-closed class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Fechar Mês</h3>
                    <p class="text-sm text-gray-500 dark:text-zinc-400">{{ \Carbon\Carbon::parse($selectedMonth . '-01')->translatedFormat('F Y') }}</p>
                </div>
            </div>
            <p class="text-sm text-gray-600 dark:text-zinc-300 mb-4">
                Ao fechar o mês, os valores serão travados e um snapshot será gerado por operador.
                Você poderá reabrir depois se necessário.
            </p>
            <div class="flex justify-end gap-3">
                <button wire:click="$set('showCloseConfirm', false)" class="btn-secondary">Cancelar</button>
                <button wire:click="closeMonth" class="btn-primary">Confirmar Fechamento</button>
            </div>
        </div>
    </div>
    @endif
</div>
