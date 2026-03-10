<x-app-layout>
    <x-slot name="header">Romaneio — {{ $romaneio->name }}</x-slot>
    <x-slot name="actions">
        <a href="{{ route('romaneios.pdf.romaneio', $romaneio) }}" target="_blank" class="btn-secondary">
            <x-heroicon-o-document-arrow-down class="w-4 h-4" />
            PDF Romaneio
        </a>
        <a href="{{ route('romaneios.pdf.etiquetas', $romaneio) }}" target="_blank" class="btn-secondary">
            <x-heroicon-o-tag class="w-4 h-4" />
            Etiquetas de Volume
        </a>
        @if($romaneio->isOpen())
        <a href="{{ route('romaneios.board', $romaneio) }}" class="btn-primary">
            <x-heroicon-o-qr-code class="w-4 h-4" />
            Tela de Bipagem
        </a>
        @endif
    </x-slot>
    <x-slot name="breadcrumbs">
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <a href="{{ route('romaneios.index') }}" class="hover:text-gray-700 dark:hover:text-zinc-200">Romaneios</a>
        </li>
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <span class="text-gray-700 dark:text-zinc-200">{{ $romaneio->name }}</span>
        </li>
    </x-slot>

    <div class="space-y-6">
        {{-- Status geral --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <x-ui.stat label="Status" :value="$romaneio->isOpen() ? 'Aberto' : 'Fechado'" />
            <x-ui.stat label="Total de Pedidos" :value="$romaneio->total_orders" />
            <x-ui.stat label="Total de Volumes" :value="$romaneio->total_volumes" />
            <x-ui.stat label="Volumes Bipados" :value="$romaneio->total_volumes_scanned . '/' . $romaneio->total_volumes" />
        </div>

        {{-- Progresso geral --}}
        @if($romaneio->total_volumes > 0)
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-gray-200 dark:border-zinc-700 p-4">
            <div class="flex items-center justify-between mb-2 text-sm">
                <span class="font-medium">Progresso de bipagem</span>
                <span class="text-gray-500 dark:text-zinc-400">{{ $romaneio->completion_percent }}%</span>
            </div>
            <div class="bg-gray-200 dark:bg-zinc-700 rounded-full h-3">
                <div class="{{ $romaneio->completion_percent >= 100 ? 'bg-green-500' : 'bg-primary-500' }} h-3 rounded-full transition-all"
                     style="width: {{ $romaneio->completion_percent }}%"></div>
            </div>
        </div>
        @endif

        {{-- Tabela de pedidos --}}
        <x-ui.card title="Pedidos no Romaneio" :padding="false">
            @if($romaneio->items->isEmpty())
            <div class="text-center py-10">
                <x-heroicon-o-inbox class="w-10 h-10 text-gray-300 dark:text-zinc-600 mx-auto mb-2" />
                <p class="text-gray-500 dark:text-zinc-400">Nenhum pedido neste romaneio.</p>
            </div>
            @else
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Pedido</th>
                        <th>Cliente</th>
                        <th>Cidade/UF</th>
                        <th class="text-center">Qtd Itens</th>
                        <th class="text-right">Total</th>
                        <th class="text-center">Volumes</th>
                        <th class="text-center">Bipados</th>
                        <th>Prazo</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($romaneio->items as $item)
                    @php
                        $order = $item->order;
                    @endphp
                    @if(!$order) @continue @endif
                    @php
                        $addr     = $order->shipping_address ?? [];
                        $deadline = $order->meta['ml_shipping_deadline'] ?? null;
                        $complete = $item->isComplete();
                        $isPartial = ! empty($item->items_detail) &&
                                     collect($item->items_detail)->sum('quantity') < $order->items->sum('quantity');
                    @endphp
                    <tr class="{{ $complete ? 'opacity-80' : '' }}">
                        <td>
                            <div class="flex items-center gap-2">
                                <a href="{{ route('orders.show', $order) }}"
                                   class="font-mono font-semibold text-primary-600 dark:text-primary-400 hover:underline">
                                    {{ $order->order_number }}
                                </a>
                                @if($isPartial)
                                    <x-ui.badge color="warning" class="text-xs">parcial</x-ui.badge>
                                @endif
                                @if($complete)
                                    <x-heroicon-s-check-circle class="w-4 h-4 text-green-500 flex-shrink-0" />
                                @endif
                            </div>
                        </td>
                        <td>{{ $order->customer_name }}</td>
                        <td class="text-sm text-gray-500 dark:text-zinc-400">
                            {{ $addr['city'] ?? '—' }}{{ !empty($addr['state']) ? '/' . $addr['state'] : '' }}
                        </td>
                        <td class="text-center">{{ $order->items->sum('quantity') }}</td>
                        <td class="text-right font-medium">R$ {{ number_format($order->total, 2, ',', '.') }}</td>
                        <td class="text-center font-semibold">{{ $item->volumes }}</td>
                        <td class="text-center">
                            <span class="{{ $complete ? 'text-green-600 dark:text-green-400 font-bold' : '' }}">
                                {{ $item->volumes_scanned }}/{{ $item->volumes }}
                            </span>
                        </td>
                        <td class="text-sm">
                            @if($deadline)
                                @php
                                    $dl = \Carbon\Carbon::parse($deadline);
                                    $isOverdue = $dl->isPast();
                                @endphp
                                <span class="{{ $isOverdue ? 'text-red-500' : 'text-gray-600 dark:text-zinc-400' }}">
                                    {{ $dl->format('d/m H:i') }}
                                </span>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('orders.show', $order) }}" class="btn-ghost btn-xs">
                                <x-heroicon-o-eye class="w-3.5 h-3.5" />
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </x-ui.card>

        {{-- Info romaneio --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <x-ui.card title="Informações">
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-zinc-400">Criado por</span>
                        <span>{{ $romaneio->createdBy?->name ?? '—' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-zinc-400">Criado em</span>
                        <span>{{ $romaneio->created_at->format('d/m/Y H:i') }}</span>
                    </div>
                    @if($romaneio->isClosed())
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-zinc-400">Fechado por</span>
                        <span>{{ $romaneio->closedBy?->name ?? '—' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-zinc-400">Fechado em</span>
                        <span>{{ $romaneio->closed_at?->format('d/m/Y H:i') ?? '—' }}</span>
                    </div>
                    @endif
                    @if($romaneio->notes)
                    <div class="pt-2 border-t border-gray-100 dark:border-zinc-800">
                        <p class="text-gray-600 dark:text-zinc-400">{{ $romaneio->notes }}</p>
                    </div>
                    @endif
                </div>
            </x-ui.card>

            @if($romaneio->isOpen())
            <x-ui.card title="Ações">
                <div class="space-y-3">
                    <a href="{{ route('romaneios.board', $romaneio) }}"
                       class="btn-primary w-full justify-center">
                        <x-heroicon-o-qr-code class="w-4 h-4" />
                        Abrir tela de bipagem
                    </a>
                    <form method="POST" action="{{ route('romaneios.close', $romaneio) }}"
                          onsubmit="return confirm('Fechar romaneio e marcar todos os pedidos como despachados?')">
                        @csrf
                        <button type="submit" class="btn-secondary w-full justify-center text-green-600 border-green-600">
                            <x-heroicon-o-check-circle class="w-4 h-4" />
                            Fechar romaneio (sem bipagem)
                        </button>
                    </form>
                </div>
            </x-ui.card>
            @endif
        </div>
    </div>
</x-app-layout>
