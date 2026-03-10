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
                       placeholder="Buscar por número, cliente, produto, SKU..."
                       class="form-input pl-10">
            </div>

            {{-- Filtro conta --}}
            <select wire:model.live="filterAccount" class="form-input w-full sm:w-48">
                <option value="">Todas as contas</option>
                @foreach($accounts as $acc)
                    <option value="{{ $acc->id }}">{{ $acc->account_name }}</option>
                @endforeach
            </select>

            {{-- Filtro tipo marketplace --}}
            <select wire:model.live="filterType" class="form-input w-full sm:w-40">
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

            <button wire:click="markBulkShipped"
                    wire:confirm="Marcar {{ count($selectedOrders) }} pedido(s) como enviado(s)?"
                    class="btn-secondary btn-sm">
                <x-heroicon-o-truck class="w-4 h-4" />
                Marcar Enviados
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
            <button wire:click="createEmptyRomaneio"
                    wire:loading.attr="disabled"
                    wire:target="createEmptyRomaneio"
                    class="btn-ghost btn-sm">
                <span wire:loading.remove wire:target="createEmptyRomaneio">
                    <x-heroicon-o-plus-circle class="w-4 h-4" />
                    Novo Romaneio (bipagem)
                </span>
                <span wire:loading wire:target="createEmptyRomaneio" class="text-xs">Criando...</span>
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
                            {{-- Expand toggle --}}
                            <th class="w-6 px-2"></th>
                            <th class="w-8">
                                <input type="checkbox" wire:model.live="selectAll"
                                       class="rounded border-gray-300 dark:border-zinc-600">
                            </th>
                            <th>Pedido</th>
                            <th>Cliente / Destino</th>
                            <th>Prazo / Status</th>
                            <th class="text-center">Volumes</th>
                            <th>Pipeline</th>
                            <th class="text-right">Ações</th>
                        </tr>
                    </thead>

                    {{-- Múltiplos <tbody> — cada pedido é um grupo expansível --}}
                    @foreach($orders as $order)
                    @php
                        $deadline       = $order->meta['ml_shipping_deadline'] ?? null;
                        $deadlineCarbon = $deadline ? \Carbon\Carbon::parse($deadline) : null;
                        $isOverdue      = $deadlineCarbon && $deadlineCarbon->isPast() && ! $deadlineCarbon->isToday();
                        $isToday        = $deadlineCarbon?->isToday();
                        $isTomorrow     = $deadlineCarbon?->isTomorrow();
                        $hasArtwork     = $order->items->contains(fn ($i) => $i->has_artwork);
                        $account        = $order->marketplaceAccount;
                        $itemCount      = $order->items->count();

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

                        // Integrações vinculadas
                        $hasWebmania = (bool) ($account?->webmania_account_id ?? false);
                        $hasME       = (bool) ($account?->melhor_envios_account_id ?? false);

                        // NF-e
                        $approvedNfe = $order->invoices
                            ->firstWhere('status', \App\Enums\NfeStatus::Approved);
                        $pendingNfe  = $order->invoices->first(
                            fn ($i) => in_array($i->status->value, ['pending', 'processing'])
                        );
                        $hasNfe      = (bool) $approvedNfe;

                        // ML shipping label
                        $mlShippingId = $order->meta['ml_shipping_id'] ?? null;

                        // Can mark shipped
                        $canShip = ($isPacked || $isPartial)
                            && (! $isMl || $hasNfe);
                    @endphp

                    <tbody x-data="{ expanded: false }" wire:key="exp-{{ $order->id }}"
                           class="{{ $isOverdue ? 'bg-red-50/50 dark:bg-red-900/5' : '' }} {{ in_array((string)$order->id, $selectedOrders) ? 'bg-primary-50/50 dark:bg-primary-900/10' : '' }}">

                        {{-- ── LINHA PRINCIPAL ── --}}
                        <tr class="cursor-pointer group" @click.self="expanded = !expanded">

                            {{-- Expand chevron --}}
                            <td class="px-2 w-6" @click="expanded = !expanded">
                                <x-heroicon-s-chevron-right
                                    class="w-3.5 h-3.5 text-gray-400 dark:text-zinc-500 transition-transform duration-200 group-hover:text-gray-600"
                                    ::class="expanded ? 'rotate-90' : ''" />
                            </td>

                            {{-- Checkbox --}}
                            <td>
                                <input type="checkbox" wire:model.live="selectedOrders"
                                       value="{{ $order->id }}"
                                       class="rounded border-gray-300 dark:border-zinc-600"
                                       @click.stop>
                            </td>

                            {{-- Pedido + marketplace + hint de itens --}}
                            <td @click="expanded = !expanded">
                                <div class="flex items-center gap-2">
                                    @if($account)
                                        <div class="w-6 h-6 flex-shrink-0" title="{{ $account->account_name }}">
                                            {!! $account->marketplace_type->logoSvg() !!}
                                        </div>
                                    @endif
                                    <div>
                                        <a href="{{ route('orders.show', $order) }}"
                                           class="font-mono font-semibold text-primary-600 dark:text-primary-400 hover:underline text-sm"
                                           @click.stop>
                                            {{ $order->order_number }}
                                        </a>
                                        @if($account)
                                        <p class="text-xs text-gray-400 dark:text-zinc-500">{{ $account->account_name }}</p>
                                        @endif
                                    </div>
                                </div>
                                {{-- Badges de itens visíveis na linha colapsada --}}
                                <div class="flex flex-wrap gap-1 mt-1">
                                    <span class="inline-flex items-center gap-0.5 text-[10px] px-1.5 py-0.5 rounded-full bg-gray-100 dark:bg-zinc-700 text-gray-500 dark:text-zinc-400">
                                        <x-heroicon-o-cube class="w-2.5 h-2.5" />
                                        {{ $itemCount }} {{ Str::plural('item', $itemCount) }}
                                    </span>
                                    @if($hasArtwork)
                                    <span class="inline-flex items-center gap-0.5 text-[10px] px-1.5 py-0.5 rounded-full bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 font-medium">
                                        <x-heroicon-s-paint-brush class="w-2.5 h-2.5" /> PERSONALIZADO
                                    </span>
                                    @endif
                                    @if($isPartial)
                                    <span class="inline-flex items-center gap-0.5 text-[10px] px-1.5 py-0.5 rounded-full bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-300 font-medium">
                                        PARCIAL
                                    </span>
                                    @endif
                                    @if($order->tracking_code)
                                    <span class="inline-flex items-center gap-0.5 text-[10px] px-1.5 py-0.5 rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 font-medium" title="Rastreio: {{ $order->tracking_code }}">
                                        <x-heroicon-o-truck class="w-2.5 h-2.5" /> {{ $order->tracking_code }}
                                    </span>
                                    @endif
                                    @if($approvedNfe)
                                    <span class="inline-flex items-center gap-0.5 text-[10px] px-1.5 py-0.5 rounded-full bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 font-medium">
                                        <x-heroicon-o-document-check class="w-2.5 h-2.5" /> NF-e
                                    </span>
                                    @elseif($pendingNfe)
                                    <span class="inline-flex items-center gap-0.5 text-[10px] px-1.5 py-0.5 rounded-full bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 font-medium">
                                        <x-heroicon-o-arrow-path class="w-2.5 h-2.5 animate-spin" /> NF-e
                                    </span>
                                    @endif
                                    @php $orderMeLabel = $labelsMap[$order->id] ?? null; @endphp
                                    @if($orderMeLabel)
                                    <span class="inline-flex items-center gap-0.5 text-[10px] px-1.5 py-0.5 rounded-full bg-teal-100 dark:bg-teal-900/30 text-teal-700 dark:text-teal-300 font-medium" title="{{ $orderMeLabel->carrier }} - {{ $orderMeLabel->service }}">
                                        <x-heroicon-o-tag class="w-2.5 h-2.5" /> ME
                                    </span>
                                    @endif
                                </div>
                            </td>

                            {{-- Cliente + Destino --}}
                            <td @click="expanded = !expanded">
                                <p class="font-medium text-gray-900 dark:text-white text-sm">{{ $order->customer_name }}</p>
                                @if($order->shipping_address)
                                    @php $addr = $order->shipping_address; @endphp
                                    <p class="text-xs text-gray-500 dark:text-zinc-400">
                                        {{ $addr['city'] ?? '' }}{{ !empty($addr['state']) ? '/' . $addr['state'] : '' }}
                                        @if(!empty($addr['zipcode'])) — {{ $addr['zipcode'] }} @endif
                                    </p>
                                @endif
                            </td>

                            {{-- Prazo / Status de envio --}}
                            <td @click="expanded = !expanded" class="cursor-pointer">
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
                            <td @click="expanded = !expanded" class="cursor-pointer">
                                <x-ui.badge :color="$order->pipeline_status->color()">
                                    {{ $order->pipeline_status->label() }}
                                </x-ui.badge>
                            </td>

                            {{-- ═══════════════════════════════════════════════════════
                                 AÇÕES: dots de progresso + 1 CTA principal + menu ⋮
                            ═══════════════════════════════════════════════════════ --}}
                            @php
                                // Calcular etapa ML (5 etapas: Embalar → NF-e → NF-e Processando → Etiqueta ML → Enviado)
                                if ($isMl) {
                                    if ($isPrePack)        $mlStep = 1;
                                    elseif ($pendingNfe)   $mlStep = 3;
                                    elseif (!$hasNfe)      $mlStep = 2;
                                    elseif ($hasNfe && $mlShippingId) $mlStep = 4;
                                    else                   $mlStep = 5;
                                }

                                // Calcular etapa genérica (dinâmico baseado em integrações)
                                $meLabel = $labelsMap[$order->id] ?? null;

                                if (!$isMl) {
                                    $genTotalSteps = 1; // Embalar
                                    if ($hasWebmania) $genTotalSteps++; // + NF-e
                                    if ($hasME)       $genTotalSteps++; // + Etiqueta ME
                                    $genTotalSteps++; // + Enviado

                                    if ($isPrePack)                          $genStep = 1;
                                    elseif ($hasWebmania && !$hasNfe)         $genStep = 2;
                                    elseif ($hasWebmania && $pendingNfe)      $genStep = 2; // NF-e processando
                                    elseif ($hasME && !$meLabel)             $genStep = $hasWebmania ? 3 : 2;
                                    elseif ($isPacked || $isPartial)          $genStep = $genTotalSteps;
                                    else                                      $genStep = $genTotalSteps;
                                }
                            @endphp
                            <td class="text-right pr-3">
                                <div class="flex items-center justify-end gap-2">

                                @if($activeTab === 'in_production')
                                    {{-- Em produção: visualizar + ações secundárias --}}
                                    <span class="text-xs text-gray-400 dark:text-zinc-500 italic">Em produção</span>
                                    @include('livewire.orders._expedition-menu', [
                                        'order'   => $order,
                                        'mlStep'  => $isMl ? ($mlStep ?? 1) : null,
                                        'genStep' => !$isMl ? ($genStep ?? 1) : null,
                                        'isMl'    => $isMl,
                                        'mlShippingId' => $mlShippingId,
                                        'hasWebmania' => $hasWebmania ?? false,
                                        'hasME'       => $hasME ?? false,
                                    ])

                                @elseif($isShipped || $activeTab === 'shipped')
                                    {{-- Enviado: mostra status + opção de re-abrir para re-envio --}}
                                    <span class="inline-flex items-center gap-1 text-xs font-medium text-green-600 dark:text-green-400">
                                        <x-heroicon-s-check-circle class="w-3.5 h-3.5" />
                                        Enviado
                                    </span>
                                    @include('livewire.orders._expedition-menu', [
                                        'order'   => $order,
                                        'mlStep'  => null,
                                        'genStep' => null,
                                        'isMl'    => $isMl,
                                        'mlShippingId' => $mlShippingId,
                                        'isShipped' => true,
                                        'hasWebmania' => $hasWebmania ?? false,
                                        'hasME'       => $hasME ?? false,
                                    ])

                                @elseif($isMl)
                                    {{-- ──── PROGRESSO: dots para ML (5 etapas) ──── --}}
                                    <div class="flex items-center gap-1" title="Embalar → NF-e → NF-e Processando → Etiqueta ML → Enviado">
                                        @foreach([1,2,3,4,5] as $s)
                                        <div class="w-2 h-2 rounded-full transition-colors
                                            {{ $mlStep > $s  ? 'bg-green-400 dark:bg-green-500'
                                            : ($mlStep === $s ? 'bg-primary-500 ring-2 ring-primary-300 dark:ring-primary-700'
                                            : 'bg-gray-200 dark:bg-zinc-600') }}">
                                        </div>
                                        @endforeach
                                        <span class="text-[10px] text-gray-400 dark:text-zinc-500 ml-0.5 tabular-nums">{{ $mlStep }}/5</span>
                                    </div>

                                    {{-- ──── CTA PRINCIPAL ──── --}}
                                    @if($mlStep === 1)
                                        <button wire:click="openPackingModal({{ $order->id }})" @click.stop class="btn-secondary btn-xs">
                                            <x-heroicon-o-clipboard-document-check class="w-3.5 h-3.5" />
                                            Conferir
                                        </button>
                                    @elseif($mlStep === 2)
                                        <button wire:click="openNfeModal({{ $order->id }})" @click.stop class="btn-primary btn-xs">
                                            <x-heroicon-o-document-check class="w-3.5 h-3.5" />
                                            Emitir NF-e
                                        </button>
                                    @elseif($mlStep === 3)
                                        <span class="inline-flex items-center gap-1 text-xs text-amber-600 dark:text-amber-400 font-medium">
                                            <x-heroicon-o-arrow-path class="w-3.5 h-3.5 animate-spin" />
                                            NF-e processando
                                        </span>
                                    @elseif($mlStep === 4)
                                        @if($mlShippingId)
                                        <a href="{{ route('orders.ml-label', $order) }}" target="_blank" @click.stop class="btn-primary btn-xs">
                                            <x-heroicon-o-tag class="w-3.5 h-3.5" />
                                            Etiqueta ML
                                        </a>
                                        @endif
                                    @elseif($mlStep === 5)
                                        <button wire:click="markShipped({{ $order->id }})" @click.stop
                                                wire:confirm="Marcar {{ $order->order_number }} como enviado?"
                                                class="btn-primary btn-xs">
                                            <x-heroicon-o-truck class="w-3.5 h-3.5" />
                                            Enviado
                                        </button>
                                    @endif

                                    @include('livewire.orders._expedition-menu', [
                                        'order'        => $order,
                                        'mlStep'       => $mlStep,
                                        'genStep'      => null,
                                        'isMl'         => true,
                                        'mlShippingId' => $mlShippingId,
                                        'isShipped'    => false,
                                        'hasWebmania'  => true,
                                        'hasME'        => false,
                                    ])

                                @else
                                    {{-- ──── PROGRESSO: dots para Genérico (dinâmico) ──── --}}
                                    @php
                                        $genLabels = ['Embalar'];
                                        if ($hasWebmania) $genLabels[] = 'NF-e';
                                        if ($hasME)       $genLabels[] = 'Etiqueta';
                                        $genLabels[] = 'Enviado';
                                    @endphp
                                    <div class="flex items-center gap-1" title="{{ implode(' → ', $genLabels) }}">
                                        @foreach(range(1, $genTotalSteps) as $s)
                                        <div class="w-2 h-2 rounded-full transition-colors
                                            {{ $genStep > $s  ? 'bg-green-400 dark:bg-green-500'
                                            : ($genStep === $s ? 'bg-primary-500 ring-2 ring-primary-300 dark:ring-primary-700'
                                            : 'bg-gray-200 dark:bg-zinc-600') }}">
                                        </div>
                                        @endforeach
                                        <span class="text-[10px] text-gray-400 dark:text-zinc-500 ml-0.5 tabular-nums">{{ $genStep }}/{{ $genTotalSteps }}</span>
                                    </div>

                                    {{-- ──── CTA PRINCIPAL ──── --}}
                                    @if($genStep === 1)
                                        <button wire:click="openPackingModal({{ $order->id }})" @click.stop class="btn-secondary btn-xs">
                                            <x-heroicon-o-clipboard-document-check class="w-3.5 h-3.5" />
                                            Conferir
                                        </button>
                                    @elseif($hasWebmania && !$hasNfe && !$pendingNfe)
                                        <button wire:click="openNfeModal({{ $order->id }})" @click.stop class="btn-primary btn-xs">
                                            <x-heroicon-o-document-check class="w-3.5 h-3.5" />
                                            Emitir NF-e
                                        </button>
                                    @elseif($hasWebmania && $pendingNfe)
                                        <span class="inline-flex items-center gap-1 text-xs text-amber-600 dark:text-amber-400 font-medium">
                                            <x-heroicon-o-arrow-path class="w-3.5 h-3.5 animate-spin" />
                                            NF-e processando
                                        </span>
                                    @elseif($hasME && !$meLabel)
                                        <button wire:click="openShippingModal({{ $order->id }})" @click.stop class="btn-primary btn-xs">
                                            <x-heroicon-o-truck class="w-3.5 h-3.5" />
                                            Cotar Frete
                                        </button>
                                    @elseif($genStep >= $genTotalSteps)
                                        <button wire:click="markShipped({{ $order->id }})" @click.stop
                                                wire:confirm="Marcar {{ $order->order_number }} como enviado?"
                                                class="btn-primary btn-xs">
                                            <x-heroicon-o-truck class="w-3.5 h-3.5" />
                                            Enviado
                                        </button>
                                    @endif

                                    @include('livewire.orders._expedition-menu', [
                                        'order'        => $order,
                                        'mlStep'       => null,
                                        'genStep'      => $genStep,
                                        'isMl'         => false,
                                        'mlShippingId' => null,
                                        'isShipped'    => false,
                                        'hasWebmania'  => $hasWebmania ?? false,
                                        'hasME'        => $hasME ?? false,
                                    ])

                                @endif

                                </div>
                            </td>
                        </tr>

                        {{-- ── LINHA EXPANDIDA: itens com imagens e links ── --}}
                        <tr x-show="expanded" x-cloak
                            x-transition:enter="transition-all ease-out duration-150"
                            x-transition:enter-start="opacity-0 -translate-y-1"
                            x-transition:enter-end="opacity-100 translate-y-0"
                            x-transition:leave="transition-all ease-in duration-100"
                            x-transition:leave-start="opacity-100 translate-y-0"
                            x-transition:leave-end="opacity-0 -translate-y-1">
                            <td colspan="8" class="p-0 border-b border-gray-200 dark:border-zinc-700">
                                <div class="bg-gray-50 dark:bg-zinc-800/60 px-6 py-4">

                                    {{-- Cabeçalho do painel --}}
                                    <p class="text-[10px] uppercase tracking-widest font-semibold text-gray-400 dark:text-zinc-500 mb-3">
                                        {{ $itemCount }} {{ Str::plural('item', $itemCount) }} neste pedido
                                    </p>

                                    <div class="flex flex-col gap-2">
                                    @foreach($order->items as $item)
                                    @php
                                        // Anúncio interno vinculado (via ml_item_id)
                                        $mlItemId = $item->meta['ml_item_id'] ?? null;
                                        $listing  = $mlItemId ? ($listingsMap[$mlItemId] ?? null) : null;

                                        // Imagem: artwork → thumbnail do anúncio → imagem primária do produto → primeira imagem
                                        $imgUrl = $item->artwork_url
                                            ?? $listing?->meta['thumbnail']
                                            ?? $item->product?->primaryImage?->url
                                            ?? $item->product?->images->first()?->url;

                                        // Link externo (ML)
                                        $mlPermalink = $item->meta['ml_permalink']
                                            ?? ($mlItemId ? 'https://www.mercadolivre.com.br/p/' . $mlItemId : null);

                                        // Variante
                                        $variantLabel = $item->variant?->name ?? ($item->meta['variation_name'] ?? null);

                                        // Variação ML (atributos)
                                        $mlVariationAttrs = $item->meta['ml_variation_attrs'] ?? [];

                                        // Status de produção
                                        $prodStatus = $item->production_status ?? null;
                                    @endphp
                                    <div class="flex items-start gap-4 rounded-lg bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 p-3 shadow-xs">

                                        {{-- Imagem --}}
                                        <div class="flex-shrink-0">
                                        @if($imgUrl)
                                            <div x-data="{ lightbox: false }" class="relative">
                                                <img src="{{ $imgUrl }}" alt="{{ $item->name }}"
                                                     @click="lightbox = true"
                                                     class="w-16 h-16 rounded-md object-cover border border-gray-200 dark:border-zinc-600 cursor-zoom-in">
                                                {{-- Lightbox inline --}}
                                                <div x-show="lightbox" x-cloak
                                                     @click="lightbox = false"
                                                     @keydown.escape.window="lightbox = false"
                                                     class="fixed inset-0 z-[999] flex items-center justify-center bg-black/70 cursor-zoom-out"
                                                     x-transition>
                                                    <img src="{{ $imgUrl }}" alt="{{ $item->name }}"
                                                         class="max-w-[90vw] max-h-[85vh] rounded-xl shadow-2xl object-contain">
                                                    @if($item->artwork_url)
                                                    <span class="absolute top-4 left-1/2 -translate-x-1/2 bg-purple-600 text-white text-xs px-3 py-1 rounded-full">Arte personalizada</span>
                                                    @endif
                                                </div>
                                                {{-- Indicador de arte --}}
                                                @if($item->artwork_url)
                                                <span class="absolute -top-1 -right-1 w-4 h-4 bg-purple-500 rounded-full border-2 border-white dark:border-zinc-900" title="Arte personalizada"></span>
                                                @endif
                                            </div>
                                        @else
                                            <div class="w-16 h-16 rounded-md bg-gray-100 dark:bg-zinc-700 flex items-center justify-center border border-gray-200 dark:border-zinc-600">
                                                <x-heroicon-o-cube class="w-7 h-7 text-gray-300 dark:text-zinc-500" />
                                            </div>
                                        @endif
                                        </div>

                                        {{-- Informações do item --}}
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-start justify-between gap-4">
                                                <div class="min-w-0">
                                                    <p class="font-semibold text-sm text-gray-900 dark:text-white leading-tight truncate">
                                                        {{ $item->name }}
                                                    </p>
                                                    @if($variantLabel)
                                                    <p class="text-xs text-gray-500 dark:text-zinc-400 mt-0.5">
                                                        Variação: <span class="font-medium text-gray-700 dark:text-zinc-300">{{ $variantLabel }}</span>
                                                    </p>
                                                    @elseif(!empty($mlVariationAttrs))
                                                    <p class="text-xs text-gray-500 dark:text-zinc-400 mt-0.5">
                                                        @foreach($mlVariationAttrs as $attr)
                                                        <span class="font-medium text-gray-700 dark:text-zinc-300">{{ $attr['name'] ?? '' }}: {{ $attr['value_name'] ?? '' }}</span>{{ !$loop->last ? ' · ' : '' }}
                                                        @endforeach
                                                    </p>
                                                    @endif
                                                    @if($item->sku)
                                                    <p class="text-[11px] font-mono text-gray-400 dark:text-zinc-500 mt-0.5">SKU: {{ $item->sku }}</p>
                                                    @endif
                                                </div>

                                                {{-- Quantidade + Preço --}}
                                                <div class="text-right flex-shrink-0">
                                                    <p class="text-sm font-bold text-gray-900 dark:text-white tabular-nums">
                                                        {{ $item->quantity }}×
                                                        <span class="text-gray-700 dark:text-zinc-300">R$ {{ number_format($item->unit_price, 2, ',', '.') }}</span>
                                                    </p>
                                                    <p class="text-xs text-gray-500 dark:text-zinc-400 tabular-nums">
                                                        = R$ {{ number_format($item->total, 2, ',', '.') }}
                                                    </p>
                                                    @if($item->shipped_quantity > 0)
                                                    <p class="text-[10px] text-green-600 dark:text-green-400 mt-0.5">
                                                        {{ $item->shipped_quantity }} enviado(s)
                                                    </p>
                                                    @endif
                                                    @if($item->pending_quantity < $item->quantity && $item->pending_quantity > 0)
                                                    <p class="text-[10px] text-orange-500 mt-0.5">
                                                        {{ $item->pending_quantity }} pendente(s)
                                                    </p>
                                                    @endif
                                                </div>
                                            </div>

                                            {{-- Badges + Links --}}
                                            <div class="flex flex-wrap items-center gap-2 mt-2">
                                                {{-- Status de produção --}}
                                                @if($prodStatus)
                                                <span class="inline-flex items-center gap-1 text-[10px] px-2 py-0.5 rounded-full font-medium
                                                    {{ $prodStatus->value === 'completed' ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300'
                                                    : ($prodStatus->value === 'in_progress' ? 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300'
                                                    : 'bg-gray-100 dark:bg-zinc-700 text-gray-500 dark:text-zinc-400') }}">
                                                    Produção: {{ $prodStatus->label() }}
                                                </span>
                                                @endif

                                                @if($item->artwork_url)
                                                <span class="inline-flex items-center gap-1 text-[10px] px-2 py-0.5 rounded-full bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 font-medium">
                                                    <x-heroicon-s-paint-brush class="w-2.5 h-2.5" /> Arte personalizada
                                                </span>
                                                @endif

                                                {{-- Links --}}
                                                {{-- Anúncio interno (no sistema) --}}
                                                @if($listing)
                                                <a href="{{ route('listings.show', $listing->id) }}"
                                                   target="_blank"
                                                   @click.stop
                                                   class="inline-flex items-center gap-1 text-[10px] px-2 py-0.5 rounded-full bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-100 dark:hover:bg-indigo-900/40 font-medium transition-colors">
                                                    <x-heroicon-o-megaphone class="w-2.5 h-2.5" />
                                                    Anúncio (sistema)
                                                </a>
                                                @elseif($item->product_id)
                                                <a href="{{ route('products.edit', $item->product_id) }}"
                                                   target="_blank"
                                                   @click.stop
                                                   class="inline-flex items-center gap-1 text-[10px] px-2 py-0.5 rounded-full bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400 hover:bg-primary-100 dark:hover:bg-primary-900/40 font-medium transition-colors">
                                                    <x-heroicon-o-arrow-top-right-on-square class="w-2.5 h-2.5" />
                                                    Ver produto (interno)
                                                </a>
                                                @endif

                                                @if($mlPermalink)
                                                <a href="{{ $mlPermalink }}"
                                                   target="_blank"
                                                   rel="noopener noreferrer"
                                                   @click.stop
                                                   class="inline-flex items-center gap-1 text-[10px] px-2 py-0.5 rounded-full bg-yellow-50 dark:bg-yellow-900/20 text-yellow-700 dark:text-yellow-400 hover:bg-yellow-100 dark:hover:bg-yellow-900/40 font-medium transition-colors">
                                                    <x-heroicon-o-arrow-top-right-on-square class="w-2.5 h-2.5" />
                                                    Ver anúncio (ML)
                                                </a>
                                                @endif

                                                @if(!empty($item->meta['shopee_item_id']))
                                                <a href="https://shopee.com.br/product/{{ $item->meta['shopee_shop_id'] ?? 0 }}/{{ $item->meta['shopee_item_id'] }}"
                                                   target="_blank"
                                                   rel="noopener noreferrer"
                                                   @click.stop
                                                   class="inline-flex items-center gap-1 text-[10px] px-2 py-0.5 rounded-full bg-orange-50 dark:bg-orange-900/20 text-orange-700 dark:text-orange-400 hover:bg-orange-100 dark:hover:bg-orange-900/40 font-medium transition-colors">
                                                    <x-heroicon-o-arrow-top-right-on-square class="w-2.5 h-2.5" />
                                                    Ver anúncio (Shopee)
                                                </a>
                                                @endif
                                            </div>
                                        </div>

                                    </div>
                                    @endforeach
                                    </div>

                                    {{-- ── Histórico de Conferência ── --}}
                                    @if(isset($packingHistoryMap[$order->id]))
                                    @php $lastConf = $packingHistoryMap[$order->id]; @endphp
                                    <div class="mt-4 rounded-xl border border-gray-200 dark:border-zinc-700 overflow-hidden">
                                        <div class="flex items-center justify-between px-4 py-2.5 bg-gray-100 dark:bg-zinc-800">
                                            <div class="flex items-center gap-2">
                                                <x-heroicon-o-clipboard-document-check class="w-4 h-4 text-primary-500" />
                                                <span class="text-xs font-semibold text-gray-700 dark:text-zinc-300 uppercase tracking-wide">
                                                    Última Conferência
                                                </span>
                                                @if(($lastConf->data['status'] ?? '') === 'complete')
                                                <span class="inline-flex items-center gap-1 text-[10px] px-1.5 py-0.5 rounded-full bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300 font-semibold">
                                                    <x-heroicon-s-check class="w-2.5 h-2.5" /> Completo
                                                </span>
                                                @elseif(($lastConf->data['status'] ?? '') === 'partial')
                                                <span class="inline-flex items-center gap-1 text-[10px] px-1.5 py-0.5 rounded-full bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300 font-semibold">
                                                    Parcial
                                                </span>
                                                @endif
                                            </div>
                                            <div class="flex items-center gap-3">
                                                <span class="text-[11px] text-gray-400 dark:text-zinc-500">
                                                    {{ $lastConf->happened_at->format('d/m/Y H:i') }}
                                                    @if($lastConf->performer) · {{ $lastConf->performer->name }} @endif
                                                </span>
                                                <button wire:click="openPackingModal({{ $order->id }})"
                                                        class="text-[11px] text-primary-600 dark:text-primary-400 hover:underline font-medium">
                                                    Reconferir
                                                </button>
                                            </div>
                                        </div>
                                        <div class="px-4 py-3 bg-white dark:bg-zinc-900">
                                            {{-- Itens da conferência --}}
                                            <div class="flex flex-wrap gap-x-6 gap-y-1">
                                                @foreach($lastConf->data['items'] ?? [] as $ci)
                                                @php
                                                    $ciOk = ($ci['difference'] ?? 0) <= 0;
                                                @endphp
                                                <div class="flex items-center gap-1.5 text-xs">
                                                    @if($ciOk)
                                                    <x-heroicon-s-check-circle class="w-3.5 h-3.5 text-green-500 flex-shrink-0" />
                                                    @else
                                                    <x-heroicon-s-exclamation-circle class="w-3.5 h-3.5 text-amber-500 flex-shrink-0" />
                                                    @endif
                                                    <span class="text-gray-700 dark:text-zinc-300 truncate max-w-[200px]">{{ $ci['name'] ?? '—' }}</span>
                                                    <span class="font-bold tabular-nums {{ $ciOk ? 'text-green-600 dark:text-green-400' : 'text-amber-600 dark:text-amber-400' }}">
                                                        {{ $ci['qty_confirmed'] }}/{{ $ci['qty_ordered'] }}
                                                    </span>
                                                    @if(!$ciOk)
                                                    <span class="text-red-500 font-semibold">(-{{ $ci['difference'] }})</span>
                                                    @endif
                                                </div>
                                                @endforeach
                                            </div>
                                            {{-- Observações da conferência --}}
                                            @if(!empty($lastConf->description))
                                            <p class="mt-2 text-xs text-gray-500 dark:text-zinc-400 italic">
                                                "{{ $lastConf->description }}"
                                            </p>
                                            @endif
                                        </div>
                                    </div>
                                    @else
                                    {{-- Sem conferência prévia --}}
                                    @if(!$isShipped)
                                    <div class="mt-4 flex items-center gap-2 text-xs text-gray-400 dark:text-zinc-500">
                                        <x-heroicon-o-clipboard-document class="w-4 h-4" />
                                        <span>Nenhuma conferência realizada ainda.</span>
                                        <button wire:click="openPackingModal({{ $order->id }})"
                                                class="text-primary-600 dark:text-primary-400 hover:underline font-medium">
                                            Conferir agora
                                        </button>
                                    </div>
                                    @endif
                                    @endif

                                    {{-- ── Etiqueta Melhor Envios ── --}}
                                    @php $expandedMeLabel = $labelsMap[$order->id] ?? null; @endphp
                                    @if($expandedMeLabel)
                                    <div class="mt-4 rounded-xl border border-teal-200 dark:border-teal-800 overflow-hidden">
                                        <div class="flex items-center justify-between px-4 py-2.5 bg-teal-50 dark:bg-teal-900/20">
                                            <div class="flex items-center gap-2">
                                                <x-heroicon-o-tag class="w-4 h-4 text-teal-500" />
                                                <span class="text-xs font-semibold text-teal-700 dark:text-teal-300 uppercase tracking-wide">
                                                    Etiqueta Melhor Envios
                                                </span>
                                                <span class="inline-flex items-center text-[10px] px-1.5 py-0.5 rounded-full bg-teal-100 dark:bg-teal-900/40 text-teal-700 dark:text-teal-300 font-semibold">
                                                    {{ ucfirst($expandedMeLabel->status) }}
                                                </span>
                                            </div>
                                            <div class="flex items-center gap-3 text-xs">
                                                <span class="text-gray-500 dark:text-zinc-400">
                                                    {{ $expandedMeLabel->carrier }} — {{ $expandedMeLabel->service }}
                                                    · R$ {{ number_format($expandedMeLabel->cost, 2, ',', '.') }}
                                                </span>
                                                @if($expandedMeLabel->tracking_code)
                                                <span class="font-mono font-semibold text-teal-700 dark:text-teal-300">
                                                    {{ $expandedMeLabel->tracking_code }}
                                                </span>
                                                @endif
                                                @if($expandedMeLabel->label_url)
                                                <a href="{{ $expandedMeLabel->label_url }}" target="_blank"
                                                   class="text-primary-600 dark:text-primary-400 hover:underline font-medium">
                                                    Imprimir
                                                </a>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    @endif

                                </div>
                            </td>
                        </tr>

                    </tbody>
                    @endforeach
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
         MODAL — Conferência de Embalagem
    ============================================================ --}}
    @if($showPackingModal && !empty($packingItems))
    @php
        $packTotalOrdered   = array_sum(array_column($packingItems, 'quantity'));
        $packTotalConfirmed = array_sum(array_map(fn ($i) => max(0, (int) ($packingChecks[(string) $i['id']] ?? 0)), $packingItems));
        $packAllOk          = $packTotalConfirmed >= $packTotalOrdered;
        $packOrderObj       = \App\Models\Order::select('id','order_number','customer_name')->find($packingOrderId);
    @endphp
    <div class="fixed inset-0 z-[60] flex items-center justify-center bg-black/60 backdrop-blur-sm"
         @keydown.escape.window="$wire.closePackingModal()">
        <div class="bg-white dark:bg-zinc-900 rounded-2xl shadow-2xl w-full max-w-2xl mx-4 flex flex-col"
             style="max-height: 92vh"
             @click.outside="$wire.closePackingModal()">

            {{-- Header --}}
            <div class="flex items-start justify-between px-6 py-4 border-b border-gray-200 dark:border-zinc-700 flex-shrink-0">
                <div>
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <x-heroicon-o-clipboard-document-check class="w-5 h-5 text-primary-500" />
                        Conferência de Embalagem
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-zinc-400 mt-0.5">
                        <span class="font-mono font-semibold text-gray-700 dark:text-zinc-300">{{ $packOrderObj?->order_number }}</span>
                        @if($packOrderObj?->customer_name)
                        · {{ $packOrderObj->customer_name }}
                        @endif
                    </p>
                </div>
                <button wire:click="closePackingModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-zinc-200 mt-0.5">
                    <x-heroicon-o-x-mark class="w-5 h-5" />
                </button>
            </div>

            {{-- Status summary --}}
            <div class="px-6 py-3 flex-shrink-0
                {{ $packAllOk
                    ? 'bg-green-50 dark:bg-green-900/20 border-b border-green-200 dark:border-green-800'
                    : 'bg-amber-50 dark:bg-amber-900/20 border-b border-amber-200 dark:border-amber-800' }}">
                <div class="flex items-center gap-3">
                    @if($packAllOk)
                        <span class="inline-flex items-center gap-1.5 text-sm font-semibold text-green-700 dark:text-green-300">
                            <x-heroicon-s-check-circle class="w-4 h-4" />
                            Tudo conferido — pronto para embalar
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 text-sm font-semibold text-amber-700 dark:text-amber-300">
                            <x-heroicon-s-exclamation-triangle class="w-4 h-4" />
                            {{ $packTotalOrdered - $packTotalConfirmed }} unid. faltando
                        </span>
                    @endif
                    <span class="ml-auto text-sm font-bold tabular-nums {{ $packAllOk ? 'text-green-700 dark:text-green-300' : 'text-amber-700 dark:text-amber-300' }}">
                        {{ $packTotalConfirmed }} / {{ $packTotalOrdered }}
                    </span>
                    <span class="text-xs text-gray-400 dark:text-zinc-500">unidades</span>
                </div>

                {{-- Barra de progresso --}}
                <div class="mt-2 h-1.5 rounded-full bg-gray-200 dark:bg-zinc-700 overflow-hidden">
                    <div class="h-full rounded-full transition-all duration-300
                        {{ $packAllOk ? 'bg-green-500' : 'bg-amber-400' }}"
                         style="width: {{ $packTotalOrdered > 0 ? min(100, round($packTotalConfirmed / $packTotalOrdered * 100)) : 0 }}%">
                    </div>
                </div>
            </div>

            {{-- Lista de itens (scrollable) --}}
            <div class="overflow-y-auto flex-1 px-6 py-4 space-y-2">
                @foreach($packingItems as $pItem)
                @php
                    $pQtyConf = max(0, (int) ($packingChecks[(string) $pItem['id']] ?? 0));
                    $pDiff    = $pItem['quantity'] - $pQtyConf;
                    $pItemOk  = $pDiff <= 0;
                @endphp
                <div class="flex items-center gap-3 p-3 rounded-xl border transition-colors
                    {{ $pItemOk
                        ? 'border-green-200 dark:border-green-800/60 bg-green-50/60 dark:bg-green-900/10'
                        : 'border-amber-200 dark:border-amber-800/60 bg-amber-50/60 dark:bg-amber-900/10' }}">

                    {{-- Imagem --}}
                    @if($pItem['img_url'])
                    <img src="{{ $pItem['img_url'] }}" alt="{{ $pItem['name'] }}"
                         class="w-12 h-12 rounded-lg object-cover flex-shrink-0 border border-gray-200 dark:border-zinc-700">
                    @else
                    <div class="w-12 h-12 rounded-lg bg-gray-100 dark:bg-zinc-700 flex items-center justify-center flex-shrink-0">
                        <x-heroicon-o-cube class="w-6 h-6 text-gray-300 dark:text-zinc-500" />
                    </div>
                    @endif

                    {{-- Info --}}
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-sm text-gray-900 dark:text-white leading-tight line-clamp-2">{{ $pItem['name'] }}</p>
                        @if($pItem['sku'])
                        <p class="text-[11px] font-mono text-gray-400 dark:text-zinc-500 mt-0.5">{{ $pItem['sku'] }}</p>
                        @endif
                    </div>

                    {{-- Pedido qty --}}
                    <div class="text-center flex-shrink-0 w-14">
                        <p class="text-[10px] uppercase tracking-wide text-gray-400 dark:text-zinc-500">Pedido</p>
                        <p class="text-lg font-bold text-gray-700 dark:text-zinc-200">{{ $pItem['quantity'] }}</p>
                    </div>

                    {{-- Seta --}}
                    <x-heroicon-o-arrow-right class="w-4 h-4 text-gray-300 dark:text-zinc-600 flex-shrink-0" />

                    {{-- Input qty conferida --}}
                    <div class="text-center flex-shrink-0 w-20">
                        <p class="text-[10px] uppercase tracking-wide {{ $pItemOk ? 'text-green-600 dark:text-green-400' : 'text-amber-600 dark:text-amber-400' }}">Conferido</p>
                        <input type="number"
                               wire:model.live="packingChecks.{{ $pItem['id'] }}"
                               min="0"
                               max="{{ $pItem['quantity'] * 3 }}"
                               class="w-20 text-center text-lg font-bold rounded-lg border-2 px-2 py-1 outline-none focus:ring-2 transition-colors
                                   {{ $pItemOk
                                       ? 'border-green-400 dark:border-green-600 text-green-700 dark:text-green-300 bg-white dark:bg-zinc-800 focus:ring-green-300'
                                       : 'border-amber-400 dark:border-amber-600 text-amber-700 dark:text-amber-300 bg-white dark:bg-zinc-800 focus:ring-amber-300' }}">
                    </div>

                    {{-- Status icon --}}
                    <div class="flex-shrink-0 w-10 flex justify-center">
                        @if($pItemOk)
                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-green-100 dark:bg-green-900/40">
                            <x-heroicon-s-check class="w-4 h-4 text-green-600 dark:text-green-400" />
                        </span>
                        @elseif($pQtyConf === 0)
                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-red-100 dark:bg-red-900/40">
                            <x-heroicon-o-x-mark class="w-4 h-4 text-red-500 dark:text-red-400" />
                        </span>
                        @else
                        <span class="inline-flex items-center justify-center text-xs font-bold w-8 h-8 rounded-full bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300">
                            -{{ $pDiff }}
                        </span>
                        @endif
                    </div>
                </div>
                @endforeach

                {{-- Mensagem de erro (itens faltando ao tentar confirmar) --}}
                @error('packingChecks')
                <div class="flex items-start gap-2 text-sm text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-900/20 rounded-xl px-4 py-3 border border-amber-200 dark:border-amber-700 mt-2">
                    <x-heroicon-s-exclamation-triangle class="w-4 h-4 flex-shrink-0 mt-0.5" />
                    <span>{{ $message }}</span>
                </div>
                @enderror

                {{-- Observações --}}
                <div class="pt-2">
                    <label class="block text-xs font-semibold text-gray-500 dark:text-zinc-400 uppercase tracking-wide mb-1">
                        Observações <span class="font-normal normal-case">(opcional — ficará no histórico)</span>
                    </label>
                    <textarea wire:model="packingNotes"
                              rows="2"
                              placeholder="Ex: embalagem danificada, item trocado, cliente autorizou envio parcial..."
                              class="w-full text-sm rounded-xl border border-gray-300 dark:border-zinc-600 bg-white dark:bg-zinc-800 text-gray-900 dark:text-white px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent resize-none placeholder-gray-400 dark:placeholder-zinc-500"></textarea>
                </div>
            </div>

            {{-- Footer --}}
            <div class="px-6 py-4 border-t border-gray-200 dark:border-zinc-700 flex-shrink-0">
                <div class="flex items-center justify-between gap-3">
                    <button wire:click="closePackingModal" class="btn-ghost btn-sm">
                        Cancelar
                    </button>
                    <div class="flex items-center gap-2">
                        @if(!$packAllOk)
                        <button wire:click="confirmPacking(true)"
                                wire:confirm="Confirmar embalagem parcial ({{ $packTotalConfirmed }}/{{ $packTotalOrdered }} unid.)? Esta informação ficará registrada no histórico."
                                wire:loading.attr="disabled"
                                wire:target="confirmPacking"
                                class="btn-secondary btn-sm">
                            <x-heroicon-o-archive-box class="w-4 h-4" />
                            Forçar (parcial)
                        </button>
                        @endif
                        <button wire:click="confirmPacking(false)"
                                wire:loading.attr="disabled"
                                wire:target="confirmPacking"
                                class="btn-primary btn-sm">
                            <span wire:loading.remove wire:target="confirmPacking">
                                <x-heroicon-o-archive-box-arrow-down class="w-4 h-4 inline" />
                                {{ $packAllOk ? 'Confirmar e Embalar' : 'Salvar Conferência' }}
                            </span>
                            <span wire:loading wire:target="confirmPacking" class="text-sm">Salvando...</span>
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </div>
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

    {{-- ============================================================
         MODAL — Emissão NF-e (Webmaniabr)
    ============================================================ --}}
    @if($showNfeModal && $nfeOrderId)
    @php $nfeOrder = \App\Models\Order::select('id','order_number','customer_name')->find($nfeOrderId); @endphp
    <div class="fixed inset-0 z-[60] flex items-center justify-center bg-black/60 backdrop-blur-sm"
         @keydown.escape.window="$wire.closeNfeModal()">
        <div class="bg-white dark:bg-zinc-900 rounded-2xl shadow-2xl w-full max-w-lg mx-4 flex flex-col"
             style="max-height: 85vh"
             @click.outside="$wire.closeNfeModal()">

            {{-- Header --}}
            <div class="flex items-start justify-between px-6 py-4 border-b border-gray-200 dark:border-zinc-700">
                <div>
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <x-heroicon-o-document-check class="w-5 h-5 text-primary-500" />
                        Emitir NF-e
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-zinc-400 mt-0.5">
                        <span class="font-mono font-semibold text-gray-700 dark:text-zinc-300">{{ $nfeOrder?->order_number }}</span>
                        @if($nfeOrder?->customer_name) · {{ $nfeOrder->customer_name }} @endif
                    </p>
                </div>
                <button wire:click="closeNfeModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-zinc-200 mt-0.5">
                    <x-heroicon-o-x-mark class="w-5 h-5" />
                </button>
            </div>

            {{-- Corpo --}}
            <div class="overflow-y-auto flex-1 px-6 py-4 space-y-4">
                <div>
                    <label class="form-label">Natureza da Operação</label>
                    <input type="text" wire:model="nfeNatureOp" class="form-input" placeholder="Venda">
                </div>
                <div>
                    <label class="form-label">Informações ao Fisco <span class="text-gray-400 font-normal">(opcional)</span></label>
                    <textarea wire:model="nfeInfoFisco" rows="2" class="form-input" placeholder="Informações adicionais ao fisco..."></textarea>
                </div>
                <div>
                    <label class="form-label">Informações ao Consumidor <span class="text-gray-400 font-normal">(opcional)</span></label>
                    <textarea wire:model="nfeInfoConsumer" rows="2" class="form-input" placeholder="Informações para o consumidor..."></textarea>
                </div>
                <div class="flex items-center gap-2">
                    <input type="checkbox" wire:model="nfeHomologation" id="nfe-homolog"
                           class="rounded border-gray-300 dark:border-zinc-600 text-primary-600 focus:ring-primary-500">
                    <label for="nfe-homolog" class="text-sm text-gray-700 dark:text-zinc-300">Emitir em homologação (teste)</label>
                </div>

                @error('nfe')
                <div class="flex items-start gap-2 text-sm text-red-700 dark:text-red-300 bg-red-50 dark:bg-red-900/20 rounded-xl px-4 py-3 border border-red-200 dark:border-red-700">
                    <x-heroicon-s-exclamation-triangle class="w-4 h-4 flex-shrink-0 mt-0.5" />
                    <span>{{ $message }}</span>
                </div>
                @enderror
            </div>

            {{-- Footer --}}
            <div class="px-6 py-4 border-t border-gray-200 dark:border-zinc-700 flex items-center justify-between gap-3">
                <button wire:click="closeNfeModal" class="btn-ghost btn-sm">Cancelar</button>
                <div class="flex items-center gap-2">
                    <button wire:click="emitNfe('preview')"
                            wire:loading.attr="disabled"
                            wire:target="emitNfe"
                            class="btn-secondary btn-sm"
                            @if($nfeLoading) disabled @endif>
                        <x-heroicon-o-eye class="w-4 h-4" />
                        Pré-visualizar
                    </button>
                    <button wire:click="emitNfe('emit')"
                            wire:loading.attr="disabled"
                            wire:target="emitNfe"
                            class="btn-primary btn-sm"
                            @if($nfeLoading) disabled @endif>
                        <span wire:loading.remove wire:target="emitNfe">
                            <x-heroicon-o-document-check class="w-4 h-4 inline" />
                            Emitir NF-e
                        </span>
                        <span wire:loading wire:target="emitNfe" class="text-sm">Emitindo...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- ============================================================
         MODAL — Cotação de Frete (Melhor Envios)
    ============================================================ --}}
    @if($showShippingModal && $shippingOrderId)
    @php $shipOrder = \App\Models\Order::select('id','order_number','customer_name')->find($shippingOrderId); @endphp
    <div class="fixed inset-0 z-[60] flex items-center justify-center bg-black/60 backdrop-blur-sm"
         @keydown.escape.window="$wire.closeShippingModal()">
        <div class="bg-white dark:bg-zinc-900 rounded-2xl shadow-2xl w-full max-w-2xl mx-4 flex flex-col"
             style="max-height: 92vh"
             @click.outside="$wire.closeShippingModal()">

            {{-- Header --}}
            <div class="flex items-start justify-between px-6 py-4 border-b border-gray-200 dark:border-zinc-700">
                <div>
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <x-heroicon-o-truck class="w-5 h-5 text-primary-500" />
                        Cotação de Frete — Melhor Envios
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-zinc-400 mt-0.5">
                        <span class="font-mono font-semibold text-gray-700 dark:text-zinc-300">{{ $shipOrder?->order_number }}</span>
                        @if($shipOrder?->customer_name) · {{ $shipOrder->customer_name }} @endif
                    </p>
                </div>
                <button wire:click="closeShippingModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-zinc-200 mt-0.5">
                    <x-heroicon-o-x-mark class="w-5 h-5" />
                </button>
            </div>

            {{-- Corpo --}}
            <div class="overflow-y-auto flex-1 px-6 py-4 space-y-4">
                {{-- Dimensões --}}
                <div class="grid grid-cols-4 gap-3">
                    <div>
                        <label class="form-label text-xs">Peso (kg)</label>
                        <input type="number" step="0.01" wire:model="shippingWeight" class="form-input text-sm" min="0.01">
                    </div>
                    <div>
                        <label class="form-label text-xs">Largura (cm)</label>
                        <input type="number" step="0.1" wire:model="shippingWidth" class="form-input text-sm" min="1">
                    </div>
                    <div>
                        <label class="form-label text-xs">Altura (cm)</label>
                        <input type="number" step="0.1" wire:model="shippingHeight" class="form-input text-sm" min="1">
                    </div>
                    <div>
                        <label class="form-label text-xs">Comprimento (cm)</label>
                        <input type="number" step="0.1" wire:model="shippingLength" class="form-input text-sm" min="1">
                    </div>
                </div>

                <button wire:click="calculateShippingQuote"
                        wire:loading.attr="disabled"
                        wire:target="calculateShippingQuote"
                        class="btn-primary btn-sm w-full"
                        @if($shippingLoading) disabled @endif>
                    <span wire:loading.remove wire:target="calculateShippingQuote">
                        <x-heroicon-o-calculator class="w-4 h-4 inline" />
                        Calcular Cotação
                    </span>
                    <span wire:loading wire:target="calculateShippingQuote" class="text-sm">Calculando...</span>
                </button>

                {{-- Erro --}}
                @if($shippingError)
                <div class="flex items-start gap-2 text-sm text-red-700 dark:text-red-300 bg-red-50 dark:bg-red-900/20 rounded-xl px-4 py-3 border border-red-200 dark:border-red-700">
                    <x-heroicon-s-exclamation-triangle class="w-4 h-4 flex-shrink-0 mt-0.5" />
                    <span>{{ $shippingError }}</span>
                </div>
                @endif

                {{-- Resultados --}}
                @if(!empty($shippingQuotes))
                <div class="space-y-2">
                    <p class="text-xs font-semibold text-gray-500 dark:text-zinc-400 uppercase tracking-wide">
                        {{ count($shippingQuotes) }} opções de frete
                    </p>
                    @foreach($shippingQuotes as $qKey => $quote)
                    <div wire:click="selectShippingQuote('{{ $qKey }}')"
                         class="flex items-center gap-3 p-3 rounded-xl border-2 cursor-pointer transition-all
                            {{ $shippingSelectedKey == $qKey
                                ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20'
                                : 'border-gray-200 dark:border-zinc-700 hover:border-primary-300 dark:hover:border-primary-700' }}">
                        {{-- Radio --}}
                        <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center flex-shrink-0
                            {{ $shippingSelectedKey == $qKey
                                ? 'border-primary-500'
                                : 'border-gray-300 dark:border-zinc-600' }}">
                            @if($shippingSelectedKey == $qKey)
                            <div class="w-2.5 h-2.5 rounded-full bg-primary-500"></div>
                            @endif
                        </div>
                        {{-- Info --}}
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-sm text-gray-900 dark:text-white">
                                {{ $quote['company']['name'] ?? 'N/A' }} — {{ $quote['name'] ?? '' }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-zinc-400">
                                Prazo: {{ $quote['delivery_time'] ?? '?' }} dias úteis
                                @if(!empty($quote['delivery_range']))
                                    ({{ $quote['delivery_range']['min'] ?? '?' }}-{{ $quote['delivery_range']['max'] ?? '?' }})
                                @endif
                            </p>
                        </div>
                        {{-- Preço --}}
                        <div class="text-right flex-shrink-0">
                            <p class="text-lg font-bold text-gray-900 dark:text-white tabular-nums">
                                R$ {{ number_format((float)($quote['price'] ?? 0), 2, ',', '.') }}
                            </p>
                            @if(!empty($quote['discount']))
                            <p class="text-[10px] text-green-600 dark:text-green-400 line-through">
                                R$ {{ number_format((float)($quote['custom_price'] ?? $quote['price']), 2, ',', '.') }}
                            </p>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>

            {{-- Footer --}}
            @if(!empty($shippingQuotes))
            <div class="px-6 py-4 border-t border-gray-200 dark:border-zinc-700 flex items-center justify-between gap-3">
                <button wire:click="closeShippingModal" class="btn-ghost btn-sm">Cancelar</button>
                <button wire:click="purchaseShippingLabel"
                        wire:loading.attr="disabled"
                        wire:target="purchaseShippingLabel"
                        class="btn-primary btn-sm"
                        @if(!$shippingSelectedKey || $shippingPurchasing) disabled @endif>
                    <span wire:loading.remove wire:target="purchaseShippingLabel">
                        <x-heroicon-o-shopping-cart class="w-4 h-4 inline" />
                        Comprar Etiqueta
                    </span>
                    <span wire:loading wire:target="purchaseShippingLabel" class="text-sm">Comprando...</span>
                </button>
            </div>
            @endif
        </div>
    </div>
    @endif

</div>
