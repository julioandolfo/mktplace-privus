<div x-data="{ showFilters: false }">

    {{-- ============================================================
         HEADER — Contadores por Tab
    ============================================================ --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-2 mb-4">
        @php
            $tabs = [
                ['key' => 'in_production', 'label' => 'Produção',   'icon' => 'heroicon-o-cog-8-tooth',        'color' => 'text-purple-600 dark:text-purple-400', 'bg' => 'bg-purple-50 dark:bg-purple-900/20'],
                ['key' => 'overdue',       'label' => 'Atrasados',  'icon' => 'heroicon-o-exclamation-circle', 'color' => 'text-red-600 dark:text-red-400',    'bg' => 'bg-red-50 dark:bg-red-900/20'],
                ['key' => 'today',         'label' => 'Hoje',       'icon' => 'heroicon-o-sun',                'color' => 'text-amber-600 dark:text-amber-400', 'bg' => 'bg-amber-50 dark:bg-amber-900/20'],
                ['key' => 'tomorrow',      'label' => 'Amanhã',     'icon' => 'heroicon-o-calendar',           'color' => 'text-blue-600 dark:text-blue-400',   'bg' => 'bg-blue-50 dark:bg-blue-900/20'],
                ['key' => 'this_week',     'label' => 'Esta Semana','icon' => 'heroicon-o-calendar-days',      'color' => 'text-cyan-600 dark:text-cyan-400',   'bg' => 'bg-cyan-50 dark:bg-cyan-900/20'],
                ['key' => 'later',         'label' => 'Depois',     'icon' => 'heroicon-o-clock',              'color' => 'text-gray-600 dark:text-zinc-400',   'bg' => 'bg-gray-50 dark:bg-zinc-800'],
                ['key' => 'partial',       'label' => 'Parciais',   'icon' => 'heroicon-o-queue-list',         'color' => 'text-orange-600 dark:text-orange-400','bg' => 'bg-orange-50 dark:bg-orange-900/20'],
                ['key' => 'shipped',       'label' => 'Enviados',   'icon' => 'heroicon-o-truck',              'color' => 'text-green-600 dark:text-green-400', 'bg' => 'bg-green-50 dark:bg-green-900/20'],
            ];
        @endphp

        @foreach($tabs as $tab)
        <button wire:click="$set('activeTab', '{{ $tab['key'] }}')"
                class="card p-3 text-left transition-all {{ $activeTab === $tab['key'] ? 'ring-2 ring-primary-500 ' . $tab['bg'] : 'hover:' . $tab['bg'] }}">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-gray-500 dark:text-zinc-400">{{ $tab['label'] }}</span>
                @if(($tabCounts[$tab['key']] ?? 0) > 0 && $tab['key'] === 'overdue')
                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-red-500 text-white text-xs font-bold">!</span>
                @endif
            </div>
            <p class="text-xl font-bold mt-1 {{ $tab['color'] }}">{{ $tabCounts[$tab['key']] ?? 0 }}</p>
        </button>
        @endforeach
    </div>

    {{-- ============================================================
         TOOLBAR — Busca, Filtros, Ações em lote
    ============================================================ --}}
    <div class="card p-3 mb-4">
        <div class="flex flex-col sm:flex-row gap-3">
            {{-- Busca --}}
            <div class="flex-1 relative">
                <x-heroicon-o-magnifying-glass class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                <input type="text" wire:model.live.debounce.300ms="search"
                       placeholder="Buscar por número, cliente..."
                       class="form-input pl-10">
            </div>

            {{-- Filtro conta --}}
            <select wire:model.live="filterAccount" class="form-input w-48">
                <option value="">Todas as contas</option>
                @foreach($accounts as $acc)
                    <option value="{{ $acc->id }}">{{ $acc->account_name }}</option>
                @endforeach
            </select>

            {{-- Filtro tipo marketplace --}}
            <select wire:model.live="filterType" class="form-input w-40">
                <option value="">Todos canais</option>
                @foreach($types as $type)
                    <option value="{{ $type->value }}">{{ $type->label() }}</option>
                @endforeach
            </select>
        </div>

        {{-- Ações em lote — visível quando há selecionados --}}
        @if(count($selectedOrders) > 0)
        <div class="flex flex-wrap items-center gap-2 mt-3 pt-3 border-t border-gray-200 dark:border-zinc-700">
            <span class="text-sm font-medium text-gray-600 dark:text-zinc-400">
                {{ count($selectedOrders) }} pedido(s) selecionado(s)
            </span>

            <button wire:click="$set('showBulkPackModal', true)"
                    class="btn-secondary btn-sm">
                <x-heroicon-o-archive-box class="w-4 h-4" />
                Marcar Embalados
            </button>

            <button wire:click="printVolumeLabels"
                    class="btn-secondary btn-sm">
                <x-heroicon-o-printer class="w-4 h-4" />
                Etiquetas Internas
            </button>

            <button wire:click="openRomaneioModal(1)"
                    class="btn-primary btn-sm">
                <x-heroicon-o-clipboard-document-list class="w-4 h-4" />
                Criar Romaneio
            </button>
        </div>
        @else
        <div class="flex items-center gap-2 mt-3 pt-3 border-t border-gray-200 dark:border-zinc-700">
            <button wire:click="openRomaneioModal(2)"
                    class="btn-ghost btn-sm">
                <x-heroicon-o-plus-circle class="w-4 h-4" />
                Novo Romaneio Vazio (bipagem)
            </button>

            <a href="{{ route('romaneios.index') }}" class="btn-ghost btn-sm ml-auto">
                <x-heroicon-o-list-bullet class="w-4 h-4" />
                Ver Romaneios
            </a>
        </div>
        @endif
    </div>

    {{-- ============================================================
         TABELA DE PEDIDOS
    ============================================================ --}}
    @if($orders->isEmpty())
        <x-ui.card>
            <x-ui.empty-state
                title="Nenhum pedido nesta aba"
                description="Os pedidos aparecerão aqui conforme o prazo de despacho.">
                <x-slot name="icon">
                    <x-heroicon-o-truck class="w-8 h-8 text-gray-400 dark:text-zinc-500" />
                </x-slot>
            </x-ui.empty-state>
        </x-ui.card>
    @else
        <x-ui.card :padding="false">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th class="w-8">
                                <input type="checkbox" wire:model.live="selectAll"
                                       class="rounded border-gray-300 dark:border-zinc-600">
                            </th>
                            <th>Pedido</th>
                            <th>Cliente / Destino</th>
                            <th>Itens</th>
                            <th>Prazo / Status</th>
                            <th class="text-center">Volumes</th>
                            <th>Pipeline</th>
                            <th class="text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($orders as $order)
                        @php
                            $deadline       = $order->meta['ml_shipping_deadline'] ?? null;
                            $deadlineCarbon = $deadline ? \Carbon\Carbon::parse($deadline) : null;
                            $isOverdue      = $deadlineCarbon && $deadlineCarbon->isPast() && ! $deadlineCarbon->isToday();
                            $isToday        = $deadlineCarbon?->isToday();
                            $isTomorrow     = $deadlineCarbon?->isTomorrow();
                            $hasArtwork     = $order->items->contains(fn ($i) => $i->has_artwork);
                            $artworkUrl     = $order->items->firstWhere('has_artwork', true)?->artwork_url;
                            $account        = $order->marketplaceAccount;

                            // Pipeline state
                            $pipeline    = $order->pipeline_status;
                            $isPacked    = $pipeline === \App\Enums\PipelineStatus::Packed;
                            $isShipped   = $pipeline === \App\Enums\PipelineStatus::Shipped;
                            $isPartial   = $pipeline === \App\Enums\PipelineStatus::PartiallyShipped;
                            $isPrePack   = in_array($pipeline, [
                                \App\Enums\PipelineStatus::ReadyToShip,
                                \App\Enums\PipelineStatus::Packing,
                            ]);

                            // Marketplace
                            $mktType  = $account?->marketplace_type;
                            $isMl     = $mktType === \App\Enums\MarketplaceType::MercadoLivre;

                            // NF-e
                            $approvedNfe = $order->invoices
                                ->firstWhere('status', \App\Enums\NfeStatus::Approved);
                            $pendingNfe  = $order->invoices->first(
                                fn ($i) => in_array($i->status->value, ['pending', 'processing'])
                            );
                            $hasNfe      = (bool) $approvedNfe;

                            // ML shipping label
                            $mlShippingId = $order->meta['ml_shipping_id'] ?? null;

                            // Can mark shipped (ML needs NFE approved first)
                            $canShip = ($isPacked || $isPartial)
                                && (! $isMl || $hasNfe);
                        @endphp
                        <tr wire:key="exp-{{ $order->id }}"
                            class="{{ $isOverdue ? 'bg-red-50/50 dark:bg-red-900/5' : '' }} {{ in_array((string)$order->id, $selectedOrders) ? 'bg-primary-50/50 dark:bg-primary-900/10' : '' }}">

                            {{-- Checkbox --}}
                            <td>
                                <input type="checkbox" wire:model.live="selectedOrders"
                                       value="{{ $order->id }}"
                                       class="rounded border-gray-300 dark:border-zinc-600">
                            </td>

                            {{-- Pedido + marketplace --}}
                            <td>
                                <div class="flex items-center gap-2">
                                    @if($account)
                                        <div class="w-6 h-6 flex-shrink-0" title="{{ $account->account_name }}">
                                            {!! $account->marketplace_type->logoSvg() !!}
                                        </div>
                                    @endif
                                    <div>
                                        <a href="{{ route('orders.show', $order) }}"
                                           class="font-mono font-semibold text-primary-600 dark:text-primary-400 hover:underline text-sm">
                                            {{ $order->order_number }}
                                        </a>
                                        @if($account)
                                        <p class="text-xs text-gray-400 dark:text-zinc-500">{{ $account->account_name }}</p>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            {{-- Cliente + Destino --}}
                            <td>
                                <p class="font-medium text-gray-900 dark:text-white text-sm">{{ $order->customer_name }}</p>
                                @if($order->shipping_address)
                                    @php $addr = $order->shipping_address; @endphp
                                    <p class="text-xs text-gray-500 dark:text-zinc-400">
                                        {{ $addr['city'] ?? '' }}{{ !empty($addr['state']) ? '/' . $addr['state'] : '' }}
                                        @if(!empty($addr['zipcode'])) — {{ $addr['zipcode'] }} @endif
                                    </p>
                                @endif
                            </td>

                            {{-- Itens + Artwork --}}
                            <td>
                                <div class="flex items-start gap-2">
                                    {{-- Thumbnail artwork se houver --}}
                                    @if($hasArtwork && $artworkUrl)
                                    <div x-data="{ open: false }" class="flex-shrink-0">
                                        <div @mouseenter="open = true" @mouseleave="open = false" class="relative">
                                            <img src="{{ $artworkUrl }}" alt="Arte"
                                                 class="w-10 h-10 rounded object-cover border-2 border-purple-400 cursor-pointer">
                                            <div x-show="open" x-transition
                                                 class="absolute z-50 bottom-full left-0 mb-2 p-1 bg-white dark:bg-zinc-800 rounded-lg shadow-2xl border border-gray-200 dark:border-zinc-600">
                                                <img src="{{ $artworkUrl }}" alt="Arte ampliada"
                                                     class="w-48 h-48 object-contain rounded">
                                            </div>
                                        </div>
                                    </div>
                                    @endif

                                    <div class="min-w-0">
                                        @foreach($order->items->take(2) as $item)
                                        <p class="text-xs text-gray-700 dark:text-zinc-300 truncate max-w-[160px]">
                                            {{ $item->quantity }}× {{ $item->name }}
                                            @if($item->pending_quantity < $item->quantity)
                                                <span class="text-orange-500">({{ $item->pending_quantity }} pend.)</span>
                                            @endif
                                        </p>
                                        @endforeach
                                        @if($order->items->count() > 2)
                                        <p class="text-xs text-gray-400">+{{ $order->items->count() - 2 }} mais</p>
                                        @endif
                                    </div>
                                </div>

                                {{-- Badges especiais --}}
                                <div class="flex flex-wrap gap-1 mt-1">
                                    @if($hasArtwork)
                                    <span class="inline-flex items-center gap-0.5 text-xs px-1.5 py-0.5 rounded-full bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 font-medium">
                                        <x-heroicon-s-paint-brush class="w-3 h-3" /> PERSONALIZADO
                                    </span>
                                    @endif
                                    @if($isPartial)
                                    <span class="inline-flex items-center gap-0.5 text-xs px-1.5 py-0.5 rounded-full bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-300 font-medium">
                                        PARCIAL
                                    </span>
                                    @endif
                                </div>
                            </td>

                            {{-- Prazo / Status de envio --}}
                            <td>
                                @if($deadlineCarbon)
                                    <div class="flex flex-col gap-1">
                                        @if($isOverdue)
                                            <span class="inline-flex items-center gap-1 text-xs font-bold px-2 py-1 rounded-full bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400">
                                                <x-heroicon-s-exclamation-triangle class="w-3 h-3" />
                                                ATRASADO
                                            </span>
                                        @elseif($isToday)
                                            <span class="inline-flex items-center gap-1 text-xs font-bold px-2 py-1 rounded-full bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400">
                                                <x-heroicon-s-clock class="w-3 h-3" />
                                                HOJE
                                            </span>
                                        @elseif($isTomorrow)
                                            <span class="inline-flex items-center gap-1 text-xs font-bold px-2 py-1 rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400">
                                                AMANHÃ
                                            </span>
                                        @endif
                                        <p class="text-xs text-gray-500 dark:text-zinc-400">
                                            Despachar até<br>
                                            <span class="font-semibold text-gray-700 dark:text-zinc-200">
                                                {{ $deadlineCarbon->format('d/m H:i') }}
                                            </span>
                                        </p>
                                    </div>
                                @elseif($order->paid_at)
                                    <p class="text-xs text-gray-500 dark:text-zinc-400">
                                        Pago em<br>
                                        <span class="font-medium">{{ $order->paid_at->format('d/m/Y') }}</span>
                                    </p>
                                @else
                                    <span class="text-gray-400 dark:text-zinc-500 text-xs">—</span>
                                @endif
                            </td>

                            {{-- Volumes (spinbox inline) --}}
                            <td class="text-center">
                                @if($activeTab !== 'shipped')
                                <div class="flex items-center justify-center gap-1"
                                     x-data="{ vol: {{ $orderVolumes[$order->id] ?? 1 }} }">
                                    <button @click="if(vol > 1) { vol--; $wire.setVolume({{ $order->id }}, vol) }"
                                            class="w-6 h-6 rounded bg-gray-100 dark:bg-zinc-700 text-gray-600 dark:text-zinc-300 hover:bg-gray-200 dark:hover:bg-zinc-600 text-sm font-bold flex items-center justify-center">
                                        −
                                    </button>
                                    <span class="w-6 text-center text-sm font-semibold text-gray-900 dark:text-white" x-text="vol"></span>
                                    <button @click="vol++; $wire.setVolume({{ $order->id }}, vol)"
                                            class="w-6 h-6 rounded bg-gray-100 dark:bg-zinc-700 text-gray-600 dark:text-zinc-300 hover:bg-gray-200 dark:hover:bg-zinc-600 text-sm font-bold flex items-center justify-center">
                                        +
                                    </button>
                                </div>
                                @else
                                    <span class="text-sm text-gray-500">{{ $order->expedition_volumes }}</span>
                                @endif
                            </td>

                            {{-- Pipeline status --}}
                            <td>
                                <x-ui.badge :color="$order->pipeline_status->color()">
                                    {{ $order->pipeline_status->label() }}
                                </x-ui.badge>
                            </td>

                            {{-- ═══════════════════════════════════════════════════════
                                 AÇÕES: dots de progresso + 1 CTA principal + menu ⋮
                            ═══════════════════════════════════════════════════════ --}}
                            @php
                                // Calcular etapa ML
                                if ($isMl) {
                                    if ($isPrePack)        $mlStep = 1;
                                    elseif ($pendingNfe)   $mlStep = 3;
                                    elseif (!$hasNfe)      $mlStep = 2;
                                    else                   $mlStep = 4;
                                }
                                // Calcular etapa genérica
                                if (!$isMl) {
                                    if ($isPrePack)    $genStep = 1;
                                    elseif ($isPacked) $genStep = 2;
                                    else               $genStep = 3;
                                }
                            @endphp
                            <td class="text-right pr-3">
                                <div class="flex items-center justify-end gap-2">

                                @if($activeTab === 'in_production')
                                    {{-- Em produção: só visualizar --}}
                                    <span class="text-xs text-gray-400 dark:text-zinc-500 italic">Em produção</span>
                                    <a href="{{ route('orders.show', $order) }}" class="btn-ghost btn-xs" title="Ver pedido">
                                        <x-heroicon-o-eye class="w-4 h-4" />
                                    </a>

                                @elseif($isShipped || $activeTab === 'shipped')
                                    {{-- Enviado --}}
                                    <span class="inline-flex items-center gap-1 text-xs font-medium text-green-600 dark:text-green-400">
                                        <x-heroicon-s-check-circle class="w-3.5 h-3.5" />
                                        Enviado
                                    </span>
                                    <a href="{{ route('orders.show', $order) }}" class="btn-ghost btn-xs" title="Ver pedido">
                                        <x-heroicon-o-eye class="w-4 h-4" />
                                    </a>

                                @elseif($isMl)
                                    {{-- ──── PROGRESSO: dots para ML (4 etapas) ──── --}}
                                    <div class="flex items-center gap-1" title="Embalar → NF-e → Etiqueta ML → Enviado">
                                        @foreach([1,2,3,4] as $s)
                                        <div class="w-2 h-2 rounded-full transition-colors
                                            {{ $mlStep > $s  ? 'bg-green-400 dark:bg-green-500'
                                            : ($mlStep === $s ? 'bg-primary-500 ring-2 ring-primary-300 dark:ring-primary-700'
                                            : 'bg-gray-200 dark:bg-zinc-600') }}">
                                        </div>
                                        @endforeach
                                        <span class="text-[10px] text-gray-400 dark:text-zinc-500 ml-0.5 tabular-nums">{{ $mlStep }}/4</span>
                                    </div>

                                    {{-- ──── CTA PRINCIPAL ──── --}}
                                    @if($mlStep === 1)
                                        <a href="{{ route('orders.pack', $order) }}" class="btn-secondary btn-xs">
                                            <x-heroicon-o-qr-code class="w-3.5 h-3.5" />
                                            Conferir
                                        </a>
                                    @elseif($mlStep === 2)
                                        <form method="POST" action="{{ route('orders.invoice.emit', $order) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="btn-primary btn-xs">
                                                <x-heroicon-o-document-check class="w-3.5 h-3.5" />
                                                Emitir NF-e
                                            </button>
                                        </form>
                                    @elseif($mlStep === 3)
                                        <span class="inline-flex items-center gap-1 text-xs text-amber-600 dark:text-amber-400 font-medium">
                                            <x-heroicon-o-arrow-path class="w-3.5 h-3.5 animate-spin" />
                                            NF-e processando
                                        </span>
                                    @elseif($mlStep === 4)
                                        <button wire:click="markShipped({{ $order->id }})"
                                                wire:confirm="Marcar {{ $order->order_number }} como enviado?"
                                                class="btn-primary btn-xs">
                                            <x-heroicon-o-truck class="w-3.5 h-3.5" />
                                            Enviado
                                        </button>
                                    @endif

                                    {{-- ──── MENU ⋮ SECUNDÁRIO ──── --}}
                                    <div x-data="{ open: false }" class="relative">
                                        <button @click="open = !open" class="btn-ghost btn-xs px-1" title="Mais ações">
                                            <x-heroicon-o-ellipsis-vertical class="w-4 h-4" />
                                        </button>
                                        <div x-show="open" x-cloak @click.outside="open = false"
                                             x-transition:enter="transition ease-out duration-100"
                                             x-transition:enter-start="opacity-0 scale-95"
                                             x-transition:enter-end="opacity-100 scale-100"
                                             class="absolute right-0 top-full mt-1 w-52 bg-white dark:bg-zinc-800 rounded-lg shadow-xl border border-gray-100 dark:border-zinc-700 z-50 py-1 text-left">

                                            @if($mlStep === 1)
                                            <a href="{{ route('romaneios.etiquetas-avulso', ['orders' => $order->id]) }}"
                                               target="_blank"
                                               class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-zinc-300 hover:bg-gray-50 dark:hover:bg-zinc-700">
                                                <x-heroicon-o-document-text class="w-4 h-4 text-gray-400" />
                                                Etiqueta de Volume
                                            </a>
                                            <button wire:click="markPacked({{ $order->id }})"
                                                    wire:confirm="Marcar {{ $order->order_number }} como embalado sem conferência?"
                                                    @click="open = false"
                                                    class="w-full flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-zinc-300 hover:bg-gray-50 dark:hover:bg-zinc-700">
                                                <x-heroicon-o-archive-box class="w-4 h-4 text-gray-400" />
                                                Marcar Embalado
                                            </button>
                                            <div class="my-1 border-t border-gray-100 dark:border-zinc-700"></div>
                                            @endif

                                            @if($mlStep === 4 && $mlShippingId)
                                            <a href="{{ route('orders.ml-label', $order) }}"
                                               target="_blank"
                                               class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-zinc-300 hover:bg-gray-50 dark:hover:bg-zinc-700">
                                                <x-heroicon-o-tag class="w-4 h-4 text-amber-500" />
                                                Etiqueta ML (Correios)
                                            </a>
                                            <div class="my-1 border-t border-gray-100 dark:border-zinc-700"></div>
                                            @endif

                                            <a href="{{ route('orders.show', $order) }}"
                                               class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-zinc-300 hover:bg-gray-50 dark:hover:bg-zinc-700">
                                                <x-heroicon-o-eye class="w-4 h-4 text-gray-400" />
                                                Ver Pedido
                                            </a>
                                        </div>
                                    </div>

                                @else
                                    {{-- ──── PROGRESSO: dots para Genérico (2 etapas) ──── --}}
                                    <div class="flex items-center gap-1" title="Embalar → Enviado">
                                        @foreach([1,2] as $s)
                                        <div class="w-2 h-2 rounded-full transition-colors
                                            {{ $genStep > $s  ? 'bg-green-400 dark:bg-green-500'
                                            : ($genStep === $s ? 'bg-primary-500 ring-2 ring-primary-300 dark:ring-primary-700'
                                            : 'bg-gray-200 dark:bg-zinc-600') }}">
                                        </div>
                                        @endforeach
                                        <span class="text-[10px] text-gray-400 dark:text-zinc-500 ml-0.5 tabular-nums">{{ $genStep }}/2</span>
                                    </div>

                                    {{-- ──── CTA PRINCIPAL ──── --}}
                                    @if($genStep === 1)
                                        <a href="{{ route('orders.pack', $order) }}" class="btn-secondary btn-xs">
                                            <x-heroicon-o-qr-code class="w-3.5 h-3.5" />
                                            Conferir
                                        </a>
                                    @elseif($genStep === 2)
                                        <button wire:click="markShipped({{ $order->id }})"
                                                wire:confirm="Marcar {{ $order->order_number }} como enviado?"
                                                class="btn-primary btn-xs">
                                            <x-heroicon-o-truck class="w-3.5 h-3.5" />
                                            Enviado
                                        </button>
                                    @endif

                                    {{-- ──── MENU ⋮ SECUNDÁRIO ──── --}}
                                    <div x-data="{ open: false }" class="relative">
                                        <button @click="open = !open" class="btn-ghost btn-xs px-1" title="Mais ações">
                                            <x-heroicon-o-ellipsis-vertical class="w-4 h-4" />
                                        </button>
                                        <div x-show="open" x-cloak @click.outside="open = false"
                                             x-transition:enter="transition ease-out duration-100"
                                             x-transition:enter-start="opacity-0 scale-95"
                                             x-transition:enter-end="opacity-100 scale-100"
                                             class="absolute right-0 top-full mt-1 w-52 bg-white dark:bg-zinc-800 rounded-lg shadow-xl border border-gray-100 dark:border-zinc-700 z-50 py-1 text-left">

                                            @if($genStep === 1)
                                            <a href="{{ route('romaneios.etiquetas-avulso', ['orders' => $order->id]) }}"
                                               target="_blank"
                                               class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-zinc-300 hover:bg-gray-50 dark:hover:bg-zinc-700">
                                                <x-heroicon-o-document-text class="w-4 h-4 text-gray-400" />
                                                Etiqueta de Volume
                                            </a>
                                            <button wire:click="markPacked({{ $order->id }})"
                                                    wire:confirm="Marcar {{ $order->order_number }} como embalado?"
                                                    @click="open = false"
                                                    class="w-full flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-zinc-300 hover:bg-gray-50 dark:hover:bg-zinc-700">
                                                <x-heroicon-o-archive-box class="w-4 h-4 text-gray-400" />
                                                Marcar Embalado
                                            </button>
                                            <div class="my-1 border-t border-gray-100 dark:border-zinc-700"></div>
                                            @endif

                                            <a href="{{ route('orders.show', $order) }}"
                                               class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-zinc-300 hover:bg-gray-50 dark:hover:bg-zinc-700">
                                                <x-heroicon-o-eye class="w-4 h-4 text-gray-400" />
                                                Ver Pedido
                                            </a>
                                        </div>
                                    </div>

                                @endif

                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($orders->hasPages())
            <div class="px-4 py-3 border-t border-gray-200 dark:border-zinc-700">
                {{ $orders->links() }}
            </div>
            @endif
        </x-ui.card>
    @endif

    {{-- ============================================================
         MODAL — Criar Romaneio
    ============================================================ --}}
    @if($showRomaneioModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
         @keydown.escape.window="$wire.set('showRomaneioModal', false)">
        <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-xl w-full max-w-md mx-4 p-6"
             @click.outside="$wire.set('showRomaneioModal', false)">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-lg bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center">
                    <x-heroicon-o-clipboard-document-list class="w-5 h-5 text-primary-600 dark:text-primary-400" />
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Criar Romaneio</h3>
                    <p class="text-sm text-gray-500 dark:text-zinc-400">
                        @if($romaneioMode === 1)
                            {{ count($selectedOrders) }} pedido(s) selecionado(s)
                        @else
                            Romaneio vazio — pedidos adicionados por bipagem
                        @endif
                    </p>
                </div>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="form-label">Nome do Romaneio *</label>
                    <input type="text" wire:model="romaneioName" class="form-input"
                           placeholder="Ex: ROM-07/03/2026 Manhã">
                    @error('romaneioName') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                @if($romaneioMode === 1 && count($selectedOrders) > 0)
                <div class="bg-gray-50 dark:bg-zinc-700/50 rounded-lg p-3">
                    <p class="text-sm font-medium text-gray-700 dark:text-zinc-300 mb-1">Resumo:</p>
                    <p class="text-sm text-gray-600 dark:text-zinc-400">
                        {{ count($selectedOrders) }} pedidos · volumes definidos pelo campo na tabela
                    </p>
                </div>
                @endif
            </div>

            <div class="flex justify-end gap-3 mt-6">
                <button wire:click="$set('showRomaneioModal', false)" class="btn-secondary">
                    Cancelar
                </button>
                <button wire:click="createRomaneio" wire:loading.attr="disabled" class="btn-primary">
                    <span wire:loading.remove wire:target="createRomaneio">
                        <x-heroicon-s-plus class="w-4 h-4" />
                        Criar Romaneio
                    </span>
                    <span wire:loading wire:target="createRomaneio">Criando...</span>
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- ============================================================
         MODAL — Confirmar Marcar Embalados em Lote
    ============================================================ --}}
    @if($showBulkPackModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
         @keydown.escape.window="$wire.set('showBulkPackModal', false)">
        <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-xl w-full max-w-sm mx-4 p-6"
             @click.outside="$wire.set('showBulkPackModal', false)">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Marcar como Embalados</h3>
            <p class="text-sm text-gray-600 dark:text-zinc-400 mb-6">
                Marcar <strong>{{ count($selectedOrders) }} pedido(s)</strong> como embalados
                sem conferência individual?
            </p>
            <div class="flex justify-end gap-3">
                <button wire:click="$set('showBulkPackModal', false)" class="btn-secondary">Cancelar</button>
                <button wire:click="markBulkPacked" class="btn-primary">Confirmar</button>
            </div>
        </div>
    </div>
    @endif

</div>
