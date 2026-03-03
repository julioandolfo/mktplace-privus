<x-app-layout>
    <x-slot name="header">Pedido {{ $order->order_number }}</x-slot>
    <x-slot name="actions">
        @if($order->is_editable)
        <a href="{{ route('orders.edit', $order) }}" class="btn-secondary">
            <x-heroicon-o-pencil-square class="w-4 h-4" />
            Editar
        </a>
        @endif
    </x-slot>
    <x-slot name="breadcrumbs">
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <a href="{{ route('orders.index') }}" class="hover:text-gray-700 dark:hover:text-zinc-200">Pedidos</a>
        </li>
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <span class="text-gray-700 dark:text-zinc-200">{{ $order->order_number }}</span>
        </li>
    </x-slot>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Main content --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Items --}}
            <x-ui.card title="Itens do Pedido" :padding="false">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Produto</th>
                            <th>SKU</th>
                            <th class="text-center">Qtd</th>
                            <th class="text-right">Preco Unit.</th>
                            <th class="text-right">Desconto</th>
                            <th class="text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($order->items as $item)
                        <tr>
                            <td class="font-medium">{{ $item->name }}</td>
                            <td class="font-mono text-sm">{{ $item->sku }}</td>
                            <td class="text-center">{{ $item->quantity }}</td>
                            <td class="text-right">R$ {{ number_format($item->unit_price, 2, ',', '.') }}</td>
                            <td class="text-right">R$ {{ number_format($item->discount, 2, ',', '.') }}</td>
                            <td class="text-right font-medium">R$ {{ number_format($item->total, 2, ',', '.') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-ui.card>

            {{-- Totals --}}
            <x-ui.card title="Totais">
                <div class="space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600 dark:text-zinc-400">Subtotal</span>
                        <span>R$ {{ number_format($order->subtotal, 2, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600 dark:text-zinc-400">Frete</span>
                        <span>R$ {{ number_format($order->shipping_cost, 2, ',', '.') }}</span>
                    </div>
                    @if($order->discount > 0)
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600 dark:text-zinc-400">Desconto</span>
                        <span class="text-red-600 dark:text-red-400">- R$ {{ number_format($order->discount, 2, ',', '.') }}</span>
                    </div>
                    @endif
                    <div class="flex justify-between font-medium text-base pt-2 border-t border-gray-200 dark:border-zinc-700">
                        <span>Total</span>
                        <span>R$ {{ number_format($order->total, 2, ',', '.') }}</span>
                    </div>
                </div>
            </x-ui.card>

            {{-- Notes --}}
            @if($order->notes || $order->internal_notes)
            <x-ui.card title="Observacoes">
                @if($order->notes)
                <div class="mb-4">
                    <h4 class="text-sm font-medium text-gray-700 dark:text-zinc-300 mb-1">Observacoes do Pedido</h4>
                    <p class="text-sm text-gray-600 dark:text-zinc-400">{{ $order->notes }}</p>
                </div>
                @endif
                @if($order->internal_notes)
                <div>
                    <h4 class="text-sm font-medium text-gray-700 dark:text-zinc-300 mb-1">Notas Internas</h4>
                    <p class="text-sm text-gray-600 dark:text-zinc-400">{{ $order->internal_notes }}</p>
                </div>
                @endif
            </x-ui.card>
            @endif
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">
            {{-- Status --}}
            <x-ui.card title="Status">
                <div class="space-y-3">
                    <div>
                        <span class="text-xs text-gray-500 dark:text-zinc-400">Status do Pedido</span>
                        <div class="mt-1">
                            <x-ui.badge :color="$order->status->color()">{{ $order->status->label() }}</x-ui.badge>
                        </div>
                    </div>
                    <div>
                        <span class="text-xs text-gray-500 dark:text-zinc-400">Pagamento</span>
                        <div class="mt-1">
                            <x-ui.badge :color="$order->payment_status->color()">{{ $order->payment_status->label() }}</x-ui.badge>
                        </div>
                    </div>
                    @if($order->payment_method)
                    <div>
                        <span class="text-xs text-gray-500 dark:text-zinc-400">Forma de Pagamento</span>
                        <p class="text-sm mt-1">{{ $order->payment_method }}</p>
                    </div>
                    @endif
                </div>
            </x-ui.card>

            {{-- Customer --}}
            <x-ui.card title="Cliente">
                <div class="space-y-2">
                    <p class="font-medium">{{ $order->customer_name }}</p>
                    @if($order->customer_email)
                    <p class="text-sm text-gray-600 dark:text-zinc-400">{{ $order->customer_email }}</p>
                    @endif
                    @if($order->customer_phone)
                    <p class="text-sm text-gray-600 dark:text-zinc-400">{{ $order->customer_phone }}</p>
                    @endif
                    @if($order->customer_document)
                    <p class="text-sm text-gray-600 dark:text-zinc-400">{{ $order->customer_document }}</p>
                    @endif
                </div>
            </x-ui.card>

            {{-- Shipping --}}
            @if($order->shipping_address || $order->tracking_code)
            <x-ui.card title="Envio">
                <div class="space-y-3">
                    @if($order->shipping_method)
                    <div>
                        <span class="text-xs text-gray-500 dark:text-zinc-400">Metodo de Envio</span>
                        <p class="text-sm mt-1">{{ $order->shipping_method }}</p>
                    </div>
                    @endif
                    @if($order->tracking_code)
                    <div>
                        <span class="text-xs text-gray-500 dark:text-zinc-400">Codigo de Rastreio</span>
                        <p class="text-sm font-mono mt-1">{{ $order->tracking_code }}</p>
                    </div>
                    @endif
                    @if($order->shipping_address)
                    <div>
                        <span class="text-xs text-gray-500 dark:text-zinc-400">Endereco</span>
                        <div class="text-sm mt-1">
                            @php $addr = $order->shipping_address; @endphp
                            <p>{{ $addr['street'] ?? '' }}, {{ $addr['number'] ?? '' }}</p>
                            @if(!empty($addr['complement'])) <p>{{ $addr['complement'] }}</p> @endif
                            <p>{{ $addr['neighborhood'] ?? '' }}</p>
                            <p>{{ $addr['city'] ?? '' }} - {{ $addr['state'] ?? '' }}</p>
                            @if(!empty($addr['zipcode'])) <p>CEP: {{ $addr['zipcode'] }}</p> @endif
                        </div>
                    </div>
                    @endif
                </div>
            </x-ui.card>
            @endif

            {{-- Timeline --}}
            <x-ui.card title="Historico">
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-zinc-400">Criado em</span>
                        <span>{{ $order->created_at->format('d/m/Y H:i') }}</span>
                    </div>
                    @if($order->paid_at)
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-zinc-400">Pago em</span>
                        <span>{{ $order->paid_at->format('d/m/Y H:i') }}</span>
                    </div>
                    @endif
                    @if($order->shipped_at)
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-zinc-400">Enviado em</span>
                        <span>{{ $order->shipped_at->format('d/m/Y H:i') }}</span>
                    </div>
                    @endif
                    @if($order->delivered_at)
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-zinc-400">Entregue em</span>
                        <span>{{ $order->delivered_at->format('d/m/Y H:i') }}</span>
                    </div>
                    @endif
                    @if($order->cancelled_at)
                    <div class="flex justify-between">
                        <span class="text-red-500">Cancelado em</span>
                        <span class="text-red-500">{{ $order->cancelled_at->format('d/m/Y H:i') }}</span>
                    </div>
                    @endif
                </div>
            </x-ui.card>

            {{-- Marketplace --}}
            @if($order->marketplaceAccount)
            <x-ui.card title="Marketplace">
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-zinc-400">Canal</span>
                        <span>{{ $order->marketplaceAccount->name }}</span>
                    </div>
                    @if($order->external_id)
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-zinc-400">ID Externo</span>
                        <span class="font-mono">{{ $order->external_id }}</span>
                    </div>
                    @endif
                </div>
            </x-ui.card>
            @endif
        </div>
    </div>
</x-app-layout>
