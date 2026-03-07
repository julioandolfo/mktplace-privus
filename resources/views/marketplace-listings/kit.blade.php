<x-app-layout>
    <x-slot name="header">Criar Kits</x-slot>
    <x-slot name="subtitle">Criação de kits a partir de "{{ $listing->title }}"</x-slot>
    <x-slot name="breadcrumbs">
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <a href="{{ route('listings.index') }}" class="hover:text-primary-500">Anúncios</a>
        </li>
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <a href="{{ route('listings.show', $listing) }}" class="hover:text-primary-500 truncate max-w-48">{{ $listing->title }}</a>
        </li>
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <span class="text-gray-700 dark:text-zinc-200">Criar Kits</span>
        </li>
    </x-slot>

    @php
        $thumb     = $listing->meta['thumbnail'] ?? null;
        $unitPrice = (float) ($liveData['price'] ?? $listing->price);
        $upId      = $liveData['user_product_id'] ?? null; // MLBU...
    @endphp

    <div class="max-w-4xl mx-auto space-y-6"
         x-data="{
            tab: 'multipack',
            discount: 0,
            qtyMin: 2, qtyMax: 6, qtyStep: 1,
            unitPrice: {{ $unitPrice }},
            get previewKits() {
                let kits = [];
                for (let q = parseInt(this.qtyMin); q <= parseInt(this.qtyMax); q += parseInt(this.qtyStep)) {
                    let p = this.unitPrice * q * (1 - this.discount / 100);
                    kits.push({ qty: q, price: p.toFixed(2) });
                }
                return kits;
            },
            // Combo state
            components: [],
            comboSearch: '',
            comboResults: [],
            comboLoading: false,
            mainProductId: null,
            addedProductIds: [],
            comboAutoPrice: false,
            comboDiscount: 10,
            comboManualPrice: '',
            async searchComponents() {
                if (this.comboSearch.length < 2) return;
                this.comboLoading = true;
                try {
                    const params = new URLSearchParams({ q: this.comboSearch });
                    if (this.mainProductId) params.set('main_product_id', this.mainProductId);
                    if (this.addedProductIds.length) params.set('added', JSON.stringify(this.addedProductIds));
                    const r = await fetch('{{ route('listings.kit-search-components', $listing) }}?' + params);
                    const data = await r.json();
                    this.comboResults = data.products || [];
                } catch(e) { this.comboResults = []; }
                this.comboLoading = false;
            },
            addComponent(product) {
                if (this.components.find(c => c.user_product_id === product.id)) return;
                this.components.push({ user_product_id: product.id, title: product.title, thumbnail: product.thumbnail?.secure_url, quantity: 1, type: product.type });
                this.addedProductIds.push(product.id);
                if (!this.mainProductId) this.mainProductId = product.id;
                this.comboSearch = '';
                this.comboResults = [];
            },
            removeComponent(idx) {
                const removed = this.components.splice(idx, 1)[0];
                this.addedProductIds = this.addedProductIds.filter(id => id !== removed.user_product_id);
                if (this.mainProductId === removed.user_product_id) this.mainProductId = this.components[0]?.user_product_id || null;
            },
         }">

        {{-- Base listing info --}}
        <div class="card p-4 flex items-center gap-4">
            <div class="w-16 h-16 rounded-lg overflow-hidden bg-gray-100 dark:bg-zinc-800 flex-shrink-0">
                @if($thumb)
                    <img src="{{ $thumb }}" alt="" class="w-full h-full object-cover">
                @else
                    <div class="w-full h-full flex items-center justify-center">
                        <x-heroicon-o-photo class="w-8 h-8 text-gray-300" />
                    </div>
                @endif
            </div>
            <div class="min-w-0 flex-1">
                <p class="font-semibold text-gray-900 dark:text-white truncate">{{ $listing->title }}</p>
                <p class="text-sm text-gray-500 dark:text-zinc-400 mt-0.5">
                    <span class="font-mono text-xs">{{ $listing->external_id }}</span>
                    · <strong>R$ {{ number_format($unitPrice, 2, ',', '.') }}</strong> por unidade
                    · {{ $listing->available_quantity ?? '—' }} em estoque
                </p>
            </div>
        </div>

        {{-- Tab selector --}}
        <div class="flex gap-1 bg-gray-100 dark:bg-zinc-800 rounded-xl p-1">
            <button type="button" @click="tab = 'multipack'"
                :class="tab === 'multipack' ? 'bg-white dark:bg-zinc-700 shadow text-gray-900 dark:text-white' : 'text-gray-500 dark:text-zinc-400'"
                class="flex-1 flex items-center justify-center gap-2 py-2.5 px-4 rounded-lg text-sm font-medium transition-all">
                <x-heroicon-o-squares-plus class="w-4 h-4" />
                Multipack (mesmo item × N)
            </button>
            <button type="button" @click="tab = 'combo'"
                :class="tab === 'combo' ? 'bg-white dark:bg-zinc-700 shadow text-gray-900 dark:text-white' : 'text-gray-500 dark:text-zinc-400'"
                class="flex-1 flex items-center justify-center gap-2 py-2.5 px-4 rounded-lg text-sm font-medium transition-all">
                <x-heroicon-o-puzzle-piece class="w-4 h-4" />
                Combo (2 produtos diferentes)
            </button>
        </div>

        {{-- ══ TAB 1: MULTIPACK ══════════════════════════════════════════════ --}}
        <div x-show="tab === 'multipack'" x-cloak>
            <form method="POST" action="{{ route('listings.store-multipack', $listing) }}">
                @csrf
                <div class="space-y-4">

                    <div class="card p-5 space-y-5">
                        <h3 class="text-sm font-semibold text-gray-700 dark:text-zinc-300">Configurar quantidades a criar</h3>

                        <div class="grid grid-cols-3 gap-4">
                            <div class="space-y-1">
                                <label class="text-xs font-semibold text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Quantidade mínima</label>
                                <input type="number" name="qty_min" x-model.number="qtyMin" min="2" max="10"
                                    class="form-input w-full" required>
                                <p class="text-[10px] text-gray-400">Ex: 2 → cria Kit 2x</p>
                            </div>
                            <div class="space-y-1">
                                <label class="text-xs font-semibold text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Quantidade máxima</label>
                                <input type="number" name="qty_max" x-model.number="qtyMax" min="2" max="10"
                                    class="form-input w-full" required>
                                <p class="text-[10px] text-gray-400">Ex: 10 → até Kit 10x</p>
                            </div>
                            <div class="space-y-1">
                                <label class="text-xs font-semibold text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Pular de</label>
                                <input type="number" name="qty_step" x-model.number="qtyStep" min="1" max="5"
                                    class="form-input w-full" required>
                                <p class="text-[10px] text-gray-400">Ex: 2 → 2, 4, 6, 8…</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-4">
                            <div class="space-y-1">
                                <label class="text-xs font-semibold text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Desconto por unidade no kit</label>
                                <div class="relative">
                                    <input type="number" name="discount_pct" x-model.number="discount" min="0" max="80" step="0.5"
                                        class="form-input w-full pr-8">
                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400">%</span>
                                </div>
                                <p class="text-[10px] text-gray-400">0% = preço cheio</p>
                            </div>
                            <div class="space-y-1">
                                <label class="text-xs font-semibold text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Tipo de anúncio</label>
                                <select name="listing_type" class="form-input w-full">
                                    <option value="gold_special">Clássico</option>
                                    <option value="gold_pro">Premium</option>
                                    <option value="free">Grátis</option>
                                </select>
                            </div>
                            <div class="space-y-1">
                                <label class="text-xs font-semibold text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Frete Grátis</label>
                                <select name="free_shipping" class="form-input w-full">
                                    <option value="0">Não</option>
                                    <option value="1">Sim</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    {{-- Preview --}}
                    <div class="card p-4">
                        <h3 class="text-xs font-semibold text-gray-500 dark:text-zinc-400 uppercase tracking-wider mb-3">Prévia dos kits que serão criados</h3>
                        <div class="space-y-2">
                            <template x-for="kit in previewKits" :key="kit.qty">
                                <div class="flex items-center justify-between py-2 px-3 rounded-lg bg-gray-50 dark:bg-zinc-800/50 text-sm">
                                    <div class="flex items-center gap-2">
                                        <span class="w-6 h-6 rounded-full bg-primary-500/10 text-primary-600 dark:text-primary-400 text-xs font-bold flex items-center justify-center" x-text="kit.qty + 'x'"></span>
                                        <span class="text-gray-700 dark:text-zinc-300">Kit <span x-text="kit.qty"></span>x {{ Str::limit($listing->title, 45) }}</span>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span class="text-xs text-gray-400 line-through" x-show="discount > 0" x-text="'R$ ' + (unitPrice * kit.qty).toFixed(2).replace('.', ',')"></span>
                                        <span class="font-bold font-mono text-gray-900 dark:text-white" x-text="'R$ ' + parseFloat(kit.price).toFixed(2).replace('.', ',')"></span>
                                        <span class="text-[10px] text-emerald-600 dark:text-emerald-400 font-medium" x-show="discount > 0" x-text="'-' + discount + '%'"></span>
                                    </div>
                                </div>
                            </template>
                            <p x-show="previewKits.length === 0" class="text-xs text-gray-400 dark:text-zinc-500 py-2 text-center">
                                Configure as quantidades acima para ver a prévia.
                            </p>
                        </div>
                        <p class="text-xs text-amber-600 dark:text-amber-400 mt-3 flex items-start gap-1.5">
                            <x-heroicon-o-information-circle class="w-4 h-4 flex-shrink-0 mt-0.5" />
                            <span>Cada kit é criado como um anúncio independente no ML. O estoque é calculado automaticamente como ⌊estoque_base ÷ quantidade⌋.</span>
                        </p>
                    </div>

                    <button type="submit" class="w-full btn-primary flex items-center justify-center gap-2 py-3"
                        onclick="return confirm('Criar ' + document.querySelectorAll('[x-text]').length + ' kit(s) no Mercado Livre?')">
                        <x-heroicon-o-rocket-launch class="w-5 h-5" />
                        Criar Kits Multipack no Mercado Livre
                    </button>
                </div>
            </form>
        </div>

        {{-- ══ TAB 2: COMBO ═══════════════════════════════════════════════════ --}}
        <div x-show="tab === 'combo'" x-cloak>

            @if(! $upId)
            <div class="card p-5">
                <div class="flex items-start gap-3 text-sm">
                    <div class="w-8 h-8 rounded-full bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center flex-shrink-0">
                        <x-heroicon-o-exclamation-triangle class="w-4 h-4 text-amber-500" />
                    </div>
                    <div class="space-y-2">
                        <p class="font-semibold text-gray-800 dark:text-white">Este anúncio não está no modelo User Products do ML</p>
                        <p class="text-gray-500 dark:text-zinc-400 text-xs leading-relaxed">
                            O Kit Combo via API requer que os produtos estejam no novo modelo <strong>User Products</strong> do Mercado Livre (identificados por <code class="font-mono bg-gray-100 dark:bg-zinc-800 px-1 rounded">MLBU...</code>).
                            Este anúncio usa o modelo antigo e não possui <code class="font-mono bg-gray-100 dark:bg-zinc-800 px-1 rounded">user_product_id</code>.
                        </p>
                        <p class="text-gray-500 dark:text-zinc-400 text-xs">
                            <strong>Alternativa:</strong> Use a aba <strong>Multipack</strong> para criar kits do mesmo produto (Kit 2x, 3x…), ou aguarde a migração automática do seu anúncio para o modelo User Products.
                        </p>
                        <a href="https://developers.mercadolivre.com.br/pt_br/user-products" target="_blank"
                           class="inline-flex items-center gap-1 text-xs text-primary-500 hover:underline">
                            <x-heroicon-o-arrow-top-right-on-square class="w-3.5 h-3.5" />
                            Saiba mais sobre User Products
                        </a>
                    </div>
                </div>
            </div>
            @else
            <form method="POST" action="{{ route('listings.store-combo', $listing) }}" x-ref="comboForm">
                @csrf

                <div class="space-y-4">
                    <div class="card p-5 space-y-4">
                        <h3 class="text-sm font-semibold text-gray-700 dark:text-zinc-300">Componentes do Kit</h3>

                        {{-- Current listing as first component (auto-added) --}}
                        <div class="flex items-center gap-3 py-2 px-3 rounded-lg bg-primary-500/5 border border-primary-500/20">
                            <div class="w-10 h-10 rounded-lg overflow-hidden bg-gray-100 dark:bg-zinc-800 flex-shrink-0">
                                @if($thumb)
                                    <img src="{{ $thumb }}" alt="" class="w-full h-full object-cover">
                                @endif
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-medium text-gray-800 dark:text-white truncate">{{ $listing->title }}</p>
                                <p class="text-[10px] text-primary-500">Anúncio base · UP: {{ $upId }}</p>
                            </div>
                            <input type="hidden" name="components[0][user_product_id]" value="{{ $upId }}">
                            <div class="flex items-center gap-2">
                                <label class="text-xs text-gray-500">Qtd:</label>
                                <input type="number" name="components[0][quantity]" value="1" min="1" max="10"
                                    class="form-input w-16 text-sm text-center">
                            </div>
                        </div>

                        {{-- Dynamic extra components --}}
                        <template x-for="(comp, idx) in components" :key="idx">
                            <div class="flex items-center gap-3 py-2 px-3 rounded-lg bg-gray-50 dark:bg-zinc-800/50 border border-gray-200 dark:border-zinc-700">
                                <div class="w-10 h-10 rounded-lg overflow-hidden bg-gray-100 dark:bg-zinc-800 flex-shrink-0">
                                    <img :src="comp.thumbnail" alt="" class="w-full h-full object-cover" x-show="comp.thumbnail">
                                    <div class="w-full h-full flex items-center justify-center" x-show="!comp.thumbnail">
                                        <svg class="w-5 h-5 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                    </div>
                                </div>
                                <input type="hidden" :name="'components[' + (idx + 1) + '][user_product_id]'" :value="comp.user_product_id">
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-medium text-gray-800 dark:text-white truncate" x-text="comp.title"></p>
                                    <p class="text-[10px] text-gray-400" x-text="comp.user_product_id"></p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <label class="text-xs text-gray-500">Qtd:</label>
                                    <input type="number" :name="'components[' + (idx + 1) + '][quantity]'" x-model.number="comp.quantity" min="1" max="10"
                                        class="form-input w-16 text-sm text-center">
                                </div>
                                <button type="button" @click="removeComponent(idx)"
                                    class="p-1 rounded hover:bg-red-100 dark:hover:bg-red-900/30 text-red-500 transition-colors">
                                    <x-heroicon-o-x-mark class="w-4 h-4" />
                                </button>
                            </div>
                        </template>

                        {{-- Search for more components --}}
                        <div x-show="components.length < 5" class="space-y-2">
                            <div class="relative">
                                <x-heroicon-o-magnifying-glass class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" />
                                <input type="text" x-model="comboSearch" @input.debounce.400ms="searchComponents()"
                                    placeholder="Buscar produto para adicionar ao kit..."
                                    class="form-input pl-9 w-full">
                                <div x-show="comboLoading" class="absolute right-3 top-1/2 -translate-y-1/2">
                                    <svg class="animate-spin w-4 h-4 text-primary-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                </div>
                            </div>

                            <div x-show="comboResults.length > 0" class="border border-gray-200 dark:border-zinc-700 rounded-lg overflow-hidden divide-y divide-gray-100 dark:divide-zinc-800">
                                <template x-for="prod in comboResults" :key="prod.id">
                                    <div class="flex items-center gap-3 px-3 py-2.5"
                                         :class="prod.type === 'available' ? 'hover:bg-gray-50 dark:hover:bg-zinc-800 cursor-pointer' : 'opacity-50 cursor-not-allowed bg-gray-50/50 dark:bg-zinc-900/50'"
                                         @click="prod.type === 'available' && addComponent(prod)">
                                        <img :src="prod.thumbnail?.secure_url" alt="" class="w-8 h-8 rounded object-cover flex-shrink-0">
                                        <div class="flex-1 min-w-0">
                                            <p class="text-xs font-medium text-gray-800 dark:text-white truncate" x-text="prod.title"></p>
                                            <p class="text-[10px] text-gray-400" x-text="prod.id"></p>
                                            <template x-if="prod.reasons?.length">
                                                <p class="text-[10px] text-red-500" x-text="prod.reasons[0]?.message"></p>
                                            </template>
                                        </div>
                                        <span x-show="prod.type === 'available'"
                                              class="text-[10px] px-2 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 font-medium">
                                            Elegível
                                        </span>
                                        <span x-show="prod.type !== 'available'"
                                              class="text-[10px] px-2 py-0.5 rounded-full bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 font-medium">
                                            Inelegível
                                        </span>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <p x-show="components.length >= 5" class="text-xs text-amber-600 dark:text-amber-400">
                            Máximo de 6 componentes atingido (1 base + 5 adicionais).
                        </p>
                    </div>

                    {{-- Kit name + price --}}
                    <div class="card p-5 space-y-4">
                        <h3 class="text-sm font-semibold text-gray-700 dark:text-zinc-300">Detalhes do Kit</h3>

                        <div class="space-y-1">
                            <label class="text-xs font-semibold text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Nome do Kit</label>
                            <input type="text" name="family_name" required maxlength="255"
                                value="{{ 'Kit ' . $listing->title }}"
                                class="form-input w-full">
                        </div>

                        <div class="space-y-1">
                            <label class="text-xs font-semibold text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Tipo de anúncio</label>
                            <select name="listing_type" class="form-input w-full">
                                <option value="gold_special">Clássico</option>
                                <option value="gold_pro">Premium</option>
                            </select>
                        </div>

                        <div class="space-y-2">
                            <label class="text-xs font-semibold text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Preço</label>
                            <div class="flex items-center gap-3">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="price_mode" value="manual" x-model="comboAutoPrice === false ? 'manual' : 'auto'"
                                        @change="comboAutoPrice = false" checked class="text-primary-500">
                                    <span class="text-sm text-gray-700 dark:text-zinc-300">Preço manual</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="price_mode" value="auto"
                                        @change="comboAutoPrice = true" class="text-primary-500">
                                    <span class="text-sm text-gray-700 dark:text-zinc-300">Automático (desconto %)</span>
                                </label>
                            </div>
                            <div x-show="!comboAutoPrice" class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-gray-400">R$</span>
                                <input type="number" name="price" x-model="comboManualPrice" min="1" step="0.01"
                                    class="form-input pl-8 w-full" placeholder="0,00">
                            </div>
                            <div x-show="comboAutoPrice" class="flex items-center gap-3">
                                <div class="relative flex-1">
                                    <input type="number" name="auto_discount" x-model.number="comboDiscount" min="0" max="80" step="0.5"
                                        class="form-input w-full pr-8" placeholder="10">
                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400">%</span>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-zinc-400">de desconto sobre a soma dos preços individuais</p>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-start gap-2 text-xs text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-3">
                        <x-heroicon-o-information-circle class="w-4 h-4 flex-shrink-0 mt-0.5" />
                        <div>
                            <p><strong>Kit Virtual ML:</strong> O estoque é calculado automaticamente com base na disponibilidade de cada componente. Você não precisa controlar estoque separado para o kit.</p>
                            <p class="mt-1">A configuração do kit é imutável após a criação — apenas as condições de venda (preço, tipo) podem ser alteradas depois.</p>
                        </div>
                    </div>

                    <button type="submit" class="w-full btn-primary flex items-center justify-center gap-2 py-3"
                        x-bind:disabled="components.length < 1"
                        :class="components.length < 1 ? 'opacity-50 cursor-not-allowed' : ''"
                        onclick="return confirm('Criar Kit Combo no Mercado Livre?')">
                        <x-heroicon-o-puzzle-piece class="w-5 h-5" />
                        Criar Kit Combo no Mercado Livre
                    </button>
                    <p x-show="components.length < 1" class="text-xs text-center text-gray-400">Adicione pelo menos 1 componente extra além do anúncio base.</p>
                </div>
            </form>
            @endif
        </div>

    </div>
</x-app-layout>
