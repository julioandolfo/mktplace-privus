<x-app-layout>
    <x-slot name="header">Logs do Sistema</x-slot>
    <x-slot name="subtitle">Historico de atividades e execucao de tarefas automaticas</x-slot>
    <x-slot name="breadcrumbs">
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <span class="text-gray-700 dark:text-zinc-200">Logs</span>
        </li>
    </x-slot>

    {{-- Filters --}}
    <form method="GET" class="flex flex-wrap items-end gap-3 mb-6">
        <div>
            <label class="form-label">Canal</label>
            <select name="log" class="form-input" onchange="this.form.submit()">
                <option value="">Todos</option>
                @foreach($logChannels as $channel)
                    <option value="{{ $channel }}" {{ request('log') === $channel ? 'selected' : '' }}>
                        {{ ucfirst($channel) }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="flex-1 min-w-48">
            <label class="form-label">Buscar</label>
            <div class="relative">
                <x-heroicon-o-magnifying-glass class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="Descricao ou tipo..."
                       class="form-input pl-9">
            </div>
        </div>
        <button type="submit" class="btn-secondary">Filtrar</button>
        @if(request()->hasAny(['log', 'search']))
            <a href="{{ route('logs.index') }}" class="btn-ghost">Limpar</a>
        @endif
    </form>

    {{-- Stats --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        @php
            $total     = $logs->total();
            $today     = \Spatie\Activitylog\Models\Activity::whereDate('created_at', today())->count();
            $automated = \Spatie\Activitylog\Models\Activity::whereNull('causer_id')->count();
            $errors    = \Spatie\Activitylog\Models\Activity::where('description', 'like', '%failed%')
                             ->orWhere('description', 'like', '%erro%')->count();
        @endphp
        <x-ui.card>
            <div class="text-center">
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($total) }}</p>
                <p class="text-xs text-gray-500 dark:text-zinc-400 mt-1">Total de registros</p>
            </div>
        </x-ui.card>
        <x-ui.card>
            <div class="text-center">
                <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ number_format($today) }}</p>
                <p class="text-xs text-gray-500 dark:text-zinc-400 mt-1">Hoje</p>
            </div>
        </x-ui.card>
        <x-ui.card>
            <div class="text-center">
                <p class="text-2xl font-bold text-purple-600 dark:text-purple-400">{{ number_format($automated) }}</p>
                <p class="text-xs text-gray-500 dark:text-zinc-400 mt-1">Automatizados</p>
            </div>
        </x-ui.card>
        <x-ui.card>
            <div class="text-center">
                <p class="text-2xl font-bold {{ $errors > 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">{{ number_format($errors) }}</p>
                <p class="text-xs text-gray-500 dark:text-zinc-400 mt-1">Erros</p>
            </div>
        </x-ui.card>
    </div>

    {{-- Log table --}}
    <x-ui.card :padding="false">
        @if($logs->isEmpty())
            <div class="py-16 text-center text-gray-400 dark:text-zinc-500">
                <x-heroicon-o-clipboard-document-list class="w-10 h-10 mx-auto mb-3 opacity-40" />
                <p class="text-sm">Nenhum registro encontrado.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th class="w-36">Quando</th>
                            <th class="w-24">Canal</th>
                            <th>Evento</th>
                            <th>Recurso</th>
                            <th>Responsavel</th>
                            <th>Detalhes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($logs as $entry)
                        @php
                            $isError = str_contains(strtolower($entry->description), 'failed')
                                    || str_contains(strtolower($entry->description), 'erro')
                                    || str_contains(strtolower($entry->description), 'falha');

                            $subjectLabel = '';
                            if ($entry->subject) {
                                $subjectLabel = match(true) {
                                    $entry->subject instanceof \App\Models\MarketplaceAccount
                                        => $entry->subject->account_name ?? "#{$entry->subject_id}",
                                    $entry->subject instanceof \App\Models\Company
                                        => $entry->subject->name ?? "#{$entry->subject_id}",
                                    default => class_basename($entry->subject_type) . " #{$entry->subject_id}",
                                };
                            } elseif ($entry->subject_type) {
                                $subjectLabel = class_basename($entry->subject_type) . " #{$entry->subject_id}";
                            }
                        @endphp
                        <tr class="{{ $isError ? 'bg-red-50/50 dark:bg-red-900/10' : '' }}">
                            <td class="text-xs text-gray-500 dark:text-zinc-400 whitespace-nowrap">
                                <span title="{{ $entry->created_at->format('d/m/Y H:i:s') }}">
                                    {{ $entry->created_at->diffForHumans() }}
                                </span>
                                <br>
                                <span class="text-gray-400 dark:text-zinc-600">{{ $entry->created_at->format('d/m H:i') }}</span>
                            </td>
                            <td>
                                @php
                                    $channelColor = match($entry->log_name) {
                                        'marketplace' => 'blue',
                                        'default'     => 'gray',
                                        default       => 'gray',
                                    };
                                @endphp
                                <x-ui.badge :color="$channelColor">{{ $entry->log_name ?? 'default' }}</x-ui.badge>
                            </td>
                            <td>
                                <div class="flex items-center gap-2">
                                    @if($isError)
                                        <x-heroicon-s-exclamation-circle class="w-4 h-4 text-red-500 flex-shrink-0" />
                                    @else
                                        <x-heroicon-s-check-circle class="w-4 h-4 text-green-500 flex-shrink-0" />
                                    @endif
                                    <span class="text-sm {{ $isError ? 'text-red-700 dark:text-red-300' : 'text-gray-800 dark:text-zinc-200' }}">
                                        {{ $entry->description }}
                                    </span>
                                </div>
                            </td>
                            <td class="text-sm text-gray-600 dark:text-zinc-300">
                                {{ $subjectLabel ?: '—' }}
                            </td>
                            <td class="text-sm">
                                @if($entry->causer)
                                    <div class="flex items-center gap-1.5">
                                        <x-heroicon-o-user class="w-3.5 h-3.5 text-gray-400" />
                                        <span>{{ $entry->causer->name ?? 'Usuario' }}</span>
                                    </div>
                                @else
                                    <div class="flex items-center gap-1.5 text-purple-600 dark:text-purple-400">
                                        <x-heroicon-o-cpu-chip class="w-3.5 h-3.5" />
                                        <span>Sistema</span>
                                    </div>
                                @endif
                            </td>
                            <td>
                                @if($entry->properties->isNotEmpty())
                                    <details class="group">
                                        <summary class="cursor-pointer text-xs text-gray-400 dark:text-zinc-500 hover:text-gray-600 dark:hover:text-zinc-300 list-none flex items-center gap-1">
                                            <x-heroicon-o-chevron-right class="w-3 h-3 group-open:rotate-90 transition-transform" />
                                            Ver detalhes
                                        </summary>
                                        <pre class="mt-1 text-xs bg-gray-50 dark:bg-zinc-800 rounded p-2 max-w-xs overflow-auto text-gray-600 dark:text-zinc-300">{{ json_encode($entry->properties->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                    </details>
                                @else
                                    <span class="text-xs text-gray-300 dark:text-zinc-600">—</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($logs->hasPages())
                <div class="px-4 py-3 border-t border-gray-100 dark:border-zinc-700">
                    {{ $logs->links() }}
                </div>
            @endif
        @endif
    </x-ui.card>
</x-app-layout>
