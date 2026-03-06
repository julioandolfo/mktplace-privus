<x-app-layout>
    <x-slot name="header">{{ $listing->title }}</x-slot>
    <x-slot name="actions">
        @if($liveData && isset($liveData['permalink']))
        <a href="{{ $liveData['permalink'] }}" target="_blank" class="btn-secondary btn-sm">
            <x-heroicon-o-arrow-top-right-on-square class="w-4 h-4" />
            Ver no ML
        </a>
        @endif
    </x-slot>
    <x-slot name="breadcrumbs">
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <a href="{{ route('listings.index') }}" class="hover:text-gray-700 dark:hover:text-zinc-200">Anuncios</a>
        </li>
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <span class="text-gray-700 dark:text-zinc-200 truncate max-w-xs">{{ $listing->title }}</span>
        </li>
    </x-slot>

    @if($apiError)
    <div class="mb-4 flex items-center gap-2 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-lg px-4 py-3 text-sm text-amber-800 dark:text-amber-300">
        <x-heroicon-o-exclamation-triangle class="w-4 h-4 flex-shrink-0" />
        {{ $apiError }}
    </div>
    @endif

    @if(session('success'))
    <div class="mb-4 flex items-center gap-2 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-700 rounded-lg px-4 py-3 text-sm text-emerald-800 dark:text-emerald-300">
        <x-heroicon-o-check-circle class="w-4 h-4 flex-shrink-0" />
        {{ session('success') }}
    </div>
    @endif

    @if(session('error'))
    <div class="mb-4 flex items-center gap-2 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 rounded-lg px-4 py-3 text-sm text-red-800 dark:text-red-300">
        <x-heroicon-o-x-circle class="w-4 h-4 flex-shrink-0" />
        {{ session('error') }}
    </div>
    @endif

    @if(session('info'))
    <div class="mb-4 flex items-center gap-2 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg px-4 py-3 text-sm text-blue-800 dark:text-blue-300">
        <x-heroicon-o-information-circle class="w-4 h-4 flex-shrink-0" />
        {{ session('info') }}
    </div>
    @endif

    @php
        // Null-safe wrappers — $liveData can be null when API fails or has no credentials
        $live        = $liveData ?? [];
        $listingMeta = $listing->meta ?? [];

        $hasVariations    = !empty($live['variations']);
        $variations       = $live['variations'] ?? [];
        $isFulfillment    = in_array('fulfillment', $live['tags'] ?? []);
        $isHandlingLocked = $isFulfillment || !empty($listingMeta['handling_time_locked']);
        $lockedFields     = $listingMeta['locked_fields'] ?? [];
        $isPriceLocked    = in_array('price', $lockedFields);
        $isStockLocked    = in_array('available_quantity', $lockedFields);
        // Catalog items: title cannot be edited via API
        $isCatalogItem  = !empty($listingMeta['family_name'])
                       || !empty($listingMeta['catalog_product_id'])
                       || !empty($live['family_name'])
                       || !empty($live['catalog_product_id']);

        // Separate category attributes into editable vs read-only
        $currentAttrsIndexed = collect($live['attributes'] ?? [])->keyBy('id');

        // Only count truly missing: required + editable + without any value (name, id or values[])
        $requiredAttrs   = collect($categoryAttributes)->filter(fn($a) =>
            in_array('required', $a['tags'] ?? []) && ! in_array('read_only', $a['tags'] ?? [])
        );
        $missingRequired = $requiredAttrs->filter(function ($a) use ($currentAttrsIndexed) {
            $current = $currentAttrsIndexed->get($a['id'] ?? '');
            if (! $current) return true;
            return empty($current['value_name'])
                && empty($current['value_id'])
                && empty($current['values']);
        });
    @endphp

    {{-- Fulfillment warning --}}
    @if($isFulfillment)
    <div class="mb-4 flex items-center gap-2 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded-lg px-4 py-2 text-sm text-yellow-800 dark:text-yellow-300">
        <x-heroicon-o-cube class="w-4 h-4 flex-shrink-0" />
        Anúncio <strong>Fulfillment</strong> — Estoque gerenciado pelo Mercado Livre. Alterações de estoque podem ser ignoradas.
    </div>
    @endif

    {{-- Missing required attributes warning --}}
    @if($missingRequired->isNotEmpty())
    <div class="mb-4 flex items-start gap-2 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-lg px-4 py-3 text-sm text-amber-800 dark:text-amber-300">
        <x-heroicon-o-exclamation-triangle class="w-4 h-4 flex-shrink-0 mt-0.5" />
        <div>
            <strong>{{ $missingRequired->count() }} atributo(s) obrigatório(s) não preenchido(s)</strong>
            — isso pode afetar a visibilidade e qualidade do anúncio.
            <a href="#atributos" class="underline ml-1">Preencher agora</a>
        </div>
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- ═══ Main Column ══════════════════════════════════════════════════ --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Desempenho --}}
            <x-ui.card title="Desempenho de Vendas">
                <div class="grid grid-cols-3 gap-4 mb-6">
                    <div class="text-center p-3 bg-gray-50 dark:bg-zinc-800/60 rounded-lg">
                        <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($totalQty) }}</div>
                        <div class="text-xs text-gray-500 dark:text-zinc-400 mt-0.5">Unidades Vendidas</div>
                    </div>
                    <div class="text-center p-3 bg-gray-50 dark:bg-zinc-800/60 rounded-lg">
                        <div class="text-2xl font-bold text-gray-900 dark:text-white font-mono">
                            R$ {{ number_format($totalRevenue, 0, ',', '.') }}
                        </div>
                        <div class="text-xs text-gray-500 dark:text-zinc-400 mt-0.5">Receita (12 meses)</div>
                    </div>
                    <div class="text-center p-3 bg-gray-50 dark:bg-zinc-800/60 rounded-lg">
                        <div class="text-2xl font-bold text-gray-900 dark:text-white font-mono">
                            R$ {{ number_format($avgTicket, 2, ',', '.') }}
                        </div>
                        <div class="text-xs text-gray-500 dark:text-zinc-400 mt-0.5">Ticket Médio</div>
                    </div>
                </div>

                @if($salesStats->count() > 0)
                @php $maxRevenue = $salesStats->max('revenue') ?: 1; @endphp
                <div>
                    <div class="text-xs font-medium text-gray-500 dark:text-zinc-400 mb-3">Receita por mês (R$)</div>
                    <div class="flex items-end gap-1.5 h-28">
                        @foreach($salesStats as $stat)
                        @php
                            $pct   = round(($stat->revenue / $maxRevenue) * 100);
                            $month = \Carbon\Carbon::createFromFormat('Y-m', $stat->month)->translatedFormat('M/y');
                        @endphp
                        <div class="flex-1 flex flex-col items-center gap-1 min-w-0 group"
                             title="{{ $month }}: R$ {{ number_format($stat->revenue, 2, ',', '.') }} ({{ $stat->qty }} venda{{ $stat->qty != 1 ? 's' : '' }})">
                            <div class="text-[10px] text-gray-400 dark:text-zinc-500 opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">
                                R$ {{ number_format($stat->revenue, 0, ',', '.') }}
                            </div>
                            <div class="w-full bg-primary-500/80 hover:bg-primary-500 rounded-t transition-colors cursor-default"
                                 style="height: {{ max(4, $pct) }}%"></div>
                            <div class="text-[9px] text-gray-400 dark:text-zinc-500 truncate w-full text-center">{{ $month }}</div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @else
                <p class="text-sm text-center text-gray-400 dark:text-zinc-500 py-4">
                    Nenhuma venda registrada nos últimos 12 meses.
                </p>
                @endif
            </x-ui.card>

            {{-- Editar Anuncio --}}
            <div id="editar">
            <x-ui.card title="Editar Anuncio">
                <form method="POST" action="{{ route('listings.update', $listing) }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <label class="form-label">
                            Titulo
                            @if(!$isCatalogItem)
                                <span class="text-gray-400 text-xs font-normal">(max 60 caracteres)</span>
                            @endif
                        </label>
                        @if($isCatalogItem)
                            {{-- Catalog items: title is managed by ML, cannot be changed --}}
                            <input type="text"
                                value="{{ $live['title'] ?? $listing->title }}"
                                class="form-input bg-gray-100 dark:bg-zinc-800/60 text-gray-500 dark:text-zinc-400 cursor-not-allowed"
                                disabled>
                            <input type="hidden" name="title" value="{{ $live['title'] ?? $listing->title }}">
                            <p class="text-xs text-amber-600 dark:text-amber-400 mt-1 flex items-center gap-1">
                                <x-heroicon-o-lock-closed class="w-3 h-3" />
                                Título bloqueado — este anúncio está vinculado ao catálogo do Mercado Livre.
                            </p>
                        @else
                            <input type="text" name="title" maxlength="60"
                                value="{{ old('title', $live['title'] ?? $listing->title) }}"
                                class="form-input @error('title') border-red-500 @enderror" required>
                            @error('title') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        @endif
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="form-label">Preço (R$)</label>
                            @if($isPriceLocked)
                                <input type="number" value="{{ $live['price'] ?? $listing->price }}"
                                    class="form-input bg-gray-100 dark:bg-zinc-800/60 text-gray-500 dark:text-zinc-400 cursor-not-allowed" disabled>
                                <input type="hidden" name="price" value="{{ $live['price'] ?? $listing->price }}">
                                <p class="text-xs text-amber-600 dark:text-amber-400 mt-1 flex items-center gap-1">
                                    <x-heroicon-o-lock-closed class="w-3 h-3" />
                                    Preço gerenciado pelo ML (variações ou catálogo).
                                </p>
                            @else
                                <input type="number" name="price" step="0.01" min="0"
                                    value="{{ old('price', $live['price'] ?? $listing->price) }}"
                                    class="form-input @error('price') border-red-500 @enderror" required>
                                @error('price') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                            @endif
                        </div>
                        <div>
                            <label class="form-label">Estoque Disponível</label>
                            @if($isStockLocked)
                                <input type="number" value="{{ $live['available_quantity'] ?? $listing->available_quantity }}"
                                    class="form-input bg-gray-100 dark:bg-zinc-800/60 text-gray-500 dark:text-zinc-400 cursor-not-allowed" disabled>
                                <input type="hidden" name="available_quantity" value="{{ $live['available_quantity'] ?? $listing->available_quantity }}">
                                <p class="text-xs text-amber-600 dark:text-amber-400 mt-1 flex items-center gap-1">
                                    <x-heroicon-o-lock-closed class="w-3 h-3" />
                                    Estoque gerenciado pelas variações.
                                </p>
                            @else
                                <input type="number" name="available_quantity" min="0"
                                    value="{{ old('available_quantity', $live['available_quantity'] ?? $listing->available_quantity) }}"
                                    class="form-input @error('available_quantity') border-red-500 @enderror" required
                                    @if($isFulfillment) disabled title="Gerenciado pelo Fulfillment ML" @endif>
                                @error('available_quantity') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                            @endif
                        </div>
                    </div>

                    <div>
                        <label class="form-label">Prazo de Disponibilidade</label>
                        @php $currentHandling = old('handling_time', $live['shipping']['handling_time'] ?? 0); @endphp
                        @if($isHandlingLocked)
                            <select class="form-input bg-gray-100 dark:bg-zinc-800/60 text-gray-500 dark:text-zinc-400 cursor-not-allowed" disabled>
                                <option>{{ (int)$currentHandling === 0 ? 'Mesmo dia' : ((int)$currentHandling . ' dia(s) útil(is)') }}</option>
                            </select>
                            <input type="hidden" name="handling_time" value="{{ (int)$currentHandling }}">
                            <p class="text-xs text-amber-600 dark:text-amber-400 mt-1 flex items-center gap-1">
                                <x-heroicon-o-lock-closed class="w-3 h-3" />
                                Prazo gerenciado pelo Mercado Livre — não pode ser alterado.
                            </p>
                        @else
                            <select name="handling_time" class="form-input">
                                <option value="0" @selected((int)$currentHandling === 0)>Mesmo dia</option>
                                @for($d = 1; $d <= 20; $d++)
                                    <option value="{{ $d }}" @selected((int)$currentHandling === $d)>
                                        {{ $d === 1 ? '1 dia útil' : "{$d} dias úteis" }}
                                    </option>
                                @endfor
                            </select>
                        @endif
                    </div>

                    {{-- Shipping Dimensions --}}
                    @php
                        $dims = $live['shipping']['dimensions'] ?? null;
                    @endphp
                    <div>
                        <label class="form-label mb-2 block">Dimensões para Frete (Mercado Envios)</label>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <div>
                                <label class="text-xs text-gray-500 dark:text-zinc-400 mb-1 block">Largura (cm)</label>
                                <input type="number" name="shipping_width" step="0.1" min="0"
                                    value="{{ old('shipping_width', $dims['width'] ?? '') }}"
                                    class="form-input text-sm" placeholder="0">
                            </div>
                            <div>
                                <label class="text-xs text-gray-500 dark:text-zinc-400 mb-1 block">Altura (cm)</label>
                                <input type="number" name="shipping_height" step="0.1" min="0"
                                    value="{{ old('shipping_height', $dims['height'] ?? '') }}"
                                    class="form-input text-sm" placeholder="0">
                            </div>
                            <div>
                                <label class="text-xs text-gray-500 dark:text-zinc-400 mb-1 block">Comprimento (cm)</label>
                                <input type="number" name="shipping_length" step="0.1" min="0"
                                    value="{{ old('shipping_length', $dims['length'] ?? '') }}"
                                    class="form-input text-sm" placeholder="0">
                            </div>
                            <div>
                                <label class="text-xs text-gray-500 dark:text-zinc-400 mb-1 block">Peso (g)</label>
                                <input type="number" name="shipping_weight" step="1" min="0"
                                    value="{{ old('shipping_weight', $dims['weight'] ?? '') }}"
                                    class="form-input text-sm" placeholder="0">
                            </div>
                        </div>
                        <p class="text-xs text-gray-400 dark:text-zinc-500 mt-1">
                            Necessário para calcular o frete do Mercado Envios corretamente.
                        </p>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="btn-primary">
                            <x-heroicon-o-cloud-arrow-up class="w-4 h-4" />
                            Salvar no Mercado Livre
                        </button>
                    </div>
                </form>
            </x-ui.card>
            </div>

            {{-- Atributos por Categoria --}}
            @if(!empty($live['attributes']) || !empty($categoryAttributes))
            <div id="atributos">
            <x-ui.card title="Atributos do Anuncio">
                <form method="POST" action="{{ route('listings.update', $listing) }}" class="space-y-3">
                    @csrf
                    @method('PUT')
                    {{-- Hidden fields to keep main fields --}}
                    <input type="hidden" name="title" value="{{ $live['title'] ?? $listing->title }}">
                    <input type="hidden" name="price" value="{{ $live['price'] ?? $listing->price }}">
                    <input type="hidden" name="available_quantity" value="{{ $live['available_quantity'] ?? $listing->available_quantity }}">
                    <input type="hidden" name="handling_time" value="{{ $live['shipping']['handling_time'] ?? 0 }}">

                    @php
                        $currentAttrsMap = collect($live['attributes'] ?? [])->keyBy('id');
                        // Prefer category attributes schema; fall back to live item attributes
                        $allAttrs = collect($categoryAttributes)->isNotEmpty()
                            ? collect($categoryAttributes)
                            : collect($live['attributes'] ?? []);
                    @endphp

                    <div class="space-y-3">
                        @foreach($allAttrs as $attr)
                        @php
                            $attrId        = $attr['id'] ?? null;
                            $attrName      = $attr['name'] ?? $attrId;
                            $currentVal    = $currentAttrsMap->get($attrId) ?? [];
                            // Resolve current display value: prefer value_name, else first from values[], else value_id
                            $currentValue  = $currentVal['value_name']
                                ?? ($currentVal['values'][0]['name'] ?? null)
                                ?? (!empty($currentVal['value_id']) ? (string)$currentVal['value_id'] : '');
                            $isRequired    = in_array('required', $attr['tags'] ?? []);
                            $isMissing     = $isRequired && empty($currentValue);
                            $valueType     = $attr['value_type'] ?? 'string';
                            $allowedValues = $attr['allowed_values'] ?? [];
                            $readOnly      = in_array('read_only', $attr['tags'] ?? []);
                            // Detect boolean: value_type=boolean OR allowed_values exactly Sim/Não
                            $isBoolean     = $valueType === 'boolean'
                                || (count($allowedValues) === 2
                                    && collect($allowedValues)->pluck('name')->sort()->values()->all() === ['Não', 'Sim']);
                        @endphp
                        @if($attrId && !$readOnly)
                        <div class="flex items-center gap-3 @if($isMissing) bg-amber-50/40 dark:bg-amber-900/10 rounded-lg px-2 py-1 -mx-2 @endif">
                            <div class="w-44 flex-shrink-0">
                                <span class="text-sm text-gray-600 dark:text-zinc-400">
                                    {{ $attrName }}
                                    @if($isMissing)
                                        <span class="text-red-400 text-xs ml-0.5" title="Obrigatório não preenchido">*</span>
                                    @elseif($isRequired)
                                        <span class="text-amber-400 text-xs ml-0.5">*</span>
                                    @endif
                                </span>
                            </div>

                            @if($isBoolean)
                                {{-- Boolean: always render as Sim/Não select --}}
                                <select name="attributes[{{ $attrId }}]" class="form-input flex-1 text-sm py-1.5">
                                    <option value="">Selecione...</option>
                                    <option value="Sim" @selected(in_array(strtolower((string) $currentValue), ['sim', 'yes', '1', 'true']))>Sim</option>
                                    <option value="Não" @selected(in_array(strtolower((string) $currentValue), ['não', 'nao', 'no', '0', 'false']))>Não</option>
                                </select>
                            @elseif(!empty($allowedValues))
                                {{-- List of allowed values: render as select --}}
                                <select name="attributes[{{ $attrId }}]" class="form-input flex-1 text-sm py-1.5">
                                    <option value="">Selecione...</option>
                                    @foreach($allowedValues as $val)
                                    <option value="{{ $val['name'] ?? $val['id'] }}"
                                        @selected($currentValue === ($val['name'] ?? $val['id']))>
                                        {{ $val['name'] ?? $val['id'] }}
                                    </option>
                                    @endforeach
                                </select>
                            @elseif($valueType === 'number' || $valueType === 'number_unit')
                                <input type="number" name="attributes[{{ $attrId }}]"
                                    value="{{ old('attributes.'.$attrId, $currentValue) }}"
                                    class="form-input flex-1 text-sm py-1.5"
                                    placeholder="{{ $currentValue ?: '0' }}">
                            @else
                                <input type="text" name="attributes[{{ $attrId }}]"
                                    value="{{ old('attributes.'.$attrId, $currentValue) }}"
                                    class="form-input flex-1 text-sm py-1.5"
                                    placeholder="{{ $currentValue ?: 'Não informado' }}">
                            @endif
                        </div>
                        @endif
                        @endforeach
                    </div>

                    <div class="flex justify-end pt-2">
                        <button type="submit" class="btn-primary btn-sm">
                            <x-heroicon-o-cloud-arrow-up class="w-4 h-4" />
                            Salvar Atributos
                        </button>
                    </div>
                </form>
            </x-ui.card>
            </div>
            @endif

            {{-- Variações --}}
            @if($hasVariations)
            <x-ui.card title="Variações ({{ count($variations) }})">
                @php
                    $allPictures = $live['pictures'] ?? [];
                @endphp
                <div class="space-y-4">
                    @foreach($variations as $vi => $variation)
                    @php
                        $varAttrs = collect($variation['attribute_combinations'] ?? [])
                            ->map(fn($a) => ($a['name'] ?? '') . ': ' . ($a['value_name'] ?? ''))
                            ->join(' · ');
                        $varPicIds   = $variation['picture_ids'] ?? [];
                        $varPictures = collect($allPictures)->whereIn('id', $varPicIds);
                        $varSku      = $variation['seller_custom_field'] ?? '';
                        $varQty      = $variation['available_quantity'] ?? 0;
                        $varPrice    = $variation['price'] ?? null;
                    @endphp
                    <div class="border border-gray-200 dark:border-zinc-700 rounded-lg overflow-hidden"
                         x-data="{ open: false }">
                        {{-- Header --}}
                        <div class="flex items-center gap-3 px-4 py-3 cursor-pointer hover:bg-gray-50/60 dark:hover:bg-zinc-800/40 transition-colors"
                             @click="open = !open">
                            {{-- Thumbnail --}}
                            <div class="w-10 h-10 rounded-md overflow-hidden bg-gray-100 dark:bg-zinc-800 flex-shrink-0 border border-gray-200 dark:border-zinc-700">
                                @if($varPictures->isNotEmpty())
                                    <img src="{{ $varPictures->first()['url'] ?? '' }}" class="w-full h-full object-cover" loading="lazy">
                                @else
                                    <div class="w-full h-full flex items-center justify-center">
                                        <x-heroicon-o-photo class="w-4 h-4 text-gray-300 dark:text-zinc-600" />
                                    </div>
                                @endif
                            </div>

                            {{-- Info --}}
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-sm text-gray-900 dark:text-white truncate">
                                    {{ $varAttrs ?: "Variação #{$variation['id']}" }}
                                </p>
                                <div class="flex flex-wrap items-center gap-x-3 gap-y-0.5 mt-0.5 text-xs text-gray-500 dark:text-zinc-400">
                                    @if($varPrice !== null)
                                    <span class="font-mono">R$ {{ number_format($varPrice, 2, ',', '.') }}</span>
                                    @endif
                                    <span class="{{ $varQty <= 3 ? 'text-red-500 font-semibold' : '' }}">
                                        Est: {{ $varQty }}
                                    </span>
                                    @if($varSku)
                                    <span class="font-mono text-gray-400 dark:text-zinc-500">SKU: {{ $varSku }}</span>
                                    @endif
                                    <span class="text-gray-400 dark:text-zinc-600">{{ count($varPicIds) }} foto(s)</span>
                                </div>
                            </div>

                            {{-- Expand icon --}}
                            <x-heroicon-o-chevron-down class="w-4 h-4 text-gray-400 dark:text-zinc-500 transition-transform flex-shrink-0"
                                                       ::class="open ? 'rotate-180' : ''" />
                        </div>

                        {{-- Expanded edit form --}}
                        <div x-show="open" x-collapse x-cloak
                             class="border-t border-gray-100 dark:border-zinc-800 px-4 py-4 bg-gray-50/30 dark:bg-zinc-800/20">

                            <form method="POST" action="{{ route('listings.update-variation', [$listing, $variation['id']]) }}">
                                @csrf
                                @method('PUT')

                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
                                    <div>
                                        <label class="text-xs text-gray-500 dark:text-zinc-400 mb-1 block">Preço (R$)</label>
                                        <input type="number" name="price" step="0.01" min="0"
                                            value="{{ $varPrice ?? '' }}"
                                            class="form-input text-sm" placeholder="Igual ao anúncio">
                                    </div>
                                    <div>
                                        <label class="text-xs text-gray-500 dark:text-zinc-400 mb-1 block">Estoque *</label>
                                        <input type="number" name="available_quantity" min="0"
                                            value="{{ $varQty }}"
                                            class="form-input text-sm" required>
                                    </div>
                                    <div class="col-span-2">
                                        <label class="text-xs text-gray-500 dark:text-zinc-400 mb-1 block">SKU do Vendedor</label>
                                        <input type="text" name="seller_custom_field" maxlength="100"
                                            value="{{ $varSku }}"
                                            class="form-input text-sm" placeholder="Código interno">
                                    </div>
                                </div>

                                {{-- Pictures linked to this variation --}}
                                @if(!empty($allPictures))
                                <div class="mb-4">
                                    <label class="text-xs text-gray-500 dark:text-zinc-400 mb-2 block">
                                        Imagens desta variação
                                        <span class="text-gray-400 dark:text-zinc-600">(selecione quais fotos pertencem a esta variação)</span>
                                    </label>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach($allPictures as $pic)
                                        @php $isLinked = in_array($pic['id'], $varPicIds); @endphp
                                        <label class="relative cursor-pointer group">
                                            <input type="checkbox" name="picture_ids[]" value="{{ $pic['id'] }}"
                                                {{ $isLinked ? 'checked' : '' }}
                                                class="sr-only peer">
                                            <div class="w-14 h-14 rounded-md overflow-hidden border-2 transition-colors
                                                        peer-checked:border-primary-500 peer-checked:ring-2 peer-checked:ring-primary-500/30
                                                        border-gray-200 dark:border-zinc-700 hover:border-gray-400 dark:hover:border-zinc-500">
                                                <img src="{{ $pic['url'] }}" class="w-full h-full object-cover" loading="lazy">
                                            </div>
                                            <div class="absolute -top-1 -right-1 w-4 h-4 rounded-full bg-primary-500 text-white items-center justify-center text-[8px] hidden peer-checked:flex shadow-sm">
                                                <x-heroicon-s-check class="w-3 h-3" />
                                            </div>
                                        </label>
                                        @endforeach
                                    </div>
                                </div>
                                @endif

                                {{-- Attributes (read-only display) --}}
                                @if(!empty($variation['attribute_combinations']))
                                <div class="mb-4 flex flex-wrap gap-2">
                                    @foreach($variation['attribute_combinations'] as $combo)
                                    <span class="text-[11px] px-2 py-1 rounded-md bg-gray-100 dark:bg-zinc-800 text-gray-600 dark:text-zinc-400">
                                        {{ $combo['name'] ?? $combo['id'] }}: <strong>{{ $combo['value_name'] ?? '—' }}</strong>
                                    </span>
                                    @endforeach
                                </div>
                                @endif

                                <div class="flex items-center justify-between">
                                    {{-- Delete variation --}}
                                    @if(count($variations) > 1)
                                    <form method="POST" action="{{ route('listings.delete-variation', [$listing, $variation['id']]) }}"
                                          class="inline" onsubmit="return confirm('Remover esta variação permanentemente do ML?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-ghost btn-sm text-xs text-red-500 hover:text-red-600">
                                            <x-heroicon-o-trash class="w-3.5 h-3.5" />
                                            Remover variação
                                        </button>
                                    </form>
                                    @else
                                    <span></span>
                                    @endif

                                    <button type="submit" class="btn-primary btn-sm text-xs">
                                        <x-heroicon-o-cloud-arrow-up class="w-3.5 h-3.5" />
                                        Salvar variação
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    @endforeach
                </div>
            </x-ui.card>
            @endif

            {{-- Imagens --}}
            @if($liveData !== null)
            <x-ui.card title="Imagens">
                @if(!empty($liveData['pictures']))
                <div class="grid grid-cols-4 sm:grid-cols-6 gap-2 mb-4">
                    @foreach($liveData['pictures'] as $pic)
                    <div class="group relative aspect-square rounded-md overflow-hidden border border-gray-200 dark:border-zinc-700">
                        <a href="{{ $pic['url'] }}" target="_blank">
                            <img src="{{ $pic['url'] }}" alt="Imagem do anuncio"
                                 class="w-full h-full object-cover" loading="lazy">
                        </a>
                        <form method="POST"
                              action="{{ route('listings.remove-picture', [$listing, $pic['id']]) }}"
                              class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                class="text-white hover:text-red-400 transition-colors"
                                onclick="return confirm('Remover esta imagem do anúncio no ML?')"
                                title="Remover imagem">
                                <x-heroicon-o-trash class="w-5 h-5" />
                            </button>
                        </form>
                    </div>
                    @endforeach
                </div>
                @else
                <p class="text-sm text-gray-400 dark:text-zinc-500 mb-4">Nenhuma imagem cadastrada.</p>
                @endif

                {{-- Add image: URL or file upload --}}
                <form method="POST" action="{{ route('listings.add-picture', $listing) }}"
                      enctype="multipart/form-data"
                      class="border-t border-gray-100 dark:border-zinc-800 pt-4 space-y-3"
                      x-data="{ mode: 'url' }">
                    @csrf

                    <div class="flex gap-2 mb-2">
                        <button type="button" @click="mode='url'"
                            :class="mode==='url' ? 'btn-primary btn-sm' : 'btn-secondary btn-sm'">
                            <x-heroicon-o-link class="w-3.5 h-3.5" />
                            Por URL
                        </button>
                        <button type="button" @click="mode='file'"
                            :class="mode==='file' ? 'btn-primary btn-sm' : 'btn-secondary btn-sm'">
                            <x-heroicon-o-arrow-up-tray class="w-3.5 h-3.5" />
                            Upload
                        </button>
                    </div>

                    <div x-show="mode==='url'" class="flex gap-2">
                        <input type="url" name="picture_url"
                            placeholder="URL da nova imagem (https://...)"
                            class="form-input flex-1 text-sm">
                        <button type="submit" class="btn-secondary btn-sm flex-shrink-0">
                            <x-heroicon-o-plus class="w-4 h-4" />
                            Adicionar
                        </button>
                    </div>

                    <div x-show="mode==='file'" class="space-y-2">
                        <input type="file" name="picture_file" accept="image/jpeg,image/png"
                            class="form-input text-sm w-full">
                        <p class="text-xs text-gray-400 dark:text-zinc-500">
                            JPG ou PNG, máx. 10MB. Mínimo 500×500px.
                        </p>
                        <button type="submit" class="btn-primary btn-sm">
                            <x-heroicon-o-cloud-arrow-up class="w-4 h-4" />
                            Enviar para ML
                        </button>
                    </div>
                </form>
            </x-ui.card>
            @endif

            {{-- Descricao --}}
            <x-ui.card title="Descricao">
                <form method="POST" action="{{ route('listings.update-description', $listing) }}" class="space-y-3">
                    @csrf
                    <textarea name="description" rows="8"
                        class="form-input w-full text-sm leading-relaxed resize-y @error('description') border-red-500 @enderror"
                        placeholder="Descrição do anúncio...">{{ old('description', $description['plain_text'] ?? '') }}</textarea>
                    @error('description') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
                    <div class="flex justify-end">
                        <button type="submit" class="btn-primary btn-sm">
                            <x-heroicon-o-cloud-arrow-up class="w-4 h-4" />
                            Salvar Descrição
                        </button>
                    </div>
                </form>
            </x-ui.card>

            {{-- Vincular Produto --}}
            @if($listing->product)
            <x-ui.card title="Produto Vinculado">
                <div class="flex items-start justify-between gap-4">
                    <div class="space-y-1 text-sm">
                        <p class="font-medium text-gray-900 dark:text-white text-base">{{ $listing->product->name }}</p>
                        @if($listing->product->sku)
                            <p class="font-mono text-gray-400 dark:text-zinc-500">SKU: {{ $listing->product->sku }}</p>
                        @endif
                        <p class="text-gray-500 dark:text-zinc-400">
                            Preco interno: <span class="font-mono">R$ {{ number_format($listing->product->price, 2, ',', '.') }}</span>
                        </p>
                    </div>
                    <a href="{{ route('products.edit', $listing->product) }}" class="btn-secondary btn-sm flex-shrink-0">
                        <x-heroicon-o-arrow-top-right-on-square class="w-4 h-4" />
                        Ver Produto
                    </a>
                </div>

                <div class="mt-4 pt-4 border-t border-gray-100 dark:border-zinc-800">
                    <form method="POST" action="{{ route('listings.link-product', $listing) }}" class="flex items-end gap-3">
                        @csrf
                        <input type="hidden" name="product_id" value="{{ $listing->product_id }}">
                        <div class="flex-1">
                            <label class="form-label">Quantidade por venda</label>
                            <input type="number" name="product_quantity" value="{{ $listing->product_quantity }}"
                                min="1" class="form-input" placeholder="Ex: 100 (para Kit 100un)">
                            <p class="text-xs text-gray-400 dark:text-zinc-500 mt-1">
                                Unidades do produto descontadas do estoque a cada venda deste anuncio.
                            </p>
                        </div>
                        <button type="submit" class="btn-secondary btn-sm">Salvar</button>
                    </form>
                </div>

                <div class="mt-3">
                    <form method="POST" action="{{ route('listings.unlink-product', $listing) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn-ghost btn-sm text-red-500 hover:text-red-400"
                            onclick="return confirm('Remover vínculo com este produto?')">
                            <x-heroicon-o-x-mark class="w-4 h-4" />
                            Desvincular Produto
                        </button>
                    </form>
                </div>
            </x-ui.card>

            @else
            <x-ui.card title="Vincular Produto">
                <p class="text-sm text-gray-500 dark:text-zinc-400 mb-4">
                    Este anuncio ainda nao esta vinculado a nenhum produto do catalogo interno.
                </p>

                <form method="POST" action="{{ route('listings.link-product', $listing) }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="form-label">Produto existente</label>
                        <select name="product_id" class="form-input" required>
                            <option value="">Selecione um produto...</option>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}">
                                    {{ $product->name }}
                                    @if($product->sku) ({{ $product->sku }}) @endif
                                    — R$ {{ number_format($product->price, 2, ',', '.') }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Quantidade por venda</label>
                        <input type="number" name="product_quantity" value="1" min="1" class="form-input">
                        <p class="text-xs text-gray-400 dark:text-zinc-500 mt-1">
                            Ex: anuncio "Kit 100 garrafas" → produto "Garrafa X" com quantidade = 100
                        </p>
                    </div>
                    <button type="submit" class="btn-primary btn-sm">
                        <x-heroicon-o-link class="w-4 h-4" />
                        Vincular Produto
                    </button>
                </form>

                <div class="my-6 border-t border-gray-200 dark:border-zinc-700 relative">
                    <span class="absolute -top-2.5 left-1/2 -translate-x-1/2 px-3 bg-white dark:bg-zinc-900 text-xs text-gray-400 dark:text-zinc-500">ou</span>
                </div>

                <details class="group">
                    <summary class="cursor-pointer text-sm font-medium text-primary-400 hover:text-primary-300 list-none flex items-center gap-1.5">
                        <x-heroicon-o-plus-circle class="w-4 h-4" />
                        Criar novo produto a partir deste anuncio
                        <x-heroicon-o-chevron-down class="w-4 h-4 group-open:rotate-180 transition-transform" />
                    </summary>
                    <form method="POST" action="{{ route('listings.create-product', $listing) }}" class="mt-4 space-y-3">
                        @csrf
                        <div>
                            <label class="form-label">Nome do Produto *</label>
                            <input type="text" name="name" value="{{ $listing->title }}" class="form-input" required>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="form-label">Preco (R$) *</label>
                                <input type="number" name="price" value="{{ $listing->price }}" step="0.01" min="0" class="form-input" required>
                            </div>
                            <div>
                                <label class="form-label">SKU (opcional)</label>
                                <input type="text" name="sku" value="{{ $listing->meta['seller_sku'] ?? '' }}"
                                    class="form-input" placeholder="{{ $listing->external_id }}">
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Quantidade por venda</label>
                            <input type="number" name="product_quantity" value="1" min="1" class="form-input">
                        </div>
                        <button type="submit" class="btn-primary btn-sm">
                            <x-heroicon-o-plus class="w-4 h-4" />
                            Criar e Vincular
                        </button>
                    </form>
                </details>
            </x-ui.card>
            @endif

        </div>

        {{-- ═══ Sidebar ══════════════════════════════════════════════════════ --}}
        <div class="space-y-6">

            {{-- Saude do Anuncio --}}
            @if(!empty($quality))
            <x-ui.card title="Saude do Anuncio">
                @php
                    $health    = $quality['health'] ?? 0;
                    $pct       = round($health * 100);
                    $color     = $pct >= 70 ? 'bg-emerald-500' : ($pct >= 40 ? 'bg-amber-500' : 'bg-red-500');
                    $textColor = $pct >= 70 ? 'text-emerald-500' : ($pct >= 40 ? 'text-amber-500' : 'text-red-500');
                @endphp
                <div class="mb-4">
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-sm text-gray-500 dark:text-zinc-400">Pontuacao</span>
                        <span class="text-lg font-bold {{ $textColor }}">{{ $pct }}%</span>
                    </div>
                    <div class="h-2.5 bg-gray-200 dark:bg-zinc-700 rounded-full overflow-hidden">
                        <div class="{{ $color }} h-full rounded-full transition-all" style="width: {{ $pct }}%"></div>
                    </div>
                </div>

                @if(!empty($quality['issues']))
                <div class="space-y-2">
                    <p class="text-xs font-semibold text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Problemas</p>
                    @foreach($quality['issues'] as $issue)
                    <div class="flex items-start gap-2 text-sm">
                        <x-heroicon-o-x-circle class="w-4 h-4 text-red-400 flex-shrink-0 mt-0.5" />
                        <span class="text-gray-700 dark:text-zinc-300">{{ $issue['text'] ?? $issue['id'] ?? '' }}</span>
                    </div>
                    @endforeach
                </div>
                @endif

                @if(!empty($quality['recommendations']))
                <div class="mt-3 space-y-2">
                    <p class="text-xs font-semibold text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Recomendacoes</p>
                    @foreach($quality['recommendations'] as $rec)
                    <div class="flex items-start gap-2 text-sm">
                        <x-heroicon-o-light-bulb class="w-4 h-4 text-amber-400 flex-shrink-0 mt-0.5" />
                        <span class="text-gray-700 dark:text-zinc-300">{{ $rec['text'] ?? $rec }}</span>
                    </div>
                    @endforeach
                </div>
                @endif
            </x-ui.card>
            @endif

            {{-- Status --}}
            <x-ui.card title="Status">
                <div class="flex items-center justify-between mb-4">
                    <x-ui.badge :color="$listing->status_color">
                        {{ match($listing->status) {
                            'active'  => 'Ativo',
                            'paused'  => 'Pausado',
                            'closed'  => 'Encerrado',
                            'deleted' => 'Deletado',
                            default   => $listing->status,
                        } }}
                    </x-ui.badge>
                    @if(in_array($listing->status, ['active', 'paused']))
                    <form method="POST" action="{{ route('listings.toggle-status', $listing) }}">
                        @csrf
                        <button type="submit"
                            class="{{ $listing->status === 'active' ? 'btn-secondary' : 'btn-primary' }} btn-sm"
                            onclick="return confirm('{{ $listing->status === 'active' ? 'Pausar este anúncio?' : 'Reativar este anúncio?' }}')">
                            @if($listing->status === 'active')
                                <x-heroicon-o-pause class="w-4 h-4" />
                                Pausar
                            @else
                                <x-heroicon-o-play class="w-4 h-4" />
                                Ativar
                            @endif
                        </button>
                    </form>
                    @endif
                </div>
                @if($liveData && isset($liveData['status']) && $liveData['status'] !== $listing->status)
                <p class="text-xs text-amber-600 dark:text-amber-400">
                    Status no ML: {{ $liveData['status'] }}
                </p>
                @endif
            </x-ui.card>

            {{-- Info ML --}}
            <x-ui.card title="Informacoes ML">
                <div class="space-y-2.5 text-sm">
                    <div>
                        <span class="text-gray-500 dark:text-zinc-400 text-xs">ID</span>
                        <p class="font-mono text-xs mt-0.5">{{ $listing->external_id }}</p>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-zinc-400 text-xs">Conta</span>
                        <p class="mt-0.5">{{ $listing->marketplaceAccount?->account_name ?? '—' }}</p>
                    </div>
                    @if($live['listing_type_id'] ?? null)
                    <div>
                        <span class="text-gray-500 dark:text-zinc-400 text-xs">Tipo de anuncio</span>
                        <p class="font-mono mt-0.5 text-xs">
                            {{ match($live['listing_type_id']) {
                                'gold_pro'     => 'Premium',
                                'gold_special' => 'Classico',
                                'free'         => 'Grátis',
                                default        => $live['listing_type_id'],
                            } }}
                        </p>
                    </div>
                    @endif
                    @if($live['category_id'] ?? null)
                    <div>
                        <span class="text-gray-500 dark:text-zinc-400 text-xs">Categoria</span>
                        <p class="font-mono mt-0.5 text-xs">{{ $live['category_id'] }}</p>
                    </div>
                    @endif
                    @if($live['condition'] ?? null)
                    <div>
                        <span class="text-gray-500 dark:text-zinc-400 text-xs">Condicao</span>
                        <p class="mt-0.5 text-xs capitalize">
                            {{ match($live['condition']) {
                                'new'           => 'Novo',
                                'used'          => 'Usado',
                                'not_specified' => 'Não especificado',
                                default         => $live['condition'],
                            } }}
                        </p>
                    </div>
                    @endif
                    @if($hasVariations)
                    <div>
                        <span class="text-gray-500 dark:text-zinc-400 text-xs">Variações</span>
                        <p class="mt-0.5 text-xs">{{ count($variations) }} variação{{ count($variations) !== 1 ? 'ões' : '' }}</p>
                    </div>
                    @endif
                    @if($live['permalink'] ?? ($listingMeta['ml_permalink'] ?? null))
                    <a href="{{ $live['permalink'] ?? $listingMeta['ml_permalink'] }}" target="_blank"
                       class="flex items-center gap-1 text-primary-500 hover:text-primary-400 mt-0.5 text-xs">
                        Ver no ML
                        <x-heroicon-o-arrow-top-right-on-square class="w-3 h-3 flex-shrink-0" />
                    </a>
                    @endif
                </div>
            </x-ui.card>

            {{-- Frete --}}
            @if(!empty($live['shipping']))
            <x-ui.card title="Frete">
                <div class="space-y-2 text-sm">
                    @php $shipping = $live['shipping']; @endphp
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-zinc-400">Modo</span>
                        <span>
                            {{ match($shipping['mode'] ?? '') {
                                'me2'           => 'Mercado Envios',
                                'me1'           => 'Mercado Envios 1',
                                'custom'        => 'Personalizado',
                                'not_specified' => 'Não especificado',
                                default         => $shipping['mode'] ?? '—',
                            } }}
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-zinc-400">Frete Grátis</span>
                        <x-ui.badge :color="($shipping['free_shipping'] ?? false) ? 'success' : 'neutral'">
                            {{ ($shipping['free_shipping'] ?? false) ? 'Sim' : 'Não' }}
                        </x-ui.badge>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-zinc-400">Retirada</span>
                        <x-ui.badge :color="($shipping['local_pick_up'] ?? false) ? 'success' : 'neutral'">
                            {{ ($shipping['local_pick_up'] ?? false) ? 'Sim' : 'Não' }}
                        </x-ui.badge>
                    </div>
                    @if(!empty($shipping['dimensions']))
                    <div class="pt-2 border-t border-gray-100 dark:border-zinc-800">
                        <p class="text-xs text-gray-500 dark:text-zinc-400 mb-1">Dimensões</p>
                        @php $dims = $shipping['dimensions']; @endphp
                        <p class="text-xs font-mono">
                            {{ $dims['width'] ?? '?' }}×{{ $dims['height'] ?? '?' }}×{{ $dims['length'] ?? '?' }} cm
                            · {{ $dims['weight'] ?? '?' }}g
                        </p>
                    </div>
                    @endif
                </div>
            </x-ui.card>
            @endif

            {{-- Sincronizacao --}}
            <x-ui.card title="Sincronizacao">
                <div class="text-sm space-y-2">
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-zinc-400">Importado em</span>
                        <span>{{ $listing->created_at->format('d/m/Y') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-zinc-400">Atualizado em</span>
                        <span>{{ $listing->updated_at->format('d/m/Y H:i') }}</span>
                    </div>
                </div>
            </x-ui.card>

        </div>
    </div>
</x-app-layout>
