<x-app-layout>
    <x-slot name="header">Pedido {{ $order->order_number }}</x-slot>
    <x-slot name="actions">
        {{-- Ações de expedição --}}
        @if($order->pipeline_status->value !== 'shipped')
        <a href="{{ route('orders.pack', $order) }}" class="btn-secondary">
            <x-heroicon-o-qr-code class="w-4 h-4" />
            Conferir
        </a>
        @endif

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
            <x-ui.card :padding="false">
                <x-slot name="title">
                    <div class="flex items-center justify-between">
                        <span>Itens do Pedido</span>
                        @if($order->pipeline_status === \App\Enums\PipelineStatus::PartiallyShipped)
                            <x-ui.badge color="warning">Envio Parcial</x-ui.badge>
                        @endif
                    </div>
                </x-slot>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th colspan="2">Produto</th>
                            <th>SKU</th>
                            <th class="text-center">Qtd</th>
                            <th class="text-center">Enviado</th>
                            <th class="text-center">Pendente</th>
                            <th class="text-right">Preco Unit.</th>
                            <th class="text-right">Total</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($order->items as $item)
                        @php
                            $itemMeta       = $item->meta ?? [];
                            $variationAttrs = $itemMeta['ml_variation_attrs'] ?? [];
                            $mlItemId      = $itemMeta['ml_item_id'] ?? null;
                            $listingRecord = $mlItemId ? ($mlListings[$mlItemId] ?? null) : null;
                            $listingUrl = $listingRecord
                                ? route('listings.show', $listingRecord['id'])
                                : null;
                            $thumbUrl = null;
                            if ($listingRecord) {
                                $listingMeta = $listingRecord['meta'] ?? [];
                                $thumbUrl = $listingMeta['thumbnail'] ?? ($listingMeta['live']['thumbnail'] ?? null);
                            }
                            if (!$thumbUrl && $item->product?->primaryImage) {
                                $thumbUrl = $item->product->primaryImage->url ?? null;
                            }
                            if (!$thumbUrl && $item->has_artwork) {
                                $thumbUrl = $item->artwork_url;
                            }
                            $pendingQty = $item->pending_quantity;
                        @endphp
                        <tr class="{{ $item->is_fully_shipped ? 'opacity-60' : '' }}">
                            {{-- Thumbnail / Artwork --}}
                            <td class="w-12 pr-0">
                                @if($item->has_artwork)
                                    <div class="relative">
                                        <img src="{{ $item->artwork_url }}" alt="Arte"
                                             class="w-10 h-10 object-cover rounded border-2 border-purple-400" />
                                        <span class="absolute -top-1 -right-1 w-3 h-3 bg-purple-500 rounded-full" title="Arte personalizada"></span>
                                    </div>
                                @elseif($thumbUrl)
                                    <img src="{{ $thumbUrl }}" alt="{{ $item->name }}"
                                         class="w-10 h-10 object-cover rounded border border-gray-200 dark:border-zinc-700" />
                                @else
                                    <div class="w-10 h-10 rounded border border-gray-200 dark:border-zinc-700 flex items-center justify-center bg-gray-50 dark:bg-zinc-800">
                                        <x-heroicon-o-photo class="w-5 h-5 text-gray-300 dark:text-zinc-600" />
                                    </div>
                                @endif
                            </td>
                            {{-- Nome --}}
                            <td>
                                <div class="flex items-start gap-1">
                                    @if($listingUrl)
                                    <a href="{{ $listingUrl }}" class="font-medium leading-tight hover:text-primary-600 dark:hover:text-primary-400 transition-colors">{{ $item->name }}</a>
                                    <a href="{{ $listingUrl }}" class="flex-shrink-0 text-gray-400 hover:text-primary-500 mt-0.5">
                                        <x-heroicon-o-arrow-top-right-on-square class="w-3.5 h-3.5" />
                                    </a>
                                    @else
                                    <div class="font-medium leading-tight">{{ $item->name }}</div>
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
                            {{-- Enviado --}}
                            <td class="text-center">
                                @if($item->shipped_quantity > 0)
                                    <span class="font-semibold text-green-600 dark:text-green-400">{{ $item->shipped_quantity }}</span>
                                @else
                                    <span class="text-gray-400">0</span>
                                @endif
                            </td>
                            {{-- Pendente --}}
                            <td class="text-center">
                                @if($item->cancelled_quantity > 0 && $pendingQty === 0)
                                    <span class="text-xs text-red-500">cancelado</span>
                                @elseif($pendingQty > 0)
                                    <span class="font-semibold text-amber-600 dark:text-amber-400">{{ $pendingQty }}</span>
                                @else
                                    <x-heroicon-s-check-circle class="w-4 h-4 text-green-500 mx-auto" />
                                @endif
                            </td>
                            <td class="text-right">R$ {{ number_format($item->unit_price, 2, ',', '.') }}</td>
                            <td class="text-right font-medium">R$ {{ number_format($item->total, 2, ',', '.') }}</td>
                            {{-- Cancelar restante --}}
                            <td>
                                @if($pendingQty > 0 && $item->shipped_quantity === 0)
                                <form method="POST" action="{{ route('orders.cancel-remaining', [$order, $item]) }}"
                                      onsubmit="return confirm('Cancelar {{ $pendingQty }} unidade(s) restante(s) de {{ $item->name }}?')">
                                    @csrf
                                    <button type="submit" class="text-xs text-red-500 hover:text-red-700 dark:hover:text-red-400 whitespace-nowrap">
                                        Cancelar
                                    </button>
                                </form>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-ui.card>

            {{-- Produção por Item (só aparece se algum item requer produção ou tem arte) --}}
            @if($order->items->contains(fn ($i) => $i->product?->requires_production || $i->has_artwork || $i->production_status->value !== 'not_required'))
            <x-ui.card title="Produção por Item">
                <div class="space-y-4">
                    @foreach($order->items as $item)
                    @php
                        $needsProd  = $item->product?->requires_production || $item->production_status->value !== 'not_required';
                        $hasArtwork = $item->has_artwork;
                    @endphp
                    @if($needsProd || $hasArtwork)
                    <div class="flex items-start gap-4 pb-4 border-b border-gray-100 dark:border-zinc-800 last:border-0 last:pb-0"
                         x-data="{ editArtwork: false }">

                        {{-- Arte --}}
                        <div class="flex-shrink-0">
                            @if($hasArtwork)
                            <a href="{{ $item->artwork_url }}" target="_blank" class="block relative group">
                                <img src="{{ $item->artwork_url }}" alt="Arte"
                                     class="w-16 h-16 object-cover rounded-lg border-2
                                            {{ $item->artwork_approved ? 'border-green-400' : 'border-purple-400' }}" />
                                @if($item->artwork_approved)
                                <div class="absolute -top-1 -right-1 w-4 h-4 bg-green-500 rounded-full flex items-center justify-center">
                                    <x-heroicon-s-check class="w-2.5 h-2.5 text-white" />
                                </div>
                                @endif
                            </a>
                            @else
                            <div class="w-16 h-16 rounded-lg border-2 border-dashed border-gray-300 dark:border-zinc-600
                                        flex items-center justify-center cursor-pointer hover:border-purple-400 transition-colors"
                                 @click="editArtwork = true">
                                <x-heroicon-o-photo class="w-6 h-6 text-gray-400" />
                            </div>
                            @endif
                        </div>

                        {{-- Info --}}
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-sm leading-tight">{{ $item->name }}</p>
                            <div class="flex items-center gap-2 mt-1">
                                <x-ui.badge :color="$item->production_status->color()" class="text-xs">
                                    {{ $item->production_status->label() }}
                                </x-ui.badge>
                                @if($hasArtwork && !$item->artwork_approved)
                                <x-ui.badge color="warning" class="text-xs">Arte pendente</x-ui.badge>
                                @endif
                            </div>
                            @if($item->production_notes)
                            <p class="text-xs text-gray-400 dark:text-zinc-500 mt-1 italic">{{ $item->production_notes }}</p>
                            @endif
                        </div>

                        {{-- Ações rápidas --}}
                        <div class="flex-shrink-0 flex flex-col gap-1.5">
                            <button @click="editArtwork = !editArtwork" class="btn-secondary btn-xs">
                                <x-heroicon-o-pencil class="w-3 h-3" />
                                Arte
                            </button>
                            @if($hasArtwork)
                            <form method="POST" action="{{ route('orders.items.artwork', [$order, $item]) }}">
                                @csrf @method('PATCH')
                                <input type="hidden" name="artwork_approved" value="{{ $item->artwork_approved ? '0' : '1' }}">
                                <button type="submit" class="btn-xs w-full {{ $item->artwork_approved ? 'btn-secondary text-red-600' : 'bg-green-600 text-white border-green-600' }}">
                                    {{ $item->artwork_approved ? 'Desaprovar' : '✓ Aprovar' }}
                                </button>
                            </form>
                            @endif
                        </div>
                    </div>

                    {{-- Formulário inline de arte (colapsável) --}}
                    <div x-show="editArtwork" x-cloak @keydown.escape="editArtwork = false"
                         class="ml-20 -mt-2 pb-4">
                        <form method="POST" action="{{ route('orders.items.artwork', [$order, $item]) }}"
                              class="flex gap-2 items-end">
                            @csrf @method('PATCH')
                            <div class="flex-1">
                                <label class="form-label text-xs">URL da Arte/Mockup</label>
                                <input type="url" name="artwork_url"
                                       value="{{ old('artwork_url', $item->artwork_url) }}"
                                       placeholder="https://..." class="form-input text-sm">
                            </div>
                            <div class="flex-1">
                                <label class="form-label text-xs">Status de Produção</label>
                                <select name="production_status" class="form-input text-sm">
                                    @foreach(\App\Enums\ProductionStatus::cases() as $ps)
                                    <option value="{{ $ps->value }}"
                                            {{ $item->production_status->value === $ps->value ? 'selected' : '' }}>
                                        {{ $ps->label() }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="form-label text-xs">Obs</label>
                                <input type="text" name="production_notes"
                                       value="{{ old('production_notes', $item->production_notes) }}"
                                       placeholder="Notas internas" class="form-input text-sm">
                            </div>
                            <button type="submit" class="btn-primary btn-sm flex-shrink-0">Salvar</button>
                            <button type="button" @click="editArtwork = false" class="btn-secondary btn-sm flex-shrink-0">✕</button>
                        </form>
                    </div>
                    @endif
                    @endforeach
                </div>
            </x-ui.card>
            @endif

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

            {{-- Pipeline / Expedição --}}
            <x-ui.card title="Expedição">
                <div class="space-y-3">
                    <div>
                        <span class="text-xs text-gray-500 dark:text-zinc-400">Pipeline interno</span>
                        <div class="mt-1">
                            <x-ui.badge :color="$order->pipeline_status->color()">
                                {{ $order->pipeline_status->label() }}
                            </x-ui.badge>
                        </div>
                    </div>

                    {{-- Ações de expedição --}}
                    <div class="flex flex-col gap-2 pt-1">
                        <a href="{{ route('orders.pack', $order) }}" class="btn-secondary btn-sm w-full justify-center">
                            <x-heroicon-o-qr-code class="w-4 h-4" />
                            Conferir Embalagem
                        </a>

                        @if($order->marketplaceAccount?->marketplace_type === \App\Enums\MarketplaceType::MercadoLivre && ($meta['ml_shipping_id'] ?? null))
                        <a href="{{ route('orders.ml-label', $order) }}" target="_blank"
                           class="btn-secondary btn-sm w-full justify-center">
                            <x-heroicon-o-tag class="w-4 h-4" />
                            Etiqueta ML (Correios)
                        </a>
                        @endif
                    </div>

                    {{-- Volumes configurados --}}
                    <div>
                        <span class="text-xs text-gray-500 dark:text-zinc-400">Volumes de caixa</span>
                        <p class="text-sm font-semibold mt-1">{{ $order->expedition_volumes }}</p>
                    </div>
                </div>
            </x-ui.card>

            {{-- NF-e --}}
            <x-ui.card title="Nota Fiscal">
                @if($latestInvoice)
                <div class="space-y-2 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500 dark:text-zinc-400">Status</span>
                        <x-ui.badge :color="$latestInvoice->status->color()">
                            {{ $latestInvoice->status->label() }}
                        </x-ui.badge>
                    </div>
                    @if($latestInvoice->number)
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500 dark:text-zinc-400">NF-e nº</span>
                        <span class="font-mono font-semibold">{{ $latestInvoice->number }}</span>
                    </div>
                    @endif
                    @if($latestInvoice->access_key)
                    <div>
                        <span class="text-xs text-gray-500 dark:text-zinc-400">Chave de Acesso</span>
                        <p class="font-mono text-[10px] break-all text-gray-600 dark:text-zinc-400 mt-0.5">
                            {{ $latestInvoice->formatted_access_key }}
                        </p>
                    </div>
                    @endif
                    <div class="flex gap-2 pt-1">
                        @if($latestInvoice->pdf_url)
                        <a href="{{ $latestInvoice->pdf_url }}" target="_blank" class="btn-secondary btn-xs flex-1 justify-center">
                            <x-heroicon-o-document class="w-3.5 h-3.5" />
                            DANFE
                        </a>
                        @endif
                        @if($latestInvoice->xml_url)
                        <a href="{{ $latestInvoice->xml_url }}" target="_blank" class="btn-secondary btn-xs flex-1 justify-center">
                            <x-heroicon-o-code-bracket class="w-3.5 h-3.5" />
                            XML
                        </a>
                        @endif
                    </div>
                </div>
                @else
                <div class="text-center py-2">
                    <p class="text-sm text-gray-500 dark:text-zinc-400 mb-3">Nenhuma NF-e emitida</p>
                    <button onclick="document.getElementById('nfe-modal').classList.remove('hidden')"
                            class="btn-primary btn-sm w-full justify-center">
                        <x-heroicon-o-document-plus class="w-4 h-4" />
                        Emitir NF-e
                    </button>
                </div>
                @endif
            </x-ui.card>

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

            {{-- Melhor Envios — cotação / etiqueta --}}
            @if($order->marketplaceAccount?->melhor_envios_account_id)
            <x-ui.card title="Frete — Melhor Envios">
                <livewire:orders.shipping-quote :order="$order" />
            </x-ui.card>
            @endif

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

    {{-- ============================================================
         TIMELINE DO PEDIDO
    ============================================================ --}}
    <x-ui.card title="Histórico do Pedido" class="mt-6">
        <x-slot name="headerActions">
            <span class="text-xs text-gray-400 dark:text-zinc-500">{{ $order->timelines->count() }} evento(s)</span>
        </x-slot>
        <x-order-timeline :order="$order" />
    </x-ui.card>

    {{-- Compras vinculadas ao pedido --}}
    @php
        $purchaseRequests = \App\Models\PurchaseRequest::where('order_id', $order->id)->with('supplier', 'items')->get();
    @endphp
    @if($purchaseRequests->isNotEmpty())
    <x-ui.card title="Compras" class="mt-4">
        <div class="space-y-3">
            @foreach($purchaseRequests as $pr)
            <div class="flex items-center justify-between bg-gray-50 dark:bg-zinc-700/50 rounded-lg px-4 py-3">
                <div>
                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $pr->title }}</p>
                    <div class="flex items-center gap-3 mt-1 text-xs text-gray-500 dark:text-zinc-400">
                        <span>{{ $pr->items->count() }} item(ns)</span>
                        <span>{{ $pr->total_cost_formatted }}</span>
                        @if($pr->supplier)
                            <span>{{ $pr->supplier->name }}</span>
                        @endif
                    </div>
                </div>
                <div>
                    @if($pr->status === 'pending')
                        <span class="inline-flex items-center text-xs font-bold px-2 py-0.5 rounded-full bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400">Pendente</span>
                    @elseif($pr->status === 'purchased')
                        <span class="inline-flex items-center text-xs font-bold px-2 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400">Comprado</span>
                    @elseif($pr->status === 'cancelled')
                        <span class="inline-flex items-center text-xs font-bold px-2 py-0.5 rounded-full bg-gray-100 dark:bg-zinc-700 text-gray-500 dark:text-zinc-400">Cancelado</span>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
        <div class="mt-3">
            <a href="{{ route('purchases.index') }}" class="text-xs text-primary-600 dark:text-primary-400 hover:underline">
                Ver todas as compras →
            </a>
        </div>
    </x-ui.card>
    @endif

    {{-- Design Assignment (quando houver) --}}
    @if($order->designAssignment)
    <x-ui.card title="Design — Mockup e Arquivos" class="mt-4">
        @php $da = $order->designAssignment; @endphp
        <div class="flex items-center gap-3 mb-4">
            <x-ui.badge :color="$da->statusColor()">{{ $da->statusLabel() }}</x-ui.badge>
            @if($da->designer)
            <span class="text-sm text-gray-500 dark:text-zinc-400">
                Designer: <strong>{{ $da->designer->name }}</strong>
            </span>
            @endif
            @if($da->completed_at)
            <span class="text-sm text-gray-500 dark:text-zinc-400">
                Concluído em {{ $da->completed_at->format('d/m/Y H:i') }}
            </span>
            @endif
            @if(auth()->user()->isAdmin() || auth()->id() === $da->designer_id)
            <a href="{{ route('designer.edit', $da) }}" class="btn-secondary btn-sm ml-auto">
                <x-heroicon-o-paint-brush class="w-4 h-4" />
                Abrir Editor
            </a>
            @endif
        </div>

        @if($da->mockup_url)
        <div class="mb-4">
            <p class="text-xs font-medium text-gray-500 dark:text-zinc-400 mb-2">Mockup Final</p>
            <a href="{{ $da->mockup_url }}" target="_blank">
                <img src="{{ $da->mockup_url }}" alt="Mockup"
                     class="max-h-48 rounded-xl border-2 border-green-300 dark:border-green-700 object-contain" />
            </a>
        </div>
        @endif

        @if($da->productionFiles->count() > 0)
        <div>
            <p class="text-xs font-medium text-gray-500 dark:text-zinc-400 mb-2">Arquivos de Produção ({{ $da->productionFiles->count() }})</p>
            <div class="space-y-1.5">
                @foreach($da->productionFiles as $file)
                <div class="flex items-center gap-2 p-2 rounded-lg bg-gray-50 dark:bg-zinc-800 text-sm">
                    <x-heroicon-o-document class="w-4 h-4 text-gray-400 flex-shrink-0" />
                    <a href="{{ $file->publicUrl() }}" target="_blank"
                       class="flex-1 truncate text-gray-700 dark:text-zinc-300 hover:underline">
                        {{ $file->file_name }}
                    </a>
                    <span class="text-xs text-gray-400 flex-shrink-0">{{ $file->fileSizeFormatted() }}</span>
                    @if($file->is_ai_generated)
                    <x-ui.badge color="violet" class="text-[10px]">IA</x-ui.badge>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </x-ui.card>
    @endif

    {{-- ============================================================
         MODAL — Emissão de NF-e
    ============================================================ --}}
    <div id="nfe-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50"
         @keydown.escape.window="document.getElementById('nfe-modal').classList.add('hidden')">
        <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-2xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-zinc-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Emitir NF-e</h3>
                <button onclick="document.getElementById('nfe-modal').classList.add('hidden')"
                        class="text-gray-400 hover:text-gray-600 dark:hover:text-zinc-200">
                    <x-heroicon-o-x-mark class="w-5 h-5" />
                </button>
            </div>

            <form action="{{ route('orders.invoice.emit', $order) }}" method="POST" class="p-6 space-y-5">
                @csrf

                {{-- Provider --}}
                @if($order->marketplaceAccount?->webmania_account_id)
                <div class="flex items-center gap-2 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg text-sm text-blue-700 dark:text-blue-300">
                    <x-heroicon-o-information-circle class="w-4 h-4 flex-shrink-0" />
                    Emissão via <strong>Webmaniabr</strong> — conta vinculada: {{ $order->marketplaceAccount->webmaniaAccount?->name ?? 'Webmania' }}
                </div>
                @else
                <div class="flex items-center gap-2 p-3 bg-amber-50 dark:bg-amber-900/20 rounded-lg text-sm text-amber-700 dark:text-amber-300">
                    <x-heroicon-o-exclamation-triangle class="w-4 h-4 flex-shrink-0" />
                    Nenhuma conta Webmaniabr vinculada a esta conta de marketplace.
                    <a href="{{ route('settings.index') }}" class="underline ml-1">Configurar</a>
                </div>
                @endif

                {{-- Campos principais --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Natureza da Operação</label>
                        <input type="text" name="nature_operation" value="Venda" class="form-input">
                    </div>
                    <div>
                        <label class="form-label">Modalidade do Frete</label>
                        <select name="shipping_modality" class="form-input">
                            <option value="9">Sem frete (9)</option>
                            <option value="0">CIF — Remetente paga (0)</option>
                            <option value="1">FOB — Destinatário paga (1)</option>
                        </select>
                    </div>
                </div>

                {{-- Seção colapsável: Volume e Peso --}}
                <details class="border border-gray-200 dark:border-zinc-700 rounded-lg">
                    <summary class="px-4 py-3 cursor-pointer text-sm font-medium text-gray-700 dark:text-zinc-300">
                        Informar Volume e Peso (opcional)
                    </summary>
                    <div class="px-4 pb-4 pt-2 grid grid-cols-3 gap-3">
                        <div>
                            <label class="form-label text-xs">Espécie</label>
                            <input type="text" name="volume_species" placeholder="Caixa" class="form-input">
                        </div>
                        <div>
                            <label class="form-label text-xs">Peso Bruto (kg)</label>
                            <input type="number" step="0.001" name="weight_gross" class="form-input">
                        </div>
                        <div>
                            <label class="form-label text-xs">Peso Líquido (kg)</label>
                            <input type="number" step="0.001" name="weight_net" class="form-input">
                        </div>
                    </div>
                </details>

                {{-- Seção colapsável: Informações Complementares --}}
                <details class="border border-gray-200 dark:border-zinc-700 rounded-lg">
                    <summary class="px-4 py-3 cursor-pointer text-sm font-medium text-gray-700 dark:text-zinc-300">
                        Informações Complementares (opcional)
                    </summary>
                    <div class="px-4 pb-4 pt-2 space-y-3">
                        <div>
                            <label class="form-label text-xs">Info ao Fisco</label>
                            <textarea name="info_fisco" rows="2" class="form-input"></textarea>
                        </div>
                        <div>
                            <label class="form-label text-xs">Info ao Consumidor</label>
                            <textarea name="info_consumer" rows="2" class="form-input"></textarea>
                        </div>
                    </div>
                </details>

                {{-- Toggle homologação --}}
                <div class="flex items-center gap-3">
                    <input type="checkbox" name="homologation" id="nfe-homolog" value="1"
                           class="rounded border-gray-300 dark:border-zinc-600">
                    <label for="nfe-homolog" class="text-sm text-gray-700 dark:text-zinc-300">
                        Emitir em <strong>Homologação</strong> (ambiente de teste)
                    </label>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button"
                            onclick="document.getElementById('nfe-modal').classList.add('hidden')"
                            class="btn-secondary">
                        Cancelar
                    </button>
                    <button type="submit" name="action" value="preview" class="btn-secondary">
                        <x-heroicon-o-eye class="w-4 h-4" />
                        Pré-visualizar DANFE
                    </button>
                    <button type="submit" name="action" value="emit" class="btn-primary">
                        <x-heroicon-o-document-plus class="w-4 h-4" />
                        Emitir NF-e
                    </button>
                </div>
            </form>
        </div>
    </div>

</x-app-layout>
