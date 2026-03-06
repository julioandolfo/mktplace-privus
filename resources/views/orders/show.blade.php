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

    @php
        $meta = $order->meta ?? [];
        $isFulfillment = $meta['is_fulfillment'] ?? false;
        $isFromML = $order->marketplaceAccount !== null;
        $packId = $meta['pack_id'] ?? $order->external_id;
        $mlLink = null;
        // ML order link
        if ($order->external_id && $isFromML) {
            $mlLink = 'https://www.mercadolivre.com.br/vendas/' . $order->external_id . '/detalhe';
        }
    @endphp

    {{-- Fulfillment badge --}}
    @if($isFulfillment)
    <div class="mb-4 flex items-center gap-2 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded-lg px-4 py-2 text-sm text-yellow-800 dark:text-yellow-300">
        <x-heroicon-o-cube class="w-4 h-4 flex-shrink-0" />
        Pedido <strong>Fulfillment</strong> — Estoque e envio gerenciados pelo Mercado Livre.
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- ═══ Main Column ══════════════════════════════════════════════════ --}}
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
                        @php
                            $itemMeta = $item->meta ?? [];
                            $variationAttrs = $itemMeta['ml_variation_attrs'] ?? [];
                        @endphp
                        <tr>
                            <td>
                                <div class="font-medium">{{ $item->name }}</div>
                                @if(!empty($variationAttrs))
                                <div class="text-xs text-gray-400 dark:text-zinc-500 mt-0.5">
                                    @foreach($variationAttrs as $attr)
                                        <span>{{ $attr['name'] ?? '' }}: {{ $attr['value_name'] ?? '' }}</span>
                                        @if(!$loop->last) · @endif
                                    @endforeach
                                </div>
                                @endif
                            </td>
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
                    @if(!empty($meta['ml_installments']) && $meta['ml_installments'] > 1)
                    <p class="text-xs text-gray-400 dark:text-zinc-500">
                        {{ $meta['ml_installments'] }}x de R$ {{ number_format($order->total / $meta['ml_installments'], 2, ',', '.') }}
                    </p>
                    @endif
                </div>
            </x-ui.card>

            {{-- Messages (Mercado Livre) --}}
            @if($isFromML && $packId)
            <x-ui.card>
                <x-slot name="title">
                    <div class="flex items-center gap-2">
                        Mensagens
                        @if($unreadMessages > 0)
                        <span class="inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-white bg-red-500 rounded-full">
                            {{ $unreadMessages }}
                        </span>
                        @endif
                    </div>
                </x-slot>
                <livewire:orders.order-messages :order="$order" />
            </x-ui.card>
            @endif

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

        {{-- ═══ Sidebar ══════════════════════════════════════════════════════ --}}
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
                    @php
                        $paymentMethodLabel = match($meta['ml_payment_method'] ?? $order->payment_method ?? null) {
                            'pix'          => 'PIX',
                            'credit_card'  => 'Cartão de Crédito',
                            'debit_card'   => 'Cartão de Débito',
                            'bolbradesco'  => 'Boleto',
                            'account_money'=> 'Saldo ML',
                            default        => $meta['ml_payment_method'] ?? $order->payment_method ?? null,
                        };
                    @endphp
                    @if($paymentMethodLabel)
                    <div>
                        <span class="text-xs text-gray-500 dark:text-zinc-400">Forma de Pagamento</span>
                        <p class="text-sm mt-1 flex items-center gap-1.5">
                            @if(($meta['ml_payment_method'] ?? '') === 'pix')
                                <span class="inline-block w-4 h-4 bg-teal-500 rounded-sm text-white text-[9px] flex items-center justify-center font-bold">P</span>
                            @endif
                            {{ $paymentMethodLabel }}
                            @if(!empty($meta['ml_installments']) && $meta['ml_installments'] > 1)
                                <span class="text-gray-400 dark:text-zinc-500">{{ $meta['ml_installments'] }}x</span>
                            @endif
                        </p>
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
                    @if($order->customer)
                    <a href="{{ route('customers.show', $order->customer) }}" class="text-xs text-primary-500 hover:text-primary-400 flex items-center gap-1">
                        Ver perfil do cliente
                        <x-heroicon-o-arrow-top-right-on-square class="w-3 h-3" />
                    </a>
                    @endif
                </div>
            </x-ui.card>

            {{-- Shipping --}}
            @if($order->shipping_address || $order->tracking_code || !empty($meta['ml_shipping_id']))
            <x-ui.card title="Envio">
                <div class="space-y-3">

                    {{-- Shipping method & mode --}}
                    @if($order->shipping_method || !empty($meta['ml_shipping_mode']))
                    <div>
                        <span class="text-xs text-gray-500 dark:text-zinc-400">Metodo</span>
                        <p class="text-sm mt-1">
                            {{ $order->shipping_method ?? '' }}
                            @if(!empty($meta['ml_shipping_mode']))
                            <span class="text-xs text-gray-400 dark:text-zinc-500">({{ $meta['ml_shipping_mode'] }})</span>
                            @endif
                        </p>
                    </div>
                    @endif

                    {{-- Tracking --}}
                    @if($order->tracking_code)
                    <div>
                        <span class="text-xs text-gray-500 dark:text-zinc-400">Codigo de Rastreio</span>
                        <div class="flex items-center gap-2 mt-1">
                            <p class="text-sm font-mono">{{ $order->tracking_code }}</p>
                            <a href="https://www.linkcorretos.com.br/{{ $order->tracking_code }}" target="_blank"
                               class="text-primary-500 hover:text-primary-400 transition-colors" title="Rastrear">
                                <x-heroicon-o-arrow-top-right-on-square class="w-3.5 h-3.5" />
                            </a>
                        </div>
                    </div>
                    @endif

                    {{-- Estimated delivery --}}
                    @if(!empty($meta['ml_estimated_delivery']))
                    <div>
                        <span class="text-xs text-gray-500 dark:text-zinc-400">Entrega Prevista</span>
                        <p class="text-sm mt-1">
                            {{ \Carbon\Carbon::parse($meta['ml_estimated_delivery'])->format('d/m/Y') }}
                        </p>
                    </div>
                    @endif

                    {{-- Address --}}
                    @if($order->shipping_address)
                    <div>
                        <span class="text-xs text-gray-500 dark:text-zinc-400">Endereco de Entrega</span>
                        <div class="text-sm mt-1 space-y-0.5">
                            @php $addr = $order->shipping_address; @endphp
                            <p>{{ $addr['street'] ?? '' }}</p>
                            @if(!empty($addr['complement'])) <p class="text-gray-500 dark:text-zinc-400">{{ $addr['complement'] }}</p> @endif
                            @if(!empty($addr['neighborhood'])) <p class="text-gray-500 dark:text-zinc-400">{{ $addr['neighborhood'] }}</p> @endif
                            <p>{{ $addr['city'] ?? '' }} - {{ $addr['state'] ?? '' }}</p>
                            @if(!empty($addr['zip'])) <p class="text-gray-500 dark:text-zinc-400">CEP: {{ $addr['zip'] }}</p> @endif
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
                        <span>{{ $order->marketplaceAccount->account_name }}</span>
                    </div>
                    @if($order->external_id)
                    <div class="flex justify-between items-center">
                        <span class="text-gray-500 dark:text-zinc-400">ID Externo</span>
                        <span class="font-mono text-xs">{{ $order->external_id }}</span>
                    </div>
                    @endif
                    @if($packId && $packId !== $order->external_id)
                    <div class="flex justify-between items-center">
                        <span class="text-gray-500 dark:text-zinc-400">Pack ID</span>
                        <span class="font-mono text-xs">{{ $packId }}</span>
                    </div>
                    @endif
                    @if(!empty($meta['ml_shipping_id']))
                    <div class="flex justify-between items-center">
                        <span class="text-gray-500 dark:text-zinc-400">Envio ID</span>
                        <span class="font-mono text-xs">{{ $meta['ml_shipping_id'] }}</span>
                    </div>
                    @endif
                    @if($mlLink)
                    <a href="{{ $mlLink }}" target="_blank"
                       class="flex items-center gap-1.5 text-primary-500 hover:text-primary-400 transition-colors pt-1">
                        <x-heroicon-o-arrow-top-right-on-square class="w-3.5 h-3.5" />
                        Ver pedido no ML
                    </a>
                    @endif

                    {{-- Feedback --}}
                    @if(!empty($meta['ml_feedback']))
                    <div class="pt-2 border-t border-gray-100 dark:border-zinc-800">
                        <span class="text-xs text-gray-500 dark:text-zinc-400 block mb-1">Avaliacao do comprador</span>
                        <div class="flex items-center gap-1">
                            @php
                                $feedbackRating = $meta['ml_feedback']['rating'] ?? null;
                            @endphp
                            @if($feedbackRating === 'positive')
                                <x-heroicon-s-hand-thumb-up class="w-4 h-4 text-emerald-500" />
                                <span class="text-xs text-emerald-500">Positiva</span>
                            @elseif($feedbackRating === 'negative')
                                <x-heroicon-s-hand-thumb-down class="w-4 h-4 text-red-500" />
                                <span class="text-xs text-red-500">Negativa</span>
                            @elseif($feedbackRating === 'neutral')
                                <x-heroicon-o-minus-circle class="w-4 h-4 text-gray-400" />
                                <span class="text-xs text-gray-400">Neutra</span>
                            @endif
                        </div>
                    </div>
                    @endif
                </div>
            </x-ui.card>
            @endif
        </div>
    </div>
</x-app-layout>
