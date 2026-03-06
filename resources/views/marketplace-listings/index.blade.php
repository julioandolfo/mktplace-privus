<x-app-layout>
    <x-slot name="header">Anúncios</x-slot>
    <x-slot name="subtitle">Gerencie seus anúncios do Mercado Livre</x-slot>
    <x-slot name="actions">
        <a href="{{ route('listings.publish-form') }}" class="btn-primary btn-sm">
            <x-heroicon-o-rocket-launch class="w-4 h-4" />
            Publicar Anúncio
        </a>
    </x-slot>
    <x-slot name="breadcrumbs">
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <span class="text-gray-700 dark:text-zinc-200">Anúncios</span>
        </li>
    </x-slot>

    <div class="space-y-5" x-data="{
        view: localStorage.getItem('listings_view') || 'list',
        setView(v) { this.view = v; localStorage.setItem('listings_view', v); },
        selected: [],
        allSelected: false,
        toggleAll(ids) {
            if (this.allSelected) { this.selected = []; this.allSelected = false; }
            else { this.selected = ids; this.allSelected = true; }
        },
        toggleOne(id) {
            if (this.selected.includes(id)) this.selected = this.selected.filter(s => s !== id);
            else this.selected.push(id);
        },
    }">

        {{-- ═══ Stats Cards ══════════════════════════════════════════════════════ --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            {{-- Total --}}
            <div class="card p-4 flex items-center gap-3 cursor-default">
                <div class="w-11 h-11 rounded-xl bg-primary-500/10 flex items-center justify-center flex-shrink-0">
                    <x-heroicon-o-megaphone class="w-5 h-5 text-primary-500" />
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-white tabular-nums">{{ number_format($totalListings) }}</div>
                    <div class="text-xs text-gray-500 dark:text-zinc-400 mt-0.5">Total de Anúncios</div>
                </div>
            </div>
            {{-- Ativos --}}
            <a href="{{ route('listings.index', array_merge(request()->except('status', 'page'), ['status' => 'active'])) }}"
               class="card p-4 flex items-center gap-3 hover:border-emerald-500/50 transition-colors cursor-pointer
                      {{ request('status') === 'active' ? 'border-emerald-500 bg-emerald-500/5' : '' }}">
                <div class="w-11 h-11 rounded-xl bg-emerald-500/10 flex items-center justify-center flex-shrink-0">
                    <x-heroicon-o-check-circle class="w-5 h-5 text-emerald-500" />
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-white tabular-nums">{{ number_format($activeCount) }}</div>
                    <div class="text-xs text-gray-500 dark:text-zinc-400 mt-0.5">Ativos</div>
                </div>
            </a>
            {{-- Pausados --}}
            <a href="{{ route('listings.index', array_merge(request()->except('status', 'page'), ['status' => 'paused'])) }}"
               class="card p-4 flex items-center gap-3 hover:border-amber-500/50 transition-colors cursor-pointer
                      {{ request('status') === 'paused' ? 'border-amber-500 bg-amber-500/5' : '' }}">
                <div class="w-11 h-11 rounded-xl bg-amber-500/10 flex items-center justify-center flex-shrink-0">
                    <x-heroicon-o-pause-circle class="w-5 h-5 text-amber-500" />
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-white tabular-nums">{{ number_format($pausedCount) }}</div>
                    <div class="text-xs text-gray-500 dark:text-zinc-400 mt-0.5">Pausados</div>
                </div>
            </a>
            {{-- Sem produto --}}
            <a href="{{ route('listings.index', array_merge(request()->except('linked', 'page'), ['linked' => '0'])) }}"
               class="card p-4 flex items-center gap-3 hover:border-red-500/50 transition-colors cursor-pointer
                      {{ request('linked') === '0' ? 'border-red-500 bg-red-500/5' : '' }}">
                <div class="w-11 h-11 rounded-xl bg-red-500/10 flex items-center justify-center flex-shrink-0">
                    <x-heroicon-o-link-slash class="w-5 h-5 text-red-500" />
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-white tabular-nums">{{ number_format($unlinkedCount) }}</div>
                    <div class="text-xs text-gray-500 dark:text-zinc-400 mt-0.5">Sem Produto</div>
                </div>
            </a>
        </div>

        {{-- ═══ Per-Account Tabs ══════════════════════════════════════════════════ --}}
        @if($perAccount->count() > 1)
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('listings.index', request()->except('account', 'page')) }}"
               class="px-3 py-1.5 rounded-full text-sm font-medium transition-colors
                      {{ ! request('account') ? 'bg-primary-500 text-white' : 'bg-gray-100 dark:bg-zinc-800 text-gray-600 dark:text-zinc-300 hover:bg-gray-200 dark:hover:bg-zinc-700' }}">
                Todas
            </a>
            @foreach($perAccount as $row)
            <a href="{{ route('listings.index', array_merge(request()->except('account', 'page'), ['account' => $row->marketplace_account_id])) }}"
               class="flex items-center gap-2 px-3 py-1.5 rounded-full text-sm font-medium transition-colors
                      {{ request('account') == $row->marketplace_account_id
                          ? 'bg-primary-500 text-white'
                          : 'bg-gray-100 dark:bg-zinc-800 text-gray-600 dark:text-zinc-300 hover:bg-gray-200 dark:hover:bg-zinc-700' }}">
                <span class="w-2 h-2 rounded-full bg-emerald-400 flex-shrink-0"></span>
                {{ $row->marketplaceAccount?->account_name ?? 'Conta #'.$row->marketplace_account_id }}
                <span class="text-xs opacity-70">({{ $row->total }})</span>
            </a>
            @endforeach
        </div>
        @endif

        {{-- ═══ Filters + View Toggle ════════════════════════════════════════════ --}}
        <div class="card p-3">
            <form method="GET" class="flex flex-wrap items-center gap-2">
                {{-- Preserve non-filter params --}}
                @foreach(request()->except(['search','account','status','linked','page']) as $k => $v)
                    <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                @endforeach

                <div class="relative flex-1 min-w-52">
                    <x-heroicon-o-magnifying-glass class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 dark:text-zinc-500 pointer-events-none" />
                    <input type="text" name="search" value="{{ request('search') }}"
                        placeholder="Buscar por título ou ID do anúncio..."
                        class="form-input pl-9 w-full">
                </div>

                <select name="status" class="form-input w-40">
                    <option value="">Todos status</option>
                    @foreach(['active' => 'Ativo', 'paused' => 'Pausado', 'closed' => 'Encerrado', 'deleted' => 'Deletado'] as $val => $label)
                        <option value="{{ $val }}" @selected(request('status') === $val)>{{ $label }}</option>
                    @endforeach
                </select>

                <select name="linked" class="form-input w-44">
                    <option value="">Com e sem produto</option>
                    <option value="1" @selected(request('linked') === '1')>Com produto vinculado</option>
                    <option value="0" @selected(request('linked') === '0')>Sem produto vinculado</option>
                </select>

                <button type="submit" class="btn-primary btn-sm">
                    <x-heroicon-o-funnel class="w-4 h-4" />
                    Filtrar
                </button>
                @if(request()->hasAny(['search','account','status','linked']))
                <a href="{{ route('listings.index') }}" class="btn-ghost btn-sm text-gray-500">
                    <x-heroicon-o-x-mark class="w-4 h-4" />
                    Limpar
                </a>
                @endif

                {{-- Spacer --}}
                <div class="flex-1 hidden sm:block"></div>

                {{-- View Toggle --}}
                <div class="flex items-center gap-1 border border-gray-200 dark:border-zinc-700 rounded-lg p-1">
                    <button type="button" @click="setView('list')"
                        :class="view === 'list' ? 'bg-primary-500 text-white' : 'text-gray-500 dark:text-zinc-400 hover:text-gray-700 dark:hover:text-zinc-200'"
                        class="p-1.5 rounded-md transition-colors" title="Visualização lista">
                        <x-heroicon-o-list-bullet class="w-4 h-4" />
                    </button>
                    <button type="button" @click="setView('grid')"
                        :class="view === 'grid' ? 'bg-primary-500 text-white' : 'text-gray-500 dark:text-zinc-400 hover:text-gray-700 dark:hover:text-zinc-200'"
                        class="p-1.5 rounded-md transition-colors" title="Visualização grade">
                        <x-heroicon-o-squares-2x2 class="w-4 h-4" />
                    </button>
                </div>
            </form>

            {{-- Active filter chips --}}
            @if(request()->hasAny(['search','status','linked']))
            <div class="flex flex-wrap gap-2 mt-2 pt-2 border-t border-gray-100 dark:border-zinc-800">
                @if(request('search'))
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-primary-500/10 text-primary-600 dark:text-primary-400 text-xs rounded-full">
                    <x-heroicon-o-magnifying-glass class="w-3 h-3" />
                    "{{ request('search') }}"
                    <a href="{{ route('listings.index', request()->except(['search', 'page'])) }}" class="hover:text-primary-800 dark:hover:text-primary-300">
                        <x-heroicon-o-x-mark class="w-3 h-3" />
                    </a>
                </span>
                @endif
                @if(request('status'))
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-primary-500/10 text-primary-600 dark:text-primary-400 text-xs rounded-full">
                    Status: {{ ['active'=>'Ativo','paused'=>'Pausado','closed'=>'Encerrado','deleted'=>'Deletado'][request('status')] ?? request('status') }}
                    <a href="{{ route('listings.index', request()->except(['status', 'page'])) }}" class="hover:text-primary-800 dark:hover:text-primary-300">
                        <x-heroicon-o-x-mark class="w-3 h-3" />
                    </a>
                </span>
                @endif
                @if(request('linked') !== null && request('linked') !== '')
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-primary-500/10 text-primary-600 dark:text-primary-400 text-xs rounded-full">
                    {{ request('linked') === '1' ? 'Com produto' : 'Sem produto' }}
                    <a href="{{ route('listings.index', request()->except(['linked', 'page'])) }}" class="hover:text-primary-800 dark:hover:text-primary-300">
                        <x-heroicon-o-x-mark class="w-3 h-3" />
                    </a>
                </span>
                @endif
            </div>
            @endif
        </div>

        {{-- ═══ Bulk Action Bar (quando há selecionados) ══════════════════════════ --}}
        <div x-show="selected.length > 0"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             class="sticky top-4 z-20 card p-3 flex items-center gap-3 border-primary-500 bg-primary-500/5">
            <x-heroicon-o-check-circle class="w-5 h-5 text-primary-500 flex-shrink-0" />
            <span class="text-sm font-medium text-gray-900 dark:text-white" x-text="`${selected.length} anúncio(s) selecionado(s)`"></span>
            <div class="flex items-center gap-2 ml-auto">
                <form method="POST" action="{{ route('listings.bulk-action') }}" id="bulk-form">
                    @csrf
                    <template x-for="id in selected" :key="id">
                        <input type="hidden" name="ids[]" :value="id">
                    </template>
                    <div class="flex gap-2">
                        <button type="submit" name="action" value="pause"
                            class="btn-secondary btn-sm"
                            onclick="return confirm('Pausar anúncios selecionados?')">
                            <x-heroicon-o-pause class="w-4 h-4" />
                            Pausar
                        </button>
                        <button type="submit" name="action" value="activate"
                            class="btn-secondary btn-sm"
                            onclick="return confirm('Ativar anúncios selecionados?')">
                            <x-heroicon-o-play class="w-4 h-4" />
                            Ativar
                        </button>
                    </div>
                </form>
                <button @click="selected = []; allSelected = false" class="btn-ghost btn-sm text-gray-500">
                    <x-heroicon-o-x-mark class="w-4 h-4" />
                </button>
            </div>
        </div>

        {{-- ═══ List View ═════════════════════════════════════════════════════════ --}}
        <div x-show="view === 'list'" x-cloak>
            @php $allIds = $listings->pluck('id')->toArray(); @endphp
            <div class="card overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-zinc-700 bg-gray-50/80 dark:bg-zinc-800/60">
                            <th class="px-4 py-3 text-left w-8">
                                <input type="checkbox" class="rounded border-gray-300 dark:border-zinc-600 text-primary-500 focus:ring-primary-500"
                                    @change="toggleAll({{ json_encode($allIds) }})"
                                    :checked="allSelected">
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Anúncio</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Preço</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Estoque</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Vendidos</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Produto</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-zinc-800">
                        @forelse($listings as $listing)
                        @php
                            $thumb     = $listing->meta['thumbnail'] ?? null;
                            $sold      = $listing->meta['sold_quantity'] ?? 0;
                            $isFree    = $listing->meta['is_free_shipping'] ?? false;
                            $isFulfill = $listing->meta['is_fulfillment'] ?? false;
                            $listType  = $listing->meta['listing_type_id'] ?? null;
                            $condition = $listing->meta['condition'] ?? null;
                        @endphp
                        <tr class="hover:bg-gray-50/60 dark:hover:bg-zinc-800/40 transition-colors group"
                            :class="selected.includes({{ $listing->id }}) ? 'bg-primary-500/5 dark:bg-primary-500/5' : ''">
                            {{-- Checkbox --}}
                            <td class="px-4 py-3">
                                <input type="checkbox" class="rounded border-gray-300 dark:border-zinc-600 text-primary-500 focus:ring-primary-500"
                                    :checked="selected.includes({{ $listing->id }})"
                                    @change="toggleOne({{ $listing->id }})">
                            </td>
                            {{-- Listing info --}}
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    {{-- Thumbnail --}}
                                    <div class="w-14 h-14 flex-shrink-0 rounded-lg overflow-hidden bg-gray-100 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700">
                                        @if($thumb)
                                            <img src="{{ $thumb }}" alt="" class="w-full h-full object-cover" loading="lazy"
                                                 onerror="this.parentElement.innerHTML='<div class=\'w-full h-full flex items-center justify-center\'><svg class=\'w-6 h-6 text-gray-300\' fill=\'none\' viewBox=\'0 0 24 24\' stroke=\'currentColor\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'1.5\' d=\'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z\'/></svg></div>'">
                                        @else
                                            <div class="w-full h-full flex items-center justify-center">
                                                <x-heroicon-o-photo class="w-6 h-6 text-gray-300 dark:text-zinc-600" />
                                            </div>
                                        @endif
                                    </div>
                                    {{-- Title + meta --}}
                                    <div class="min-w-0">
                                        <a href="{{ route('listings.show', $listing) }}"
                                           class="font-medium text-gray-900 dark:text-white hover:text-primary-500 dark:hover:text-primary-400 line-clamp-2 leading-snug transition-colors">
                                            {{ $listing->title }}
                                        </a>
                                        <div class="flex flex-wrap items-center gap-1.5 mt-1">
                                            <span class="text-xs font-mono text-gray-400 dark:text-zinc-500">{{ $listing->external_id }}</span>
                                            @if($listType)
                                            <span class="text-[10px] px-1.5 py-0.5 rounded font-medium
                                                {{ match($listType) { 'gold_pro' => 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400', 'gold_special' => 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400', default => 'bg-gray-100 dark:bg-zinc-800 text-gray-600 dark:text-zinc-400' } }}">
                                                {{ match($listType) { 'gold_pro' => 'Premium', 'gold_special' => 'Clássico', 'free' => 'Grátis', default => $listType } }}
                                            </span>
                                            @endif
                                            @if($isFulfill)
                                            <span class="text-[10px] px-1.5 py-0.5 rounded font-medium bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-400">Full</span>
                                            @endif
                                            @if($isFree)
                                            <span class="text-[10px] px-1.5 py-0.5 rounded font-medium bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400">
                                                Frete Grátis
                                            </span>
                                            @endif
                                            @if($condition === 'used')
                                            <span class="text-[10px] px-1.5 py-0.5 rounded font-medium bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-400">Usado</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </td>
                            {{-- Status --}}
                            <td class="px-4 py-3">
                                <x-ui.badge :color="$listing->status_color">
                                    {{ match($listing->status) {
                                        'active'  => 'Ativo',
                                        'paused'  => 'Pausado',
                                        'closed'  => 'Encerrado',
                                        'deleted' => 'Deletado',
                                        default   => $listing->status,
                                    } }}
                                </x-ui.badge>
                            </td>
                            {{-- Price --}}
                            <td class="px-4 py-3 text-right">
                                <span class="font-mono font-semibold text-gray-900 dark:text-white">
                                    R$ {{ number_format($listing->price, 2, ',', '.') }}
                                </span>
                            </td>
                            {{-- Stock --}}
                            <td class="px-4 py-3 text-right">
                                @php $qty = $listing->available_quantity; @endphp
                                <span class="font-mono text-sm {{ $qty !== null && $qty <= 3 ? 'text-red-500 font-bold' : 'text-gray-700 dark:text-zinc-300' }}">
                                    {{ $qty ?? '—' }}
                                </span>
                                @if($qty !== null && $qty <= 3 && $qty > 0)
                                <div class="text-[10px] text-red-400 leading-none mt-0.5">Baixo!</div>
                                @elseif($qty === 0)
                                <div class="text-[10px] text-red-500 leading-none mt-0.5 font-semibold">Esgotado</div>
                                @endif
                            </td>
                            {{-- Sold --}}
                            <td class="px-4 py-3 text-right">
                                <span class="font-mono text-sm text-gray-700 dark:text-zinc-300">
                                    {{ number_format($sold) }}
                                </span>
                            </td>
                            {{-- Product --}}
                            <td class="px-4 py-3">
                                @if($listing->product)
                                    <div class="flex items-center gap-1.5">
                                        <x-heroicon-s-check-circle class="w-3.5 h-3.5 text-emerald-500 flex-shrink-0" />
                                        <a href="{{ route('products.edit', $listing->product) }}"
                                           class="text-sm text-gray-700 dark:text-zinc-300 hover:text-primary-500 truncate max-w-32">
                                            {{ $listing->product->name }}
                                        </a>
                                        @if($listing->product_quantity > 1)
                                        <span class="text-[10px] bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 px-1.5 py-0.5 rounded font-medium">
                                            ×{{ $listing->product_quantity }}
                                        </span>
                                        @endif
                                    </div>
                                @else
                                    <a href="{{ route('listings.show', $listing) }}#vincular"
                                       class="text-xs text-amber-600 dark:text-amber-400 hover:text-amber-700 flex items-center gap-1">
                                        <x-heroicon-o-link class="w-3.5 h-3.5" />
                                        Vincular produto
                                    </a>
                                @endif
                            </td>
                            {{-- Actions --}}
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <a href="{{ route('listings.show', $listing) }}"
                                       class="p-1.5 rounded-md hover:bg-gray-100 dark:hover:bg-zinc-700 text-gray-500 dark:text-zinc-400 hover:text-gray-700 dark:hover:text-zinc-200 transition-colors"
                                       title="Ver detalhes">
                                        <x-heroicon-o-eye class="w-4 h-4" />
                                    </a>
                                    <a href="{{ route('listings.show', $listing) }}#editar"
                                       class="p-1.5 rounded-md hover:bg-gray-100 dark:hover:bg-zinc-700 text-gray-500 dark:text-zinc-400 hover:text-gray-700 dark:hover:text-zinc-200 transition-colors"
                                       title="Editar">
                                        <x-heroicon-o-pencil-square class="w-4 h-4" />
                                    </a>
                                    {{-- Quick pause/activate --}}
                                    @if(in_array($listing->status, ['active', 'paused']))
                                    <form method="POST" action="{{ route('listings.toggle-status', $listing) }}">
                                        @csrf
                                        <button type="submit"
                                            class="p-1.5 rounded-md hover:bg-gray-100 dark:hover:bg-zinc-700 transition-colors
                                                   {{ $listing->status === 'active' ? 'text-amber-500 hover:text-amber-600' : 'text-emerald-500 hover:text-emerald-600' }}"
                                            title="{{ $listing->status === 'active' ? 'Pausar anúncio' : 'Ativar anúncio' }}"
                                            onclick="return confirm('{{ $listing->status === 'active' ? 'Pausar este anúncio?' : 'Ativar este anúncio?' }}')">
                                            @if($listing->status === 'active')
                                                <x-heroicon-o-pause class="w-4 h-4" />
                                            @else
                                                <x-heroicon-o-play class="w-4 h-4" />
                                            @endif
                                        </button>
                                    </form>
                                    @endif
                                    {{-- ML link --}}
                                    @if($listing->meta['ml_permalink'] ?? null)
                                    <a href="{{ $listing->meta['ml_permalink'] }}" target="_blank"
                                       class="p-1.5 rounded-md hover:bg-gray-100 dark:hover:bg-zinc-700 text-gray-500 dark:text-zinc-400 hover:text-primary-500 transition-colors"
                                       title="Ver no Mercado Livre">
                                        <x-heroicon-o-arrow-top-right-on-square class="w-4 h-4" />
                                    </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="px-4 py-16 text-center">
                                <div class="flex flex-col items-center gap-3">
                                    <x-heroicon-o-megaphone class="w-12 h-12 text-gray-300 dark:text-zinc-600" />
                                    <div>
                                        <p class="text-sm font-medium text-gray-500 dark:text-zinc-400">Nenhum anúncio encontrado</p>
                                        @if(request()->hasAny(['search','status','linked','account']))
                                        <p class="text-xs text-gray-400 dark:text-zinc-500 mt-1">Tente ajustar os filtros ou <a href="{{ route('listings.index') }}" class="text-primary-500 hover:underline">limpar tudo</a></p>
                                        @else
                                        <p class="text-xs text-gray-400 dark:text-zinc-500 mt-1">Sincronize uma conta para importar anúncios</p>
                                        @endif
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ═══ Grid View ═════════════════════════════════════════════════════════ --}}
        <div x-show="view === 'grid'" x-cloak>
            @if($listings->isEmpty())
            <div class="card p-16 text-center">
                <div class="flex flex-col items-center gap-3">
                    <x-heroicon-o-megaphone class="w-12 h-12 text-gray-300 dark:text-zinc-600" />
                    <p class="text-sm font-medium text-gray-500 dark:text-zinc-400">Nenhum anúncio encontrado</p>
                </div>
            </div>
            @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                @foreach($listings as $listing)
                @php
                    $thumb     = $listing->meta['thumbnail'] ?? null;
                    $sold      = $listing->meta['sold_quantity'] ?? 0;
                    $isFree    = $listing->meta['is_free_shipping'] ?? false;
                    $isFulfill = $listing->meta['is_fulfillment'] ?? false;
                    $listType  = $listing->meta['listing_type_id'] ?? null;
                    $condition = $listing->meta['condition'] ?? null;
                @endphp
                <div class="card overflow-hidden flex flex-col group hover:shadow-md dark:hover:shadow-zinc-900 transition-shadow"
                     :class="selected.includes({{ $listing->id }}) ? 'ring-2 ring-primary-500' : ''">
                    {{-- Image --}}
                    <div class="relative aspect-square bg-gray-100 dark:bg-zinc-800 overflow-hidden">
                        @if($thumb)
                            <img src="{{ $thumb }}" alt="{{ $listing->title }}"
                                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" loading="lazy">
                        @else
                            <div class="w-full h-full flex items-center justify-center">
                                <x-heroicon-o-photo class="w-12 h-12 text-gray-300 dark:text-zinc-600" />
                            </div>
                        @endif

                        {{-- Top badges --}}
                        <div class="absolute top-2 left-2 flex flex-wrap gap-1">
                            @if($isFulfill)
                            <span class="text-[10px] px-1.5 py-0.5 rounded-full font-medium bg-purple-600 text-white shadow-sm">Full</span>
                            @endif
                            @if($isFree)
                            <span class="text-[10px] px-1.5 py-0.5 rounded-full font-medium bg-emerald-600 text-white shadow-sm">Frete Grátis</span>
                            @endif
                        </div>

                        {{-- Status badge --}}
                        <div class="absolute top-2 right-2">
                            <span class="text-[10px] px-2 py-0.5 rounded-full font-semibold shadow-sm
                                {{ match($listing->status) {
                                    'active'  => 'bg-emerald-500 text-white',
                                    'paused'  => 'bg-amber-500 text-white',
                                    'closed'  => 'bg-gray-500 text-white',
                                    'deleted' => 'bg-red-500 text-white',
                                    default   => 'bg-gray-500 text-white',
                                } }}">
                                {{ match($listing->status) {
                                    'active'  => 'Ativo',
                                    'paused'  => 'Pausado',
                                    'closed'  => 'Encerrado',
                                    'deleted' => 'Deletado',
                                    default   => $listing->status,
                                } }}
                            </span>
                        </div>

                        {{-- Checkbox --}}
                        <div class="absolute bottom-2 left-2 opacity-0 group-hover:opacity-100 transition-opacity"
                             :class="selected.includes({{ $listing->id }}) ? '!opacity-100' : ''">
                            <input type="checkbox"
                                class="rounded border-white bg-white/90 text-primary-500 focus:ring-primary-500 shadow-sm"
                                :checked="selected.includes({{ $listing->id }})"
                                @change="toggleOne({{ $listing->id }})">
                        </div>
                    </div>

                    {{-- Body --}}
                    <div class="p-3 flex flex-col flex-1">
                        {{-- Type tag --}}
                        @if($listType)
                        <div class="mb-1.5">
                            <span class="text-[10px] px-1.5 py-0.5 rounded font-medium
                                {{ match($listType) { 'gold_pro' => 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400', 'gold_special' => 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400', default => 'bg-gray-100 dark:bg-zinc-800 text-gray-600 dark:text-zinc-400' } }}">
                                {{ match($listType) { 'gold_pro' => 'Premium', 'gold_special' => 'Clássico', 'free' => 'Grátis', default => $listType } }}
                            </span>
                        </div>
                        @endif

                        {{-- Title --}}
                        <a href="{{ route('listings.show', $listing) }}"
                           class="text-sm font-medium text-gray-900 dark:text-white hover:text-primary-500 dark:hover:text-primary-400 line-clamp-2 leading-snug transition-colors flex-1">
                            {{ $listing->title }}
                        </a>

                        {{-- Price + Stock --}}
                        <div class="flex items-end justify-between mt-3 pt-2 border-t border-gray-100 dark:border-zinc-800">
                            <div>
                                <div class="text-lg font-bold font-mono text-gray-900 dark:text-white leading-none">
                                    R$ {{ number_format($listing->price, 2, ',', '.') }}
                                </div>
                                <div class="text-xs text-gray-400 dark:text-zinc-500 mt-0.5">
                                    {{ $listing->available_quantity ?? 0 }} em estoque
                                    @if($sold > 0) · {{ number_format($sold) }} vendidos @endif
                                </div>
                            </div>

                            {{-- Quick actions --}}
                            <div class="flex items-center gap-1">
                                <a href="{{ route('listings.show', $listing) }}#editar"
                                   class="p-1.5 rounded-md hover:bg-gray-100 dark:hover:bg-zinc-700 text-gray-400 hover:text-gray-600 dark:hover:text-zinc-200 transition-colors"
                                   title="Editar">
                                    <x-heroicon-o-pencil-square class="w-4 h-4" />
                                </a>
                                @if(in_array($listing->status, ['active', 'paused']))
                                <form method="POST" action="{{ route('listings.toggle-status', $listing) }}">
                                    @csrf
                                    <button type="submit"
                                        class="p-1.5 rounded-md hover:bg-gray-100 dark:hover:bg-zinc-700 transition-colors
                                               {{ $listing->status === 'active' ? 'text-amber-400 hover:text-amber-600' : 'text-emerald-400 hover:text-emerald-600' }}"
                                        title="{{ $listing->status === 'active' ? 'Pausar' : 'Ativar' }}"
                                        onclick="return confirm('{{ $listing->status === 'active' ? 'Pausar?' : 'Ativar?' }}')">
                                        @if($listing->status === 'active')
                                            <x-heroicon-o-pause class="w-4 h-4" />
                                        @else
                                            <x-heroicon-o-play class="w-4 h-4" />
                                        @endif
                                    </button>
                                </form>
                                @endif
                                @if($listing->meta['ml_permalink'] ?? null)
                                <a href="{{ $listing->meta['ml_permalink'] }}" target="_blank"
                                   class="p-1.5 rounded-md hover:bg-gray-100 dark:hover:bg-zinc-700 text-gray-400 hover:text-primary-500 transition-colors"
                                   title="Ver no ML">
                                    <x-heroicon-o-arrow-top-right-on-square class="w-4 h-4" />
                                </a>
                                @endif
                            </div>
                        </div>

                        {{-- Product link indicator --}}
                        @if(! $listing->product)
                        <a href="{{ route('listings.show', $listing) }}#vincular"
                           class="mt-2 text-xs text-amber-600 dark:text-amber-400 hover:text-amber-700 flex items-center gap-1 border-t border-gray-100 dark:border-zinc-800 pt-2">
                            <x-heroicon-o-exclamation-triangle class="w-3.5 h-3.5" />
                            Sem produto vinculado
                        </a>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>

        {{-- ═══ Pagination ════════════════════════════════════════════════════════ --}}
        <div class="flex items-center justify-between">
            <p class="text-sm text-gray-500 dark:text-zinc-400">
                Mostrando <span class="font-medium text-gray-700 dark:text-zinc-200">{{ $listings->firstItem() ?? 0 }}</span>–<span class="font-medium text-gray-700 dark:text-zinc-200">{{ $listings->lastItem() ?? 0 }}</span>
                de <span class="font-medium text-gray-700 dark:text-zinc-200">{{ number_format($listings->total()) }}</span> anúncios
            </p>
            {{ $listings->links() }}
        </div>

    </div>
</x-app-layout>
