<div>
    {{-- Stats --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Confirmados</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ $counts['confirmed'] }}</p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                    <x-heroicon-o-clock class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                </div>
            </div>
        </div>
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Em Producao</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ $counts['in_production'] }}</p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-yellow-100 dark:bg-yellow-900/30 flex items-center justify-center">
                    <x-heroicon-o-wrench-screwdriver class="w-5 h-5 text-yellow-600 dark:text-yellow-400" />
                </div>
            </div>
        </div>
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Produzidos</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ $counts['produced'] }}</p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                    <x-heroicon-o-check-circle class="w-5 h-5 text-green-600 dark:text-green-400" />
                </div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card p-4 mb-6">
        <div class="flex flex-col sm:flex-row gap-4">
            <div class="flex-1">
                <div class="relative">
                    <x-heroicon-o-magnifying-glass class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-zinc-500" />
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Buscar por numero ou cliente..."
                           class="form-input pl-10">
                </div>
            </div>
            <div>
                <select wire:model.live="status" class="form-input w-44">
                    <option value="">Todos Status</option>
                    @foreach($statuses as $s)
                        <option value="{{ $s->value }}">{{ $s->label() }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    {{-- Table --}}
    @if($orders->isEmpty())
        <x-ui.card>
            <x-ui.empty-state
                title="Nenhum pedido na fila de producao"
                description="Pedidos confirmados aparecerao aqui automaticamente.">
                <x-slot name="icon">
                    <x-heroicon-o-wrench-screwdriver class="w-8 h-8 text-gray-400 dark:text-zinc-500" />
                </x-slot>
            </x-ui.empty-state>
        </x-ui.card>
    @else
        <x-ui.card :padding="false">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>
                            <button wire:click="sortBy('order_number')" class="flex items-center gap-1 hover:text-gray-700 dark:hover:text-zinc-200">
                                Pedido
                                @if($sortField === 'order_number')
                                    <x-heroicon-s-chevron-up class="w-3 h-3 {{ $sortDirection === 'desc' ? 'rotate-180' : '' }}" />
                                @endif
                            </button>
                        </th>
                        <th>Cliente</th>
                        <th>Itens</th>
                        <th>Status</th>
                        <th>
                            <button wire:click="sortBy('created_at')" class="flex items-center gap-1 hover:text-gray-700 dark:hover:text-zinc-200">
                                Data
                                @if($sortField === 'created_at')
                                    <x-heroicon-s-chevron-up class="w-3 h-3 {{ $sortDirection === 'desc' ? 'rotate-180' : '' }}" />
                                @endif
                            </button>
                        </th>
                        <th class="text-right">Acoes</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($orders as $order)
                    <tr wire:key="prod-{{ $order->id }}">
                        <td>
                            <a href="{{ route('orders.show', $order) }}" class="font-mono font-medium text-primary-600 dark:text-primary-400 hover:underline">
                                {{ $order->order_number }}
                            </a>
                        </td>
                        <td class="font-medium text-gray-900 dark:text-white">{{ $order->customer_name }}</td>
                        <td>
                            <span class="text-sm">{{ $order->items->sum('quantity') }} itens</span>
                            <div class="text-xs text-gray-500 dark:text-zinc-400 mt-0.5">
                                @foreach($order->items->take(2) as $item)
                                    {{ $item->name }}{{ !$loop->last ? ', ' : '' }}
                                @endforeach
                                @if($order->items->count() > 2)
                                    +{{ $order->items->count() - 2 }}
                                @endif
                            </div>
                        </td>
                        <td>
                            <x-ui.badge :color="$order->status->color()">{{ $order->status->label() }}</x-ui.badge>
                        </td>
                        <td class="text-sm text-gray-500 dark:text-zinc-400">
                            {{ $order->created_at->format('d/m/Y H:i') }}
                        </td>
                        <td class="text-right">
                            <div class="flex items-center justify-end gap-1">
                                @if($order->status->value === 'confirmed')
                                    <button wire:click="moveToProduction({{ $order->id }})" class="btn-secondary btn-xs">
                                        <x-heroicon-o-wrench-screwdriver class="w-3.5 h-3.5" />
                                        Produzir
                                    </button>
                                @elseif($order->status->value === 'in_production')
                                    <button wire:click="markProduced({{ $order->id }})" class="btn-secondary btn-xs">
                                        <x-heroicon-o-check class="w-3.5 h-3.5" />
                                        Produzido
                                    </button>
                                @elseif($order->status->value === 'produced')
                                    <button wire:click="markReadyToShip({{ $order->id }})" class="btn-primary btn-xs">
                                        <x-heroicon-o-truck class="w-3.5 h-3.5" />
                                        Pronto p/ Envio
                                    </button>
                                @endif
                                <a href="{{ route('orders.show', $order) }}" class="btn-ghost btn-xs">
                                    <x-heroicon-o-eye class="w-4 h-4" />
                                </a>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            @if($orders->hasPages())
            <div class="px-4 py-3 border-t border-gray-200 dark:border-zinc-700">
                {{ $orders->links() }}
            </div>
            @endif
        </x-ui.card>
    @endif
</div>
