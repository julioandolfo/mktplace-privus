<div>
    {{-- ─── Filters ─────────────────────────────────────────────────────── --}}
    <div class="card p-4 mb-4" x-data="{ showAdvanced: {{ ($dateFrom || $dateTo || $shippedFrom || $shippedTo || $hasTracking !== '' || $isFulfillment !== '') ? 'true' : 'false' }} }">

        {{-- Row 1: search + status + payment + marketplace --}}
        <div class="flex flex-col sm:flex-row gap-3">
            <div class="flex-1 relative">
                <x-heroicon-o-magnifying-glass class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-zinc-500 pointer-events-none" />
                <input type="text" wire:model.live.debounce.300ms="search"
                       placeholder="Buscar por número, cliente, CPF, e-mail ou rastreio..."
                       class="form-input pl-10 w-full" />
            </div>
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
            <select wire:model.live="marketplaceId" class="form-input w-52">
                <option value="">Todos Marketplaces</option>
                @foreach($marketplaceAccounts as $acc)
                    <option value="{{ $acc->id }}">{{ $acc->account_name }}</option>
                @endforeach
            </select>
        </div>

        {{-- Row 2: advanced filters (collapsible) --}}
        <div class="mt-3 pt-3 border-t border-gray-100 dark:border-zinc-700/50">
            <button type="button" @click="showAdvanced = !showAdvanced"
                    class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-zinc-400 hover:text-gray-700 dark:hover:text-zinc-200 transition-colors">
                <span :class="showAdvanced ? 'rotate-180' : ''" class="inline-flex transition-transform">
                    <x-heroicon-o-chevron-down class="w-3.5 h-3.5" />
                </span>
                Filtros Avancados
                @if($hasActiveFilters)
                    <span class="ml-1 inline-flex items-center justify-center w-4 h-4 text-[10px] font-bold text-white bg-primary-500 rounded-full">!</span>
                @endif
            </button>

            <div x-show="showAdvanced" x-transition class="mt-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                {{-- Data criação: de --}}
                <div>
                    <label class="block text-xs text-gray-500 dark:text-zinc-400 mb-1">Pedido a partir de</label>
                    <input type="date" wire:model.live="dateFrom" class="form-input w-full text-sm" />
                </div>
                {{-- Data criação: até --}}
                <div>
                    <label class="block text-xs text-gray-500 dark:text-zinc-400 mb-1">Pedido até</label>
                    <input type="date" wire:model.live="dateTo" class="form-input w-full text-sm" />
                </div>
                {{-- Data envio: de --}}
                <div>
                    <label class="block text-xs text-gray-500 dark:text-zinc-400 mb-1">Envio a partir de</label>
                    <input type="date" wire:model.live="shippedFrom" class="form-input w-full text-sm" />
                </div>
                {{-- Data envio: até --}}
                <div>
                    <label class="block text-xs text-gray-500 dark:text-zinc-400 mb-1">Envio até</label>
                    <input type="date" wire:model.live="shippedTo" class="form-input w-full text-sm" />
                </div>
                {{-- Código de rastreio --}}
                <div>
                    <label class="block text-xs text-gray-500 dark:text-zinc-400 mb-1">Rastreio</label>
                    <select wire:model.live="hasTracking" class="form-input w-full text-sm">
                        <option value="">Todos</option>
                        <option value="1">Com rastreio</option>
                        <option value="0">Sem rastreio</option>
                    </select>
                </div>
                {{-- Fulfillment --}}
                <div>
                    <label class="block text-xs text-gray-500 dark:text-zinc-400 mb-1">Fulfillment</label>
                    <select wire:model.live="isFulfillment" class="form-input w-full text-sm">
                        <option value="">Todos</option>
                        <option value="1">Fulfillment</option>
                        <option value="0">Normal</option>
                    </select>
                </div>
                {{-- Clear button --}}
                @if($hasActiveFilters)
                <div class="flex items-end">
                    <button wire:click="clearFilters" class="btn-secondary btn-sm w-full">
                        <x-heroicon-o-x-mark class="w-4 h-4" />
                        Limpar Filtros
                    </button>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ─── Table ───────────────────────────────────────────────────────── --}}
    @if($orders->isEmpty())
        <x-ui.card>
            <x-ui.empty-state
                title="Nenhum pedido encontrado"
                description="{{ $hasActiveFilters ? 'Tente ajustar os filtros.' : 'Nenhum pedido cadastrado ainda.' }}">
                <x-slot name="icon">
                    <x-heroicon-o-shopping-bag class="w-8 h-8 text-gray-400 dark:text-zinc-500" />
                </x-slot>
                @if($hasActiveFilters)
                <x-slot name="action">
                    <button wire:click="clearFilters" class="btn-secondary">
                        <x-heroicon-o-x-mark class="w-4 h-4" />
                        Limpar Filtros
                    </button>
                </x-slot>
                @endif
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
                        <th class="text-center">Itens</th>
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
                                Pedido em
                                @if($sortField === 'created_at')
                                    <x-heroicon-s-chevron-up class="w-3 h-3 {{ $sortDirection === 'desc' ? 'rotate-180' : '' }}" />
                                @endif
                            </button>
                        </th>
                        <th>
                            <button wire:click="sortBy('shipped_at')" class="flex items-center gap-1 hover:text-gray-700 dark:hover:text-zinc-200">
                                Enviado em
                                @if($sortField === 'shipped_at')
                                    <x-heroicon-s-chevron-up class="w-3 h-3 {{ $sortDirection === 'desc' ? 'rotate-180' : '' }}" />
                                @endif
                            </button>
                        </th>
                        <th class="text-right">Acoes</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($orders as $order)
                    @php
                        $mlType = $order->marketplaceAccount?->marketplace_type;
                    @endphp
                    <tr wire:key="order-{{ $order->id }}">

                        {{-- Pedido + canal --}}
                        <td>
                            <div class="flex items-start gap-2">
                                {{-- Ícone do marketplace --}}
                                @if($mlType)
                                    <span class="mt-0.5 flex-shrink-0" title="{{ $order->marketplaceAccount->account_name }}">{!! $mlType->logoSvg('w-5 h-5') !!}</span>
                                @endif
                                <div>
                                    <a href="{{ route('orders.show', $order) }}" class="font-mono font-medium text-primary-600 dark:text-primary-400 hover:underline">
                                        {{ $order->order_number }}
                                    </a>
                                    @if($order->marketplaceAccount)
                                        <p class="text-xs text-gray-400 dark:text-zinc-500">{{ $order->marketplaceAccount->account_name }}</p>
                                    @endif
                                    @if(!empty($order->meta['is_fulfillment']))
                                        <span class="text-[10px] font-medium text-yellow-600 dark:text-yellow-400 bg-yellow-50 dark:bg-yellow-900/20 px-1 rounded">FULL</span>
                                    @endif
                                </div>
                            </div>
                        </td>

                        {{-- Cliente --}}
                        <td>
                            <div>
                                <span class="font-medium text-gray-900 dark:text-white">{{ $order->customer_name }}</span>
                                @if($order->customer_email)
                                    <p class="text-xs text-gray-500 dark:text-zinc-400">{{ $order->customer_email }}</p>
                                @endif
                            </div>
                        </td>

                        {{-- Itens --}}
                        <td class="text-center">{{ $order->items->sum('quantity') }}</td>

                        {{-- Total --}}
                        <td class="font-medium font-mono">R$ {{ number_format($order->total, 2, ',', '.') }}</td>

                        {{-- Status --}}
                        <td><x-ui.badge :color="$order->status->color()">{{ $order->status->label() }}</x-ui.badge></td>

                        {{-- Pagamento --}}
                        <td><x-ui.badge :color="$order->payment_status->color()">{{ $order->payment_status->label() }}</x-ui.badge></td>

                        {{-- Data pedido --}}
                        <td class="text-sm text-gray-500 dark:text-zinc-400 whitespace-nowrap">
                            {{ $order->created_at->format('d/m/Y') }}
                            <br><span class="text-xs">{{ $order->created_at->format('H:i') }}</span>
                        </td>

                        {{-- Data envio --}}
                        <td class="text-sm whitespace-nowrap">
                            @if($order->shipped_at)
                                <span class="text-gray-500 dark:text-zinc-400">{{ $order->shipped_at->format('d/m/Y') }}</span>
                                <br><span class="text-xs text-gray-400 dark:text-zinc-500">{{ $order->shipped_at->format('H:i') }}</span>
                            @elseif(!in_array($order->status->value, ['cancelled', 'delivered', 'returned']))
                                <span class="inline-flex items-center gap-1 text-xs font-medium text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 px-2 py-0.5 rounded-full">
                                    <x-heroicon-o-clock class="w-3 h-3" />
                                    A ENVIAR
                                </span>
                            @else
                                <span class="text-gray-300 dark:text-zinc-600">—</span>
                            @endif
                        </td>

                        {{-- Ações --}}
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
