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
        $meta          = $order->meta ?? [];
        $isFulfillment = $meta['is_fulfillment'] ?? false;
        $isFromML      = $order->marketplaceAccount?->marketplace_type === \App\Enums\MarketplaceType::MercadoLivre;
        $mlType        = $order->marketplaceAccount?->marketplace_type;
        $packId        = $meta['pack_id'] ?? $order->external_id;
        $mlShippingId  = $meta['ml_shipping_id'] ?? null;
        $mlLink        = null;
        if ($order->external_id && $isFromML) {
            $mlLink = 'https://www.mercadolivre.com.br/vendas/' . $order->external_id . '/detalhe';
        }
        // Smart tracking URL — only generate when there's a real tracking code or shipment was actually shipped
        $trackingUrl = null;
        $orderShipped = in_array($order->status->value, ['shipped', 'delivered', 'returned']);
        if ($order->tracking_code && $mlType) {
            $trackingUrl = $mlType->trackingUrl($order->tracking_code, $mlShippingId);
        } elseif ($order->tracking_code) {
            $trackingUrl = 'https://rastreamento.correios.com.br/app/index.php?objeto=' . $order->tracking_code;
        } elseif ($mlShippingId && $orderShipped && $isFromML) {
            $trackingUrl = 'https://rastreamento.mercadolivre.com.br/item/' . $mlShippingId;
        }
        // ML item → listing lookup for thumbnail/URL/internal ID
        $mlListings = [];
        if ($order->marketplaceAccount) {
            $mlItemIds = $order->items->map(fn($i) => $i->meta['ml_item_id'] ?? null)->filter()->values()->toArray();
            if (!empty($mlItemIds)) {
                $mlListings = \App\Models\MarketplaceListing::where('marketplace_account_id', $order->marketplaceAccount->id)
                    ->whereIn('external_id', $mlItemIds)
                    ->get(['id', 'external_id', 'meta'])
                    ->keyBy('external_id')
                    ->toArray();
            }
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
                            <th colspan="2">Produto</th>
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
                            $itemMeta       = $item->meta ?? [];
                            $variationAttrs = $itemMeta['ml_variation_attrs'] ?? [];
                            $mlItemId      = $itemMeta['ml_item_id'] ?? null;
                            $listingRecord = $mlItemId ? ($mlListings[$mlItemId] ?? null) : null;
                            // Internal listing URL (prefer) or external ML URL as fallback
                            $listingUrl = $listingRecord
                                ? route('listings.show', $listingRecord['id'])
                                : null;
                            // Thumbnail: ML listing meta → product primary image → placeholder
                            $thumbUrl = null;
                            if ($listingRecord) {
                                $listingMeta = $listingRecord['meta'] ?? [];
                                $thumbUrl = $listingMeta['thumbnail'] ?? ($listingMeta['live']['thumbnail'] ?? null);
                            }
                            if (!$thumbUrl && $item->product?->primaryImage) {
                                $thumbUrl = $item->product->primaryImage->url ?? null;
                            }
                        @endphp
                        <tr>
                            {{-- Thumbnail --}}
                            <td class="w-12 pr-0">
                                @if($thumbUrl)
                                    <img src="{{ $thumbUrl }}" alt="{{ $item->name }}"
                                         class="w-10 h-10 object-cover rounded border border-gray-200 dark:border-zinc-700" />
                                @else
                                    <div class="w-10 h-10 rounded border border-gray-200 dark:border-zinc-700 flex items-center justify-center bg-gray-50 dark:bg-zinc-800">
                                        <x-heroicon-o-photo class="w-5 h-5 text-gray-300 dark:text-zinc-600" />
                                    </div>
                                @endif
                            </td>
                            {{-- Nome + variações + link --}}
                            <td>
                                <div class="flex items-start gap-1">
                                    @if($listingUrl)
                                    <a href="{{ $listingUrl }}" class="font-medium leading-tight hover:text-primary-600 dark:hover:text-primary-400 transition-colors">{{ $item->name }}</a>
                                    @else
                                    <div class="font-medium leading-tight">{{ $item->name }}</div>
                                    @endif
                                    @if($listingUrl)
                                    <a href="{{ $listingUrl }}" class="flex-shrink-0 text-gray-400 hover:text-primary-500 transition-colors mt-0.5" title="Ver anúncio no sistema">
                                        <x-heroicon-o-arrow-top-right-on-square class="w-3.5 h-3.5" />
                                    </a>
                                    @endif
                                </div>
                                @if(!empty($variationAttrs))
                                <div class="text-xs text-gray-400 dark:text-zinc-500 mt-0.5">
                                    @foreach($variationAttrs as $attr)
                                        <span>{{ $attr['name'] ?? '' }}: <strong>{{ $attr['value_name'] ?? '' }}</strong></span>
                                        @if(!$loop->last) · @endif
                                    @endforeach
                                </div>
                                @endif
                                @if($mlItemId)
                                    <p class="text-[10px] font-mono text-gray-300 dark:text-zinc-600 mt-0.5">{{ $mlItemId }}</p>
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
                <div class="space-y-2.5 text-sm">
                    {{-- Nome --}}
                    <div class="flex items-center gap-2">
                        <div class="flex-shrink-0 w-8 h-8 rounded-full bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center">
                            <x-heroicon-s-user class="w-4 h-4 text-primary-600 dark:text-primary-400" />
                        </div>
                        <div>
                            <p class="font-semibold text-gray-900 dark:text-white leading-tight">{{ $order->customer_name }}</p>
                            @if($order->customer_document)
                                <p class="text-xs font-mono text-gray-400 dark:text-zinc-500">{{ $order->customer_document }}</p>
                            @endif
                        </div>
                    </div>
                    @if($order->customer_email)
                    <div class="flex items-center gap-2 text-gray-600 dark:text-zinc-400">
                        <x-heroicon-o-envelope class="w-4 h-4 flex-shrink-0 text-gray-400" />
                        <a href="mailto:{{ $order->customer_email }}" class="hover:text-primary-500 transition-colors break-all">{{ $order->customer_email }}</a>
                    </div>
                    @endif
                    @if($order->customer_phone)
                    <div class="flex items-center gap-2 text-gray-600 dark:text-zinc-400">
                        <x-heroicon-o-phone class="w-4 h-4 flex-shrink-0 text-gray-400" />
                        <a href="tel:{{ $order->customer_phone }}" class="hover:text-primary-500 transition-colors">{{ $order->customer_phone }}</a>
                    </div>
                    @endif
                    @if($order->shipping_address)
                    @php $addr = $order->shipping_address; @endphp
                    <div class="flex items-start gap-2 text-gray-600 dark:text-zinc-400">
                        <x-heroicon-o-map-pin class="w-4 h-4 flex-shrink-0 text-gray-400 mt-0.5" />
                        <div class="leading-snug">
                            <p>{{ $addr['street'] ?? '' }}</p>
                            @if(!empty($addr['complement'])) <p class="text-gray-400 dark:text-zinc-500">{{ $addr['complement'] }}</p> @endif
                            @if(!empty($addr['neighborhood'])) <p class="text-gray-400 dark:text-zinc-500">{{ $addr['neighborhood'] }}</p> @endif
                            <p>{{ $addr['city'] ?? '' }}{{ isset($addr['state']) ? ' - ' . $addr['state'] : '' }}</p>
                            @if(!empty($addr['zip'])) <p class="text-xs text-gray-400 dark:text-zinc-500">CEP {{ $addr['zip'] }}</p> @endif
                        </div>
                    </div>
                    @endif
                    @if($order->customer)
                    <div class="pt-1 border-t border-gray-100 dark:border-zinc-800">
                        <a href="{{ route('customers.show', $order->customer) }}" class="text-xs text-primary-500 hover:text-primary-400 flex items-center gap-1 transition-colors">
                            <x-heroicon-o-arrow-top-right-on-square class="w-3 h-3" />
                            Ver perfil completo do cliente
                        </a>
                    </div>
                    @endif
                </div>
            </x-ui.card>

            {{-- Shipping --}}
            @if($order->shipping_address || $order->tracking_code || !empty($meta['ml_shipping_id']))
            @php
                $hasBeenShipped  = in_array($order->status->value, ['shipped', 'delivered', 'returned']);
                $isPendingShip   = in_array($order->status->value, ['pending', 'confirmed', 'in_production', 'produced', 'ready_to_ship']);
                $dispatchDeadline = $meta['ml_shipping_deadline'] ?? null;
                $deadlineDate     = $dispatchDeadline ? \Carbon\Carbon::parse($dispatchDeadline) : null;
                $isOverdue        = $deadlineDate && $deadlineDate->isPast();
            @endphp
            <x-ui.card title="Envio">
                <div class="space-y-3">

                    {{-- Dispatch status badge --}}
                    @if($isPendingShip && !$order->shipped_at)
                    <div class="flex items-center gap-2 px-3 py-2 rounded-lg {{ $isOverdue ? 'bg-red-50 dark:bg-red-900/20' : 'bg-amber-50 dark:bg-amber-900/20' }}">
                        <x-heroicon-o-clock class="w-4 h-4 flex-shrink-0 {{ $isOverdue ? 'text-red-500' : 'text-amber-500' }}" />
                        <div>
                            <p class="text-sm font-medium {{ $isOverdue ? 'text-red-700 dark:text-red-300' : 'text-amber-700 dark:text-amber-300' }}">
                                {{ $isOverdue ? 'Envio atrasado' : 'Aguardando envio' }}
                            </p>
                            @if($deadlineDate)
                            <p class="text-xs {{ $isOverdue ? 'text-red-500 dark:text-red-400' : 'text-amber-600 dark:text-amber-400' }}">
                                Despachar até: {{ $deadlineDate->format('d/m/Y') }}
                            </p>
                            @endif
                        </div>
                    </div>
                    @endif

                    {{-- Shipping method & mode --}}
                    @if($order->shipping_method || !empty($meta['ml_shipping_mode']))
                    <div>
                        <span class="text-xs text-gray-500 dark:text-zinc-400">Método</span>
                        <p class="text-sm mt-1">
                            {{ $order->shipping_method ?? '' }}
                            @if(!empty($meta['ml_shipping_mode']))
                            <span class="text-xs text-gray-400 dark:text-zinc-500">({{ $meta['ml_shipping_mode'] }})</span>
                            @endif
                        </p>
                    </div>
                    @endif

                    {{-- Tracking — only show when there's a real tracking code --}}
                    @if($order->tracking_code)
                    <div>
                        <span class="text-xs text-gray-500 dark:text-zinc-400">Código de Rastreio</span>
                        <div class="flex items-center gap-2 mt-1">
                            <p class="text-sm font-mono">{{ $order->tracking_code }}</p>
                            @if($trackingUrl)
                            <a href="{{ $trackingUrl }}" target="_blank"
                               class="inline-flex items-center gap-1 text-xs text-primary-500 hover:text-primary-400 transition-colors" title="Rastrear envio">
                                <x-heroicon-o-arrow-top-right-on-square class="w-3.5 h-3.5" />
                                @if($isFromML) ML @else Correios @endif
                            </a>
                            @endif
                        </div>
                    </div>
                    @elseif($mlShippingId && $hasBeenShipped)
                    {{-- Fallback: ML shipping ID only if order was actually shipped but has no tracking code --}}
                    <div>
                        <span class="text-xs text-gray-500 dark:text-zinc-400">ID Envio ML</span>
                        <div class="flex items-center gap-2 mt-1">
                            <p class="text-sm font-mono text-gray-400 dark:text-zinc-500">{{ $mlShippingId }}</p>
                            @if($trackingUrl)
                            <a href="{{ $trackingUrl }}" target="_blank"
                               class="inline-flex items-center gap-1 text-xs text-primary-500 hover:text-primary-400 transition-colors" title="Rastrear envio">
                                <x-heroicon-o-arrow-top-right-on-square class="w-3.5 h-3.5" />
                                ML
                            </a>
                            @endif
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
                        <span class="text-xs text-gray-500 dark:text-zinc-400">Endereço de Entrega</span>
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
