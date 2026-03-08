<div class="space-y-6">

    {{-- Alertas de pedidos não atribuídos (admin) --}}
    @if($unassignedOrders->isNotEmpty())
    <div class="card border border-amber-200 dark:border-amber-700 bg-amber-50 dark:bg-amber-900/20 p-4">
        <div class="flex items-start gap-3">
            <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" />
            <div class="flex-1">
                <p class="font-medium text-amber-700 dark:text-amber-300 text-sm">
                    {{ $unassignedOrders->count() }} pedido(s) aguardando atribuição de designer
                </p>
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach($unassignedOrders as $unassigned)
                    <div class="flex items-center gap-2 bg-white dark:bg-zinc-800 rounded-lg px-3 py-1.5 text-sm border border-amber-200 dark:border-amber-700">
                        <span class="font-mono font-medium">{{ $unassigned->order_number }}</span>
                        <span class="text-gray-500">{{ $unassigned->customer_name }}</span>
                        <button wire:click="manualAssign({{ $unassigned->id }})"
                                class="ml-1 text-xs text-primary-600 dark:text-primary-400 hover:underline font-medium">
                            Distribuir
                        </button>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Filtros --}}
    <div class="flex items-center gap-3 flex-wrap">
        <div class="relative flex-1 min-w-48">
            <x-heroicon-o-magnifying-glass class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
            <input type="text" wire:model.live.debounce.400ms="search"
                   placeholder="Buscar pedido ou cliente..."
                   class="form-input pl-9">
        </div>
        @if($isAdmin && $designers->isNotEmpty())
        <select wire:model.live="filterDesigner" class="form-input w-auto">
            <option value="">Todos os designers</option>
            @foreach($designers as $d)
            <option value="{{ $d->id }}">{{ $d->name }}</option>
            @endforeach
        </select>
        @endif
    </div>

    {{-- Tabs --}}
    <div class="border-b border-gray-200 dark:border-zinc-700">
        <nav class="flex gap-1">
            @php
                $tabs = [
                    'pending'     => ['Aguardando', 'warning', $tabCounts['pending']],
                    'in_progress' => ['Em Andamento', 'info', $tabCounts['in_progress']],
                    'revision'    => ['Revisão', 'danger', $tabCounts['revision']],
                    'completed'   => ['Concluídos', 'success', $tabCounts['completed']],
                ];
            @endphp
            @foreach($tabs as $key => [$label, $color, $count])
            <button wire:click="setTab('{{ $key }}')"
                    class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 transition-colors
                           {{ $activeTab === $key
                               ? 'border-primary-500 text-primary-600 dark:text-primary-400'
                               : 'border-transparent text-gray-500 dark:text-zinc-400 hover:text-gray-700 dark:hover:text-zinc-200' }}">
                {{ $label }}
                @if($count > 0)
                <span class="text-xs rounded-full px-1.5 py-0.5
                    {{ $activeTab === $key ? 'bg-primary-100 dark:bg-primary-900/40 text-primary-700 dark:text-primary-300' : 'bg-gray-100 dark:bg-zinc-700 text-gray-600 dark:text-zinc-300' }}">
                    {{ $count }}
                </span>
                @endif
            </button>
            @endforeach
        </nav>
    </div>

    {{-- Cards de assignments --}}
    @if($assignments->isEmpty())
    <div class="card p-10 text-center">
        <x-heroicon-o-paint-brush class="w-12 h-12 text-gray-300 dark:text-zinc-600 mx-auto mb-3" />
        <p class="text-gray-500 dark:text-zinc-400">
            @if($activeTab === 'pending') Nenhum pedido aguardando.
            @elseif($activeTab === 'in_progress') Nenhum pedido em andamento.
            @elseif($activeTab === 'revision') Nenhum pedido em revisão.
            @else Nenhum pedido concluído.
            @endif
        </p>
    </div>
    @else
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        @foreach($assignments as $assignment)
        @php
            $order    = $assignment->order;
            $deadline = $order->meta['ml_shipping_deadline'] ?? null;
            $isLate   = $deadline && \Carbon\Carbon::parse($deadline)->isPast();
        @endphp
        <div class="card overflow-hidden flex flex-col transition-shadow hover:shadow-md">
            {{-- Header --}}
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-zinc-700
                        {{ $isLate ? 'bg-red-50 dark:bg-red-900/10' : 'bg-gray-50 dark:bg-zinc-800/50' }}">
                <div class="flex items-center gap-2">
                    <a href="{{ route('orders.show', $order) }}"
                       class="font-mono text-sm font-bold text-primary-600 dark:text-primary-400 hover:underline">
                        {{ $order->order_number }}
                    </a>
                    <x-ui.badge :color="$assignment->statusColor()">{{ $assignment->statusLabel() }}</x-ui.badge>
                </div>
                @if($isLate)
                <x-ui.badge color="danger" class="text-xs">Atrasado</x-ui.badge>
                @elseif($deadline)
                <span class="text-xs text-gray-500">
                    Até {{ \Carbon\Carbon::parse($deadline)->format('d/m H:i') }}
                </span>
                @endif
            </div>

            {{-- Cliente + canal --}}
            <div class="px-4 py-2 text-sm border-b border-gray-50 dark:border-zinc-800">
                <p class="font-medium text-gray-900 dark:text-white">{{ $order->customer_name }}</p>
                @if($order->marketplaceAccount)
                <p class="text-xs text-gray-400 dark:text-zinc-500">{{ $order->marketplaceAccount->account_name }}</p>
                @endif
                @if($isAdmin && $assignment->designer)
                <p class="text-xs text-purple-600 dark:text-purple-400 mt-0.5">
                    <x-heroicon-o-user class="w-3 h-3 inline" />
                    {{ $assignment->designer->name }}
                </p>
                @endif
            </div>

            {{-- Imagens dos produtos --}}
            <div class="px-4 py-3 flex-1">
                <div class="flex gap-2 flex-wrap">
                    @foreach($order->items->take(4) as $item)
                    @php $img = $item->product?->primaryImage; @endphp
                    <div class="relative group">
                        @if($img)
                        <img src="{{ $img->url }}" alt="{{ $item->name }}"
                             class="w-14 h-14 object-cover rounded-lg border border-gray-200 dark:border-zinc-700"
                             title="{{ $item->name }}" />
                        @else
                        <div class="w-14 h-14 bg-gray-100 dark:bg-zinc-700 rounded-lg flex items-center justify-center">
                            <x-heroicon-o-photo class="w-5 h-5 text-gray-400" />
                        </div>
                        @endif
                        <div class="absolute -bottom-1 -right-1 w-5 h-5 bg-white dark:bg-zinc-800 rounded-full border border-gray-200 dark:border-zinc-700 flex items-center justify-center">
                            <span class="text-[9px] font-bold text-gray-600 dark:text-zinc-300">{{ $item->quantity }}</span>
                        </div>
                    </div>
                    @endforeach
                    @if($order->items->count() > 4)
                    <div class="w-14 h-14 bg-gray-100 dark:bg-zinc-700 rounded-lg flex items-center justify-center">
                        <span class="text-xs text-gray-500">+{{ $order->items->count() - 4 }}</span>
                    </div>
                    @endif
                </div>
                <p class="text-xs text-gray-400 dark:text-zinc-500 mt-2">
                    {{ $order->items->count() }} item(s) · R$ {{ number_format($order->total, 2, ',', '.') }}
                </p>
            </div>

            {{-- Mockup gerado (se houver) --}}
            @if($assignment->mockup_url)
            <div class="px-4 pb-2">
                <img src="{{ $assignment->mockup_url }}" alt="Mockup"
                     class="w-full h-24 object-cover rounded-lg border-2 border-green-300 dark:border-green-700" />
            </div>
            @endif

            {{-- Ações --}}
            <div class="px-4 pb-4 flex items-center gap-2">
                @if($assignment->status === 'pending')
                <a href="{{ route('designer.edit', $assignment) }}"
                   wire:navigate
                   class="btn-primary btn-sm flex-1 text-center">
                    <x-heroicon-o-paint-brush class="w-4 h-4" />
                    Iniciar Design
                </a>
                @elseif($assignment->status === 'in_progress')
                <a href="{{ route('designer.edit', $assignment) }}"
                   wire:navigate
                   class="btn-secondary btn-sm flex-1 text-center">
                    <x-heroicon-o-pencil-square class="w-4 h-4" />
                    Continuar Editando
                </a>
                @elseif($assignment->status === 'completed')
                <a href="{{ route('designer.edit', $assignment) }}"
                   class="btn-secondary btn-sm flex-1 text-center">
                    <x-heroicon-o-eye class="w-4 h-4" />
                    Visualizar
                </a>
                @else
                <a href="{{ route('designer.edit', $assignment) }}"
                   class="btn-secondary btn-sm flex-1 text-center">
                    <x-heroicon-o-arrow-path class="w-4 h-4" />
                    Revisar
                </a>
                @endif

                @if($isAdmin)
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open" class="btn-secondary btn-sm">
                        <x-heroicon-o-ellipsis-vertical class="w-4 h-4" />
                    </button>
                    <div x-show="open" @click.outside="open = false" x-cloak
                         class="absolute right-0 bottom-full mb-1 w-48 bg-white dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 rounded-xl shadow-xl z-20 p-1">
                        @foreach($designers as $d)
                        @if($d->id !== $assignment->designer_id)
                        <button wire:click="reassign({{ $assignment->id }}, {{ $d->id }})"
                                @click="open = false"
                                class="w-full text-left px-3 py-2 text-sm rounded-lg hover:bg-gray-50 dark:hover:bg-zinc-700">
                            Reatribuir → {{ $d->name }}
                        </button>
                        @endif
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endforeach
    </div>

    {{ $assignments->links() }}
    @endif

</div>
