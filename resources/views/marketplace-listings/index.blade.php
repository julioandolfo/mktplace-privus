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

    @php
        $advancedKeys    = ['price_min','price_max','stock','platform','company','condition','listing_type','free_shipping','fulfillment','kit','category','sort'];
        $advancedCount   = collect($advancedKeys)->filter(fn($k) => request($k) !== null && request($k) !== '')->count();
        $hasAdvanced     = $advancedCount > 0;
    @endphp

    <div class="space-y-5"
         x-data="{
             view: localStorage.getItem('listings_view') || 'list',
             setView(v) { this.view = v; localStorage.setItem('listings_view', v); },
             gridCols: parseInt(localStorage.getItem('listings_grid_cols') || '4'),
             setGridCols(n) { this.gridCols = n; localStorage.setItem('listings_grid_cols', n); },
             get gridClass() {
                 return {
                     2: 'grid-cols-1 sm:grid-cols-2',
                     3: 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3',
                     4: 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4',
                     5: 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5',
                 }[this.gridCols] || 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4';
             },
             showAdvanced: @json($hasAdvanced),
             advancedCount: @json($advancedCount),
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
            <form method="GET" id="filter-form">
                {{-- Preserve non-filter params --}}
                @foreach(request()->except(['search','account','status','linked','quality','price_min','price_max','stock','platform','company','condition','listing_type','free_shipping','fulfillment','kit','category','sort','page']) as $k => $v)
                    <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                @endforeach

                {{-- ── Row 1: main filters + toggles ─────────────────────────── --}}
                <div class="flex flex-wrap items-center gap-2">
                    <div class="relative flex-1 min-w-52">
                        <x-heroicon-o-magnifying-glass class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 dark:text-zinc-500 pointer-events-none" />
                        <input type="text" name="search" value="{{ request('search') }}"
                            placeholder="Buscar por título ou ID do anúncio..."
                            class="form-input pl-9 w-full">
                    </div>

                    <select name="status" class="form-input w-36">
                        <option value="">Todos status</option>
                        @foreach(['active' => 'Ativo', 'paused' => 'Pausado', 'closed' => 'Encerrado', 'deleted' => 'Deletado'] as $val => $label)
                            <option value="{{ $val }}" @selected(request('status') === $val)>{{ $label }}</option>
                        @endforeach
                    </select>

                    <select name="quality" class="form-input w-40" title="Filtrar por qualidade">
                        <option value="">Qualidade</option>
                        <option value="high"   @selected(request('quality') === 'high')>⭐ Profissional</option>
                        <option value="medium" @selected(request('quality') === 'medium')>🟡 Satisfatória</option>
                        <option value="low"    @selected(request('quality') === 'low')>🔴 Básica</option>
                        <option value="none"   @selected(request('quality') === 'none')>— Sem pontuação</option>
                    </select>

                    {{-- Advanced filter toggle --}}
                    <button type="button" @click="showAdvanced = !showAdvanced"
                        :class="showAdvanced || advancedCount > 0 ? 'bg-primary-500/10 text-primary-600 dark:text-primary-400 border-primary-500/40' : 'btn-ghost text-gray-500'"
                        class="btn-sm flex items-center gap-1.5 border rounded-lg px-3 py-1.5 transition-colors">
                        <x-heroicon-o-adjustments-horizontal class="w-4 h-4" />
                        <span>Avançado</span>
                        @if($advancedCount > 0)
                        <span class="inline-flex items-center justify-center w-4 h-4 text-[10px] font-bold bg-primary-500 text-white rounded-full">{{ $advancedCount }}</span>
                        @endif
                        <span :class="showAdvanced ? 'rotate-180' : ''" class="inline-flex transition-transform">
                            <x-heroicon-o-chevron-down class="w-3 h-3" />
                        </span>
                    </button>

                    <button type="submit" class="btn-primary btn-sm">
                        <x-heroicon-o-funnel class="w-4 h-4" />
                        Filtrar
                    </button>
                    @if(request()->hasAny(['search','account','status','linked','quality','price_min','price_max','stock','platform','company','condition','listing_type','free_shipping','fulfillment','kit','category','sort']))
                    <a href="{{ route('listings.index') }}" class="btn-ghost btn-sm text-gray-500">
                        <x-heroicon-o-x-mark class="w-4 h-4" />
                        Limpar
                    </a>
                    @endif

                    <div class="flex-1 hidden sm:block"></div>

                    {{-- Sort --}}
                    <select name="sort" class="form-input w-44" title="Ordenar por">
                        @foreach([
                            'newest'     => 'Mais recentes',
                            'oldest'     => 'Mais antigos',
                            'price_asc'  => 'Menor preço',
                            'price_desc' => 'Maior preço',
                            'stock_asc'  => 'Menor estoque',
                            'stock_desc' => 'Maior estoque',
                            'sold_desc'  => 'Mais vendidos',
                            'title_asc'  => 'Título A→Z',
                        ] as $val => $label)
                        <option value="{{ $val }}" @selected(request('sort', 'newest') === $val)>{{ $label }}</option>
                        @endforeach
                    </select>

                    {{-- Grid cols picker --}}
                    <div x-show="view === 'grid'" x-cloak
                         class="flex items-center gap-1 border border-gray-200 dark:border-zinc-700 rounded-lg p-1">
                        <span class="text-[10px] text-gray-400 dark:text-zinc-500 px-1 hidden sm:block">Cols:</span>
                    @foreach([2,3,4,5] as $cols)
                    <button type="button" @click="setGridCols(@json($cols))"
                        :class="gridCols === @json($cols) ? 'bg-primary-500 text-white' : 'text-gray-500 dark:text-zinc-400 hover:text-gray-700 dark:hover:text-zinc-200'"
                        class="w-7 h-7 rounded-md transition-colors text-xs font-semibold" title="{{ $cols }} por linha">
                        {{ $cols }}
                    </button>
                    @endforeach
                    </div>

                    {{-- View Toggle --}}
                    <div class="flex items-center gap-1 border border-gray-200 dark:border-zinc-700 rounded-lg p-1">
                        <button type="button" @click="setView('list')"
                            :class="view === 'list' ? 'bg-primary-500 text-white' : 'text-gray-500 dark:text-zinc-400 hover:text-gray-700 dark:hover:text-zinc-200'"
                            class="p-1.5 rounded-md transition-colors" title="Lista">
                            <x-heroicon-o-list-bullet class="w-4 h-4" />
                        </button>
                        <button type="button" @click="setView('grid')"
                            :class="view === 'grid' ? 'bg-primary-500 text-white' : 'text-gray-500 dark:text-zinc-400 hover:text-gray-700 dark:hover:text-zinc-200'"
                            class="p-1.5 rounded-md transition-colors" title="Grade">
                            <x-heroicon-o-squares-2x2 class="w-4 h-4" />
                        </button>
                    </div>
                </div>

                {{-- ── Advanced filters panel ──────────────────────────────────── --}}
                <div x-show="showAdvanced" x-cloak
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 -translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     class="mt-3 pt-3 border-t border-gray-100 dark:border-zinc-800">

                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-3">

                        {{-- Preço mínimo --}}
                        <div class="space-y-1">
                            <label class="text-[11px] font-semibold text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Preço mínimo</label>
                            <div class="relative">
                                <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-xs text-gray-400">R$</span>
                                <input type="number" name="price_min" value="{{ request('price_min') }}" min="0" step="0.01"
                                    placeholder="0,00" class="form-input pl-7 w-full text-sm">
                            </div>
                        </div>

                        {{-- Preço máximo --}}
                        <div class="space-y-1">
                            <label class="text-[11px] font-semibold text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Preço máximo</label>
                            <div class="relative">
                                <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-xs text-gray-400">R$</span>
                                <input type="number" name="price_max" value="{{ request('price_max') }}" min="0" step="0.01"
                                    placeholder="∞" class="form-input pl-7 w-full text-sm">
                            </div>
                        </div>

                        {{-- Estoque --}}
                        <div class="space-y-1">
                            <label class="text-[11px] font-semibold text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Estoque</label>
                            <select name="stock" class="form-input w-full text-sm">
                                <option value="">Todos</option>
                                <option value="zero" @selected(request('stock') === 'zero')>🔴 Esgotado (0)</option>
                                <option value="low"  @selected(request('stock') === 'low')>🟡 Baixo (1–5)</option>
                                <option value="ok"   @selected(request('stock') === 'ok')>🟢 Normal (&gt;5)</option>
                            </select>
                        </div>

                        {{-- Plataforma --}}
                        <div class="space-y-1">
                            <label class="text-[11px] font-semibold text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Plataforma</label>
                            <select name="platform" class="form-input w-full text-sm">
                                <option value="">Todas</option>
                                <option value="mercado_livre" @selected(request('platform') === 'mercado_livre')>Mercado Livre</option>
                                <option value="shopee"        @selected(request('platform') === 'shopee')>Shopee</option>
                                <option value="amazon"        @selected(request('platform') === 'amazon')>Amazon</option>
                                <option value="woocommerce"   @selected(request('platform') === 'woocommerce')>WooCommerce</option>
                                <option value="tiktok"        @selected(request('platform') === 'tiktok')>TikTok Shop</option>
                            </select>
                        </div>

                        {{-- Conta --}}
                        <div class="space-y-1">
                            <label class="text-[11px] font-semibold text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Conta</label>
                            <select name="account" class="form-input w-full text-sm">
                                <option value="">Todas as contas</option>
                                @foreach($accounts as $acc)
                                <option value="{{ $acc->id }}" @selected(request('account') == $acc->id)>
                                    {{ $acc->account_name }}
                                </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Empresa --}}
                        @if($companies->count() > 1)
                        <div class="space-y-1">
                            <label class="text-[11px] font-semibold text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Empresa</label>
                            <select name="company" class="form-input w-full text-sm">
                                <option value="">Todas</option>
                                @foreach($companies as $comp)
                                <option value="{{ $comp->id }}" @selected(request('company') == $comp->id)>{{ $comp->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        @endif

                        {{-- Condição --}}
                        <div class="space-y-1">
                            <label class="text-[11px] font-semibold text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Condição</label>
                            <select name="condition" class="form-input w-full text-sm">
                                <option value="">Todas</option>
                                <option value="new"  @selected(request('condition') === 'new')>Novo</option>
                                <option value="used" @selected(request('condition') === 'used')>Usado</option>
                            </select>
                        </div>

                        {{-- Tipo de anúncio --}}
                        <div class="space-y-1">
                            <label class="text-[11px] font-semibold text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Tipo</label>
                            <select name="listing_type" class="form-input w-full text-sm">
                                <option value="">Todos tipos</option>
                                <option value="gold_pro"     @selected(request('listing_type') === 'gold_pro')>Premium</option>
                                <option value="gold_special" @selected(request('listing_type') === 'gold_special')>Clássico</option>
                                <option value="free"         @selected(request('listing_type') === 'free')>Grátis</option>
                            </select>
                        </div>

                        {{-- Categoria --}}
                        @if($categoryOptions->isNotEmpty())
                        <div class="space-y-1 col-span-2">
                            <label class="text-[11px] font-semibold text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Categoria</label>
                            <select name="category" class="form-input w-full text-sm">
                                <option value="">Todas as categorias</option>
                                @foreach($categoryOptions as $cat)
                                <option value="{{ $cat }}" @selected(request('category') === $cat)>{{ $cat }}</option>
                                @endforeach
                            </select>
                        </div>
                        @endif

                        {{-- Produto vinculado --}}
                        <div class="space-y-1">
                            <label class="text-[11px] font-semibold text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Produto</label>
                            <select name="linked" class="form-input w-full text-sm">
                                <option value="">Com e sem</option>
                                <option value="1" @selected(request('linked') === '1')>Com produto</option>
                                <option value="0" @selected(request('linked') === '0')>Sem produto</option>
                            </select>
                        </div>

                        {{-- Frete Grátis --}}
                        <div class="space-y-1">
                            <label class="text-[11px] font-semibold text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Frete Grátis</label>
                            <select name="free_shipping" class="form-input w-full text-sm">
                                <option value="">Todos</option>
                                <option value="1" @selected(request('free_shipping') === '1')>Sim</option>
                            </select>
                        </div>

                        {{-- Fulfillment --}}
                        <div class="space-y-1">
                            <label class="text-[11px] font-semibold text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Fulfillment</label>
                            <select name="fulfillment" class="form-input w-full text-sm">
                                <option value="">Todos</option>
                                <option value="1" @selected(request('fulfillment') === '1')>Sim (Full)</option>
                            </select>
                        </div>

                        {{-- Kit --}}
                        <div class="space-y-1">
                            <label class="text-[11px] font-semibold text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Kit</label>
                            <select name="kit" class="form-input w-full text-sm">
                                <option value="">Todos</option>
                                <option value="1" @selected(request('kit') === '1')>Apenas kits</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-3 flex justify-end gap-2">
                        @if($advancedCount > 0)
                        <a href="{{ route('listings.index', request()->except(array_merge($advancedKeys, ['page']))) }}"
                           class="btn-ghost btn-sm text-gray-500">
                            <x-heroicon-o-x-mark class="w-3.5 h-3.5" />
                            Limpar filtros avançados
                        </a>
                        @endif
                        <button type="submit" class="btn-primary btn-sm">
                            <x-heroicon-o-funnel class="w-4 h-4" />
                            Aplicar filtros
                        </button>
                    </div>
                </div>
            </form>

            {{-- ── Active filter chips ──────────────────────────────────────── --}}
            @php
                $allActiveFilters = [
                    'search'        => request('search') ? '"' . request('search') . '"' : null,
                    'status'        => request('status') ? (['active'=>'Ativo','paused'=>'Pausado','closed'=>'Encerrado','deleted'=>'Deletado'][request('status')] ?? request('status')) : null,
                    'quality'       => request('quality') ? (['high'=>'⭐ Profissional','medium'=>'🟡 Satisfatória','low'=>'🔴 Básica','none'=>'Sem pontuação'][request('quality')] ?? null) : null,
                    'linked'        => request('linked') !== null && request('linked') !== '' ? (request('linked') === '1' ? 'Com produto' : 'Sem produto') : null,
                    'price_min'     => request('price_min') ? 'Preço ≥ R$ ' . number_format((float)request('price_min'), 2, ',', '.') : null,
                    'price_max'     => request('price_max') ? 'Preço ≤ R$ ' . number_format((float)request('price_max'), 2, ',', '.') : null,
                    'stock'         => request('stock') ? (['zero'=>'Esgotado','low'=>'Estoque baixo','ok'=>'Estoque ok'][request('stock')] ?? null) : null,
                    'platform'      => request('platform') ? (['mercado_livre'=>'Mercado Livre','shopee'=>'Shopee','amazon'=>'Amazon','woocommerce'=>'WooCommerce','tiktok'=>'TikTok'][request('platform')] ?? null) : null,
                    'account'       => request('account') ? ($accounts->firstWhere('id', request('account'))?->account_name ?? 'Conta #'.request('account')) : null,
                    'company'       => request('company') ? ($companies->firstWhere('id', request('company'))?->name ?? 'Empresa #'.request('company')) : null,
                    'condition'     => request('condition') ? (['new'=>'Novo','used'=>'Usado'][request('condition')] ?? null) : null,
                    'listing_type'  => request('listing_type') ? (['gold_pro'=>'Premium','gold_special'=>'Clássico','free'=>'Grátis'][request('listing_type')] ?? null) : null,
                    'free_shipping' => request('free_shipping') === '1' ? 'Frete Grátis' : null,
                    'fulfillment'   => request('fulfillment') === '1' ? 'Fulfillment' : null,
                    'kit'           => request('kit') === '1' ? 'Kit' : null,
                    'category'      => request('category') ? request('category') : null,
                    'sort'          => (request('sort') && request('sort') !== 'newest') ? (['oldest'=>'Mais antigos','price_asc'=>'Menor preço','price_desc'=>'Maior preço','stock_asc'=>'Menor estoque','stock_desc'=>'Maior estoque','sold_desc'=>'Mais vendidos','title_asc'=>'Título A→Z'][request('sort')] ?? null) : null,
                ];
                $activeChips = array_filter($allActiveFilters);
            @endphp
            @if(count($activeChips) > 0)
            <div class="flex flex-wrap gap-2 mt-2 pt-2 border-t border-gray-100 dark:border-zinc-800">
                @foreach($activeChips as $key => $label)
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-primary-500/10 text-primary-600 dark:text-primary-400 text-xs rounded-full">
                    {{ $label }}
                    <a href="{{ route('listings.index', request()->except([$key, 'page'])) }}"
                       class="hover:text-primary-800 dark:hover:text-primary-300 leading-none" title="Remover filtro">
                        <x-heroicon-o-x-mark class="w-3 h-3" />
                    </a>
                </span>
                @endforeach
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
                            $thumb        = $listing->meta['thumbnail'] ?? null;
                            $sold         = $listing->meta['sold_quantity'] ?? 0;
                            $isFree       = $listing->meta['is_free_shipping'] ?? false;
                            $isFulfill    = $listing->meta['is_fulfillment'] ?? false;
                            $listType     = $listing->meta['listing_type_id'] ?? null;
                            $condition    = $listing->meta['condition'] ?? null;
                            $qualScore    = $listing->meta['quality_score'] ?? null;
                            $qualColor    = $qualScore === null ? 'text-gray-300 dark:text-zinc-600' : ($qualScore >= 66 ? 'text-emerald-500' : ($qualScore >= 50 ? 'text-amber-500' : 'text-red-500'));
                            $qualBg       = $qualScore === null ? '#d1d5db' : ($qualScore >= 66 ? '#10b981' : ($qualScore >= 50 ? '#f59e0b' : '#ef4444'));
                            $qualDash     = $qualScore !== null ? round($qualScore * 100 / 100) : 0;
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
                                    {{-- Quality circle + Thumbnail wrapper --}}
                                    <div class="flex items-center gap-2 flex-shrink-0">
                                        {{-- Quality donut circle --}}
                                        <div class="relative w-10 h-10 flex-shrink-0" title="{{ $qualScore !== null ? 'Qualidade: '.$qualScore.'%' : 'Qualidade não calculada' }}">
                                            <svg viewBox="0 0 36 36" class="w-10 h-10 -rotate-90">
                                                <circle cx="18" cy="18" r="15.9" fill="none" stroke="#e5e7eb" stroke-width="3.5" class="dark:stroke-zinc-700"/>
                                                <circle cx="18" cy="18" r="15.9" fill="none"
                                                    stroke="{{ $qualBg }}"
                                                    stroke-width="3.5"
                                                    stroke-linecap="round"
                                                    stroke-dasharray="{{ $qualDash }} 100"/>
                                            </svg>
                                            <div class="absolute inset-0 flex items-center justify-center rotate-0">
                                                <span class="text-[9px] font-bold leading-none {{ $qualColor }}">
                                                    {{ $qualScore !== null ? $qualScore.'%' : '—' }}
                                                </span>
                                            </div>
                                        </div>
                                        {{-- Thumbnail --}}
                                        <div class="w-14 h-14 rounded-lg overflow-hidden bg-gray-100 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700">
                                            @if($thumb)
                                                <img src="{{ $thumb }}" alt="" class="w-full h-full object-cover" loading="lazy"
                                                     onerror="this.parentElement.innerHTML='<div class=\'w-full h-full flex items-center justify-center\'><svg class=\'w-6 h-6 text-gray-300\' fill=\'none\' viewBox=\'0 0 24 24\' stroke=\'currentColor\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'1.5\' d=\'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z\'/></svg></div>'">
                                            @else
                                                <div class="w-full h-full flex items-center justify-center">
                                                    <x-heroicon-o-photo class="w-6 h-6 text-gray-300 dark:text-zinc-600" />
                                                </div>
                                            @endif
                                        </div>
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
            <div class="grid gap-4" :class="gridClass">
                @foreach($listings as $listing)
                @php
                    $thumb        = $listing->meta['thumbnail'] ?? null;
                    $sold         = $listing->meta['sold_quantity'] ?? 0;
                    $isFree       = $listing->meta['is_free_shipping'] ?? false;
                    $isFulfill    = $listing->meta['is_fulfillment'] ?? false;
                    $listType     = $listing->meta['listing_type_id'] ?? null;
                    $condition    = $listing->meta['condition'] ?? null;
                    $qualScore    = $listing->meta['quality_score'] ?? null;
                    $qualBgGrid   = $qualScore === null ? '#9ca3af' : ($qualScore >= 66 ? '#10b981' : ($qualScore >= 50 ? '#f59e0b' : '#ef4444'));
                    $qualDashGrid = $qualScore !== null ? (int)$qualScore : 0;
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

                        {{-- Quality circle (bottom-left) --}}
                        <div class="absolute bottom-2 left-2"
                             title="{{ $qualScore !== null ? 'Qualidade: '.$qualScore.'%' : 'Qualidade não calculada' }}">
                            <div class="relative w-11 h-11 bg-white/90 dark:bg-zinc-900/80 backdrop-blur-sm rounded-full shadow-sm">
                                <svg viewBox="0 0 36 36" class="w-11 h-11 -rotate-90">
                                    <circle cx="18" cy="18" r="14" fill="none" stroke="#e5e7eb" stroke-width="3.5" class="dark:stroke-zinc-700"/>
                                    <circle cx="18" cy="18" r="14" fill="none"
                                        stroke="{{ $qualBgGrid }}"
                                        stroke-width="3.5"
                                        stroke-linecap="round"
                                        stroke-dasharray="{{ $qualDashGrid }} 100"/>
                                </svg>
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <span class="text-[9px] font-bold leading-none" style="color: {{ $qualBgGrid }}">
                                        {{ $qualScore !== null ? $qualScore.'%' : '—' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        {{-- Checkbox --}}
                        <div class="absolute bottom-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity"
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
