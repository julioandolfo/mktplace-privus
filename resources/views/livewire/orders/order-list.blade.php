<div>
    {{-- Filters --}}
    <div class="card p-4 mb-6">
        <div class="flex flex-col sm:flex-row gap-4">
            <div class="flex-1">
                <div class="relative">
                    <x-heroicon-o-magnifying-glass class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-zinc-500" />
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Buscar por numero, cliente, documento ou rastreio..."
                           class="form-input pl-10">
                </div>
            </div>
            <div class="flex gap-3">
                <select wire:model.live="status" class="form-input w-44">
                    <option value="">Todos Status</option>
                    @foreach($statuses as $s)
                        <option value="{{ $s->value }}">{{ $s->label() }}</option>
                    @endforeach
                </select>
                <select wire:model.live="paymentStatus" class="form-input w-44">
                    <option value="">Todos Pagamentos</option>
                    @foreach($paymentStatuses as $ps)
                        <option value="{{ $ps->value }}">{{ $ps->label() }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    {{-- Table --}}
    @if($orders->isEmpty())
        <x-ui.card>
            <x-ui.empty-state
                title="Nenhum pedido encontrado"
                description="{{ $search ? 'Tente refinar sua busca.' : 'Cadastre seu primeiro pedido para comecar.' }}">
                <x-slot name="icon">
                    <x-heroicon-o-shopping-bag class="w-8 h-8 text-gray-400 dark:text-zinc-500" />
                </x-slot>
                @unless($search)
                <x-slot name="action">
                    <a href="{{ route('orders.create') }}" class="btn-primary">
                        <x-heroicon-s-plus class="w-4 h-4" />
                        Novo Pedido
                    </a>
                </x-slot>
                @endunless
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
                        <th>
                            <button wire:click="sortBy('customer_name')" class="flex items-center gap-1 hover:text-gray-700 dark:hover:text-zinc-200">
                                Cliente
                                @if($sortField === 'customer_name')
                                    <x-heroicon-s-chevron-up class="w-3 h-3 {{ $sortDirection === 'desc' ? 'rotate-180' : '' }}" />
                                @endif
                            </button>
                        </th>
                        <th>Itens</th>
                        <th>
                            <button wire:click="sortBy('total')" class="flex items-center gap-1 hover:text-gray-700 dark:hover:text-zinc-200">
                                Total
                                @if($sortField === 'total')
                                    <x-heroicon-s-chevron-up class="w-3 h-3 {{ $sortDirection === 'desc' ? 'rotate-180' : '' }}" />
                                @endif
                            </button>
                        </th>
                        <th>Status</th>
                        <th>Pagamento</th>
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
                    <tr wire:key="order-{{ $order->id }}">
                        <td>
                            <a href="{{ route('orders.show', $order) }}" class="font-mono font-medium text-primary-600 dark:text-primary-400 hover:underline">
                                {{ $order->order_number }}
                            </a>
                            @if($order->marketplaceAccount)
                            <p class="text-xs text-gray-500 dark:text-zinc-400">{{ $order->marketplaceAccount->name }}</p>
                            @endif
                        </td>
                        <td>
                            <div>
                                <span class="font-medium text-gray-900 dark:text-white">{{ $order->customer_name }}</span>
                                @if($order->customer_email)
                                <p class="text-xs text-gray-500 dark:text-zinc-400">{{ $order->customer_email }}</p>
                                @endif
                            </div>
                        </td>
                        <td class="text-center">
                            {{ $order->items->sum('quantity') }}
                        </td>
                        <td class="font-medium">R$ {{ number_format($order->total, 2, ',', '.') }}</td>
                        <td>
                            <x-ui.badge :color="$order->status->color()">{{ $order->status->label() }}</x-ui.badge>
                        </td>
                        <td>
                            <x-ui.badge :color="$order->payment_status->color()">{{ $order->payment_status->label() }}</x-ui.badge>
                        </td>
                        <td class="text-sm text-gray-500 dark:text-zinc-400">
                            {{ $order->created_at->format('d/m/Y') }}
                            <br>
                            <span class="text-xs">{{ $order->created_at->format('H:i') }}</span>
                        </td>
                        <td class="text-right">
                            <div class="flex items-center justify-end gap-1" x-data="{ open: false }">
                                <a href="{{ route('orders.show', $order) }}" class="btn-ghost btn-xs">
                                    <x-heroicon-o-eye class="w-4 h-4" />
                                </a>
                                <div class="relative">
                                    <button @click="open = !open" class="btn-ghost btn-xs">
                                        <x-heroicon-o-ellipsis-vertical class="w-4 h-4" />
                                    </button>
                                    <div x-show="open" @click.away="open = false" x-transition
                                         class="absolute right-0 mt-1 w-40 bg-white dark:bg-zinc-800 rounded-lg shadow-lg border border-gray-200 dark:border-zinc-700 py-1 z-10">
                                        @if($order->is_editable)
                                        <a href="{{ route('orders.edit', $order) }}" @click="open = false"
                                           class="w-full flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-zinc-300 hover:bg-gray-100 dark:hover:bg-zinc-700">
                                            <x-heroicon-o-pencil-square class="w-4 h-4" />
                                            Editar
                                        </a>
                                        @endif
                                        @if($order->is_cancellable)
                                        <button wire:click="cancelOrder({{ $order->id }})" wire:confirm="Tem certeza que deseja cancelar este pedido?" @click="open = false"
                                                class="w-full flex items-center gap-2 px-3 py-2 text-sm text-yellow-600 dark:text-yellow-400 hover:bg-gray-100 dark:hover:bg-zinc-700">
                                            <x-heroicon-o-x-circle class="w-4 h-4" />
                                            Cancelar
                                        </button>
                                        @endif
                                        @if($order->status->value === 'cancelled')
                                        <button wire:click="deleteOrder({{ $order->id }})" wire:confirm="Tem certeza que deseja remover este pedido?" @click="open = false"
                                                class="w-full flex items-center gap-2 px-3 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-gray-100 dark:hover:bg-zinc-700">
                                            <x-heroicon-o-trash class="w-4 h-4" />
                                            Remover
                                        </button>
                                        @endif
                                    </div>
                                </div>
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
