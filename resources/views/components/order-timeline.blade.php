@props(['order'])

@php
    $timelines = $order->timelines ?? collect();

    // Determina os próximos eventos esperados com base no pipeline atual
    $pipeline = $order->pipeline_status?->value ?? '';
    $doneEvents = $timelines->pluck('event_type')->toArray();

    $allSteps = [
        'order_created'        => ['Pedido recebido',       'heroicon-o-shopping-bag',   'blue'],
        'payment_confirmed'    => ['Pagamento confirmado',  'heroicon-o-credit-card',    'green'],
        'design_assigned'      => ['Design atribuído',      'heroicon-o-user-circle',    'purple'],
        'design_started'       => ['Design iniciado',       'heroicon-o-pencil-square',  'purple'],
        'ai_mockup_generated'  => ['Mockup IA gerado',      'heroicon-o-sparkles',       'violet'],
        'design_completed'     => ['Design finalizado',     'heroicon-o-check-badge',    'purple'],
        'production_started'   => ['Produção iniciada',     'heroicon-o-cog-8-tooth',    'orange'],
        'production_in_progress'=>['Produção em andamento', 'heroicon-o-cog-8-tooth',    'orange'],
        'production_completed' => ['Produção concluída',    'heroicon-o-check-circle',   'orange'],
        'ready_to_ship'        => ['Pronto para envio',     'heroicon-o-truck',          'sky'],
        'invoice_emitted'      => ['NF-e emitida',          'heroicon-o-document-text',  'indigo'],
        'packing_started'      => ['Embalagem iniciada',    'heroicon-o-archive-box',    'slate'],
        'packing_completed'    => ['Embalagem concluída',   'heroicon-o-archive-box-arrow-down', 'slate'],
        'shipped'              => ['Pedido enviado',        'heroicon-o-rocket-launch',  'blue'],
        'tracking_updated'     => ['Rastreio atualizado',   'heroicon-o-map-pin',        'blue'],
        'delivered'            => ['Pedido entregue',       'heroicon-s-check-circle',   'green'],
    ];

    // Eventos futuros baseados no pipeline
    $futureEvents = match ($pipeline) {
        'awaiting_production', 'in_production' => ['design_completed', 'production_completed', 'ready_to_ship', 'shipped', 'delivered'],
        'ready_to_ship', 'packing', 'packed'   => ['shipped', 'delivered'],
        'partially_shipped'                     => ['shipped', 'delivered'],
        'shipped'                               => ['delivered'],
        default                                 => [],
    };

    // Remove eventos futuros que já aconteceram
    $futureEvents = array_filter($futureEvents, fn ($e) => !in_array($e, $doneEvents));
@endphp

<div class="space-y-0">
    @if($timelines->isEmpty())
    <div class="flex flex-col items-center justify-center py-8 text-center">
        <x-heroicon-o-clock class="w-8 h-8 text-gray-300 dark:text-zinc-600 mb-2" />
        <p class="text-sm text-gray-400 dark:text-zinc-500">Nenhum evento registrado ainda.</p>
    </div>
    @else

    <div class="relative">
        {{-- Linha vertical --}}
        <div class="absolute left-4 top-0 bottom-0 w-0.5 bg-gray-200 dark:bg-zinc-700 z-0"></div>

        <div class="space-y-1">
            {{-- Eventos concluídos --}}
            @foreach($timelines as $event)
            @php
                $cfg   = $allSteps[$event->event_type] ?? ['Evento', 'heroicon-o-information-circle', 'gray'];
                $colors = [
                    'blue'   => 'bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400 ring-blue-200 dark:ring-blue-800',
                    'green'  => 'bg-green-100 dark:bg-green-900/40 text-green-600 dark:text-green-400 ring-green-200 dark:ring-green-800',
                    'purple' => 'bg-purple-100 dark:bg-purple-900/40 text-purple-600 dark:text-purple-400 ring-purple-200 dark:ring-purple-800',
                    'violet' => 'bg-violet-100 dark:bg-violet-900/40 text-violet-600 dark:text-violet-400 ring-violet-200 dark:ring-violet-800',
                    'orange' => 'bg-orange-100 dark:bg-orange-900/40 text-orange-600 dark:text-orange-400 ring-orange-200 dark:ring-orange-800',
                    'sky'    => 'bg-sky-100 dark:bg-sky-900/40 text-sky-600 dark:text-sky-400 ring-sky-200 dark:ring-sky-800',
                    'indigo' => 'bg-indigo-100 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-400 ring-indigo-200 dark:ring-indigo-800',
                    'slate'  => 'bg-slate-100 dark:bg-slate-900/40 text-slate-600 dark:text-slate-400 ring-slate-200 dark:ring-slate-800',
                    'gray'   => 'bg-gray-100 dark:bg-zinc-800 text-gray-500 dark:text-zinc-400 ring-gray-200 dark:ring-zinc-700',
                ];
                $colorClass = $colors[$cfg[2]] ?? $colors['gray'];
            @endphp
            <div class="relative flex gap-3 pl-1 pr-2 py-2.5 rounded-xl hover:bg-gray-50 dark:hover:bg-zinc-800/50 transition-colors group">
                {{-- Ícone --}}
                <div class="relative z-10 flex-shrink-0">
                    <div class="w-7 h-7 rounded-full ring-2 ring-white dark:ring-zinc-900 flex items-center justify-center {{ $colorClass }}">
                        <x-dynamic-component :component="$cfg[1]" class="w-3.5 h-3.5" />
                    </div>
                </div>

                {{-- Conteúdo --}}
                <div class="flex-1 min-w-0 pt-0.5">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-sm font-medium text-gray-900 dark:text-white leading-tight">
                            {{ $event->title }}
                        </p>
                        <time class="text-[10px] text-gray-400 dark:text-zinc-500 flex-shrink-0 mt-0.5"
                              title="{{ $event->happened_at->format('d/m/Y H:i:s') }}">
                            {{ $event->happened_at->format('d/m H:i') }}
                        </time>
                    </div>
                    @if($event->description)
                    <p class="text-xs text-gray-500 dark:text-zinc-400 mt-0.5 leading-relaxed">
                        {{ $event->description }}
                    </p>
                    @endif
                    @if($event->performer)
                    <div class="flex items-center gap-1 mt-1">
                        <div class="w-4 h-4 rounded-full bg-primary-600 flex items-center justify-center text-white text-[8px] font-bold">
                            {{ strtoupper(substr($event->performer->name, 0, 1)) }}
                        </div>
                        <span class="text-[10px] text-gray-400 dark:text-zinc-500">{{ $event->performer->name }}</span>
                    </div>
                    @else
                    <span class="text-[10px] text-gray-300 dark:text-zinc-600 mt-0.5 block">Sistema</span>
                    @endif

                    {{-- Dados extras expandíveis --}}
                    @if($event->data && count($event->data) > 0)
                    @php
                        $showData = array_filter($event->data, fn ($v, $k) => !in_array($k, ['from','to','status']), ARRAY_FILTER_USE_BOTH);
                    @endphp
                    @if(!empty($showData))
                    <details class="mt-1.5 group/details">
                        <summary class="text-[10px] text-gray-400 dark:text-zinc-500 cursor-pointer hover:text-gray-600 dark:hover:text-zinc-300 select-none">
                            Ver detalhes
                        </summary>
                        <div class="mt-1 text-[10px] text-gray-400 dark:text-zinc-500 space-y-0.5 font-mono">
                            @foreach($showData as $k => $v)
                            <div class="flex gap-1">
                                <span class="text-gray-300 dark:text-zinc-600">{{ $k }}:</span>
                                <span>{{ is_array($v) ? json_encode($v) : $v }}</span>
                            </div>
                            @endforeach
                        </div>
                    </details>
                    @endif
                    @endif
                </div>
            </div>
            @endforeach

            {{-- Eventos futuros (previsão) --}}
            @if(!empty($futureEvents))
            <div class="relative flex gap-3 pl-1 pr-2 py-2 rounded-xl opacity-40">
                <div class="relative z-10 flex-shrink-0">
                    <div class="w-7 h-7 rounded-full border-2 border-dashed border-gray-300 dark:border-zinc-600 flex items-center justify-center bg-white dark:bg-zinc-900">
                        <x-heroicon-o-ellipsis-horizontal class="w-3.5 h-3.5 text-gray-400 dark:text-zinc-500" />
                    </div>
                </div>
                <div class="flex-1 pt-1">
                    <p class="text-xs text-gray-400 dark:text-zinc-500">
                        Próximos passos:
                        <span class="font-medium">
                            @foreach(array_slice($futureEvents, 0, 3) as $fe)
                                {{ $allSteps[$fe][0] ?? $fe }}{{ !$loop->last ? ' → ' : '' }}
                            @endforeach
                        </span>
                    </p>
                </div>
            </div>
            @endif
        </div>
    </div>
    @endif
</div>
