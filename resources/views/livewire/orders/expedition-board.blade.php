<div>
    {{-- Stats --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Pronto p/ Envio</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ $counts['ready_to_ship'] }}</p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                    <x-heroicon-o-archive-box-arrow-down class="w-5 h-5 text-green-600 dark:text-green-400" />
                </div>
            </div>
        </div>
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Enviados</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ $counts['shipped'] }}</p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                    <x-heroicon-o-truck class="w-5 h-5 text-blue-600 dark:text-blue-400" />
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
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Buscar por numero, cliente ou rastreio..."
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

    {{-- Tracking Modal --}}
    @if($showTrackingModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @keydown.escape.window="$wire.set('showTrackingModal', false)">
        <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-xl w-full max-w-md mx-4 p-6" @click.outside="$wire.set('showTrackingModal', false)">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Informar Rastreio</h3>
            <div class="space-y-4">
                <div>
                    <label class="form-label">Codigo de Rastreio *</label>
                    <input type="text" wire:model="trackingCode" class="form-input font-mono" placeholder="Ex: BR123456789BR">
                    @error('trackingCode') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">Metodo de Envio</label>
                    <input type="text" wire:model="shippingMethod" class="form-input" placeholder="Ex: Correios PAC">
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <button wire:click="$set('showTrackingModal', false)" class="btn-secondary">Cancelar</button>
                <button wire:click="saveTracking" class="btn-primary">
                    <x-heroicon-s-check class="w-4 h-4" />
                    Salvar Rastreio
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Table --}}
    @if($orders->isEmpty())
        <x-ui.card>
            <x-ui.empty-state
                title="Nenhum pedido na expedicao"
                description="Pedidos prontos para envio aparecerao aqui automaticamente.">
                <x-slot name="icon">
                    <x-heroicon-o-truck class="w-8 h-8 text-gray-400 dark:text-zinc-500" />
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
                        <th>Endereco</th>
                        <th>Rastreio</th>
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
                    <tr wire:key="exp-{{ $order->id }}">
                        <td>
                            <a href="{{ route('orders.show', $order) }}" class="font-mono font-medium text-primary-600 dark:text-primary-400 hover:underline">
                                {{ $order->order_number }}
                            </a>
                        </td>
                        <td class="font-medium text-gray-900 dark:text-white">
                            {{ $order->customer_name }}
                            @if($order->customer_phone)
                            <p class="text-xs text-gray-500 dark:text-zinc-400">{{ $order->customer_phone }}</p>
                            @endif
                        </td>
                        <td class="text-sm text-gray-600 dark:text-zinc-400">
                            @if($order->shipping_address)
                                @php $addr = $order->shipping_address; @endphp
                                {{ $addr['city'] ?? '' }}{{ !empty($addr['state']) ? ' - ' . $addr['state'] : '' }}
                                @if(!empty($addr['zipcode']))
                                <br><span class="text-xs">{{ $addr['zipcode'] }}</span>
                                @endif
                            @else
                                <span class="text-gray-400 dark:text-zinc-500">-</span>
                            @endif
                        </td>
                        <td>
                            @if($order->tracking_code)
                                <span class="font-mono text-sm">{{ $order->tracking_code }}</span>
                                @if($order->shipping_method)
                                <p class="text-xs text-gray-500 dark:text-zinc-400">{{ $order->shipping_method }}</p>
                                @endif
                            @else
                                <button wire:click="openTrackingModal({{ $order->id }})" class="text-xs text-primary-600 dark:text-primary-400 hover:underline">
                                    + Adicionar
                                </button>
                            @endif
                        </td>
                        <td>
                            <x-ui.badge :color="$order->status->color()">{{ $order->status->label() }}</x-ui.badge>
                        </td>
                        <td class="text-sm text-gray-500 dark:text-zinc-400">
                            {{ $order->created_at->format('d/m/Y') }}
                        </td>
                        <td class="text-right">
                            <div class="flex items-center justify-end gap-1">
                                @if($order->status->value === 'ready_to_ship')
                                    <button wire:click="markShipped({{ $order->id }})" class="btn-primary btn-xs">
                                        <x-heroicon-o-truck class="w-3.5 h-3.5" />
                                        Enviar
                                    </button>
                                @elseif($order->status->value === 'shipped')
                                    <button wire:click="markDelivered({{ $order->id }})" wire:confirm="Confirmar entrega deste pedido?" class="btn-secondary btn-xs">
                                        <x-heroicon-o-check-circle class="w-3.5 h-3.5" />
                                        Entregue
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
