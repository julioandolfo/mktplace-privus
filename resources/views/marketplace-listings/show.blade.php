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


    @php
        // Null-safe wrappers — $liveData can be null when API fails or has no credentials
        $live        = $liveData ?? [];
        $listingMeta = $listing->meta ?? [];

        $hasVariations    = !empty($live['variations']) || !empty($listingMeta['has_variations']);
        $variations       = $live['variations'] ?? [];
        $isFulfillment    = in_array('fulfillment', $live['tags'] ?? []);
        $isHandlingLocked = $isFulfillment;
        $isPriceLocked    = $hasVariations;
        $isStockLocked    = $hasVariations;
        // Catalog items: title cannot be edited via API
        $isCatalogItem  = !empty($listingMeta['family_name'])
                       || !empty($listingMeta['catalog_product_id'])
                       || !empty($live['family_name'])
                       || !empty($live['catalog_product_id']);

        // Separate category attributes into editable vs read-only
        $currentAttrsIndexed = collect($live['attributes'] ?? [])->keyBy('id');

        // Only count truly missing: required + editable + without any real value
        // value_id = -1 is ML's sentinel for "not specified" — treat as missing
        $requiredAttrs   = collect($categoryAttributes)->filter(fn($a) =>
            in_array('required', $a['tags'] ?? []) && ! in_array('read_only', $a['tags'] ?? [])
        );
        $missingRequired = $requiredAttrs->filter(function ($a) use ($currentAttrsIndexed) {
            $current = $currentAttrsIndexed->get($a['id'] ?? '');
            if (! $current) return true;
            $hasName   = !empty($current['value_name']);
            $hasValues = !empty($current['values']);
            $valueId   = (string)($current['value_id'] ?? '');
            $hasId     = !empty($valueId) && $valueId !== '-1';
            return !$hasName && !$hasValues && !$hasId;
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
                                    <x-heroicon-o-information-circle class="w-3 h-3" />
                                    Edite o preço em cada variação abaixo.
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
                                    <x-heroicon-o-information-circle class="w-3 h-3" />
                                    Edite o estoque em cada variação abaixo.
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

                    {{-- handling_time não é editável via API ML para anúncios ativos --}}
                    @php $currentHandling = $live['shipping']['handling_time'] ?? null; @endphp
                    <input type="hidden" name="handling_time" value="{{ (int)$currentHandling }}">

                    <div class="flex items-start gap-2 text-xs bg-gray-50 dark:bg-zinc-800/40 border border-gray-200 dark:border-zinc-700 rounded-lg px-3 py-2.5">
                        <x-heroicon-o-information-circle class="w-4 h-4 flex-shrink-0 text-gray-400 dark:text-zinc-500 mt-0.5" />
                        <p class="text-gray-500 dark:text-zinc-400">
                            <strong>Prazo de disponibilidade</strong>
                            @if($currentHandling !== null && $currentHandling > 0)
                                (atual: {{ $currentHandling }} dia{{ $currentHandling > 1 ? 's' : '' }})
                            @endif
                            não pode ser alterado via API ML. Edite diretamente no
                            @if(!empty($live['permalink']))
                                <a href="{{ $live['permalink'] }}" target="_blank" class="text-primary-500 underline">painel do ML</a>.
                            @else
                                painel do Mercado Livre.
                            @endif
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
                            $valueType     = $attr['value_type'] ?? 'string';
                            $allowedValues = $attr['allowed_values'] ?? [];
                            $readOnly      = in_array('read_only', $attr['tags'] ?? []);

                            // Resolve current value — prefer value_name, then values[0].name
                            // Ignore value_id = -1 (ML sentinel for "not specified")
                            $rawValueId   = (string)($currentVal['value_id'] ?? '');
                            $rawValueName = $currentVal['value_name']
                                ?? ($currentVal['values'][0]['name'] ?? null);

                            // If value_id = -1 and no value_name, the attribute has no real value set
                            $isNotSpecified = ($rawValueId === '-1' && $rawValueName === null);
                            $currentValue   = $isNotSpecified ? '' : ($rawValueName ?? '');

                            // For allowed_values lists: also try to match by value_id to get the name
                            if (empty($currentValue) && !empty($rawValueId) && $rawValueId !== '-1' && !empty($allowedValues)) {
                                $matched = collect($allowedValues)->firstWhere('id', $rawValueId);
                                if ($matched) {
                                    $currentValue = $matched['name'] ?? '';
                                }
                            }

                            $isRequired = in_array('required', $attr['tags'] ?? []);
                            $isMissing  = $isRequired && $currentValue === '';

                            // Detect boolean: value_type=boolean OR allowed_values exactly Sim/Não
                            $isBoolean = $valueType === 'boolean'
                                || (count($allowedValues) === 2
                                    && collect($allowedValues)->pluck('name')->sort()->values()->all() === ['Não', 'Sim']);

                            // Hint text for number_unit attributes (e.g. "W", "V", "kg")
                            $unitHint = '';
                            if ($valueType === 'number_unit' && !empty($attr['allowed_units'])) {
                                $unitHint = collect($attr['allowed_units'])->pluck('name')->implode('/');
                            }
                        @endphp
                        @if($attrId && !$readOnly)
                        <div class="flex items-start gap-3 @if($isMissing) bg-amber-50/40 dark:bg-amber-900/10 rounded-lg px-2 py-1.5 -mx-2 @endif">
                            <div class="w-44 flex-shrink-0 pt-1.5">
                                <span class="text-sm text-gray-600 dark:text-zinc-400 leading-tight">
                                    {{ $attrName }}
                                    @if($isMissing)
                                        <span class="text-red-400 text-xs ml-0.5" title="Obrigatório não preenchido">*</span>
                                    @elseif($isRequired)
                                        <span class="text-amber-400 text-xs ml-0.5">*</span>
                                    @endif
                                </span>
                                @if($unitHint)
                                    <span class="block text-[10px] text-gray-400 dark:text-zinc-600 mt-0.5">{{ $unitHint }}</span>
                                @endif
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
                                    <option value="">— Selecione —</option>
                                    @foreach($allowedValues as $val)
                                    @php
                                        $valName = $val['name'] ?? $val['id'] ?? '';
                                        $isSelected = ($currentValue !== '' && $currentValue === $valName);
                                    @endphp
                                    <option value="{{ $valName }}" @selected($isSelected)>
                                        {{ $valName }}
                                    </option>
                                    @endforeach
                                </select>
                            @elseif($valueType === 'number' || $valueType === 'number_unit')
                                <div class="flex-1 flex items-center gap-1.5">
                                    <input type="number" name="attributes[{{ $attrId }}]"
                                        value="{{ old('attributes.'.$attrId, $currentValue !== '' ? $currentValue : '') }}"
                                        class="form-input flex-1 text-sm py-1.5"
                                        placeholder="Ex: {{ $unitHint ?: '0' }}"
                                        step="any">
                                    @if($unitHint)
                                        <span class="text-xs text-gray-400 dark:text-zinc-500 flex-shrink-0">{{ $unitHint }}</span>
                                    @endif
                                </div>
                            @else
                                <input type="text" name="attributes[{{ $attrId }}]"
                                    value="{{ old('attributes.'.$attrId, $currentValue) }}"
                                    class="form-input flex-1 text-sm py-1.5"
                                    placeholder="Não informado">
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

                {{-- Add new variation --}}
                @php
                    $varAttrKeys = collect($variations)
                        ->flatMap(fn($v) => collect($v['attribute_combinations'] ?? []))
                        ->unique('id')
                        ->values();
                @endphp
                @if($varAttrKeys->isNotEmpty())
                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-zinc-700" x-data="{ showNewVar: false }">
                    <button type="button" @click="showNewVar = !showNewVar"
                        class="btn-ghost btn-sm text-xs w-full flex items-center justify-center gap-1.5 py-2">
                        <x-heroicon-o-plus-circle class="w-4 h-4" />
                        <span x-text="showNewVar ? 'Cancelar' : 'Adicionar nova variação'"></span>
                    </button>

                    <div x-show="showNewVar" x-collapse x-cloak class="mt-3">
                        <form method="POST" action="{{ route('listings.add-variation', $listing) }}"
                              class="bg-white dark:bg-zinc-900 border border-dashed border-gray-300 dark:border-zinc-600 rounded-lg p-4 space-y-4">
                            @csrf

                            <p class="text-xs font-semibold text-gray-600 dark:text-zinc-400 uppercase tracking-wider">
                                Atributos da Variação
                            </p>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                @foreach($varAttrKeys as $attrKey)
                                <div>
                                    <label class="text-xs text-gray-500 dark:text-zinc-400 mb-1 block">
                                        {{ $attrKey['name'] ?? $attrKey['id'] }} *
                                    </label>
                                    <input type="hidden" name="combinations[{{ $loop->index }}][id]" value="{{ $attrKey['id'] }}">
                                    <input type="text" name="combinations[{{ $loop->index }}][value_name]"
                                        class="form-input text-sm" required
                                        placeholder="Ex: {{ collect($variations)->flatMap(fn($v) => collect($v['attribute_combinations'] ?? []))->where('id', $attrKey['id'])->pluck('value_name')->unique()->take(3)->join(', ') }}">
                                </div>
                                @endforeach
                            </div>

                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                <div>
                                    <label class="text-xs text-gray-500 dark:text-zinc-400 mb-1 block">Preço (R$)</label>
                                    <input type="number" name="price" step="0.01" min="0"
                                        class="form-input text-sm" placeholder="Igual ao anúncio">
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500 dark:text-zinc-400 mb-1 block">Estoque *</label>
                                    <input type="number" name="available_quantity" min="0" value="1"
                                        class="form-input text-sm" required>
                                </div>
                                <div class="col-span-2">
                                    <label class="text-xs text-gray-500 dark:text-zinc-400 mb-1 block">SKU do Vendedor</label>
                                    <input type="text" name="seller_custom_field" maxlength="100"
                                        class="form-input text-sm" placeholder="Código interno">
                                </div>
                            </div>

                            {{-- Select pictures for the new variation --}}
                            @if(!empty($allPictures))
                            <div>
                                <label class="text-xs text-gray-500 dark:text-zinc-400 mb-2 block">
                                    Imagens da nova variação
                                    <span class="text-gray-400 dark:text-zinc-600">(opcional)</span>
                                </label>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($allPictures as $pic)
                                    <label class="relative cursor-pointer group">
                                        <input type="checkbox" name="picture_ids[]" value="{{ $pic['id'] }}"
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

                            <div class="flex justify-end">
                                <button type="submit" class="btn-primary btn-sm text-xs">
                                    <x-heroicon-o-plus class="w-3.5 h-3.5" />
                                    Adicionar variação
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                @endif
            </x-ui.card>
            @endif

            {{-- Imagens --}}
            @if($liveData !== null)
            <div id="imagens">
            <x-ui.card>
                <x-slot name="title">
                    <div class="flex items-center justify-between">
                        <span>Imagens</span>
                        @if($aiConfigured)
                        <button type="button" id="btn-gen-img"
                            class="btn-secondary btn-sm text-xs flex items-center gap-1.5 ai-gen-btn">
                            <x-heroicon-o-sparkles class="w-3.5 h-3.5 text-purple-500" />
                            Gerar com IA
                        </button>
                        @endif
                    </div>
                </x-slot>

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

                {{-- AI Image generation panel --}}
                @if($aiConfigured)
                <div id="ai-img-panel" class="hidden mb-4 p-3 rounded-lg bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800 space-y-3">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-sparkles class="w-4 h-4 text-purple-500 flex-shrink-0" />
                        <p class="text-xs font-medium text-purple-700 dark:text-purple-300">Gerar imagem com IA</p>
                    </div>
                    <p class="text-xs text-purple-600 dark:text-purple-400">
                        Descreva a imagem que deseja gerar. Quanto mais detalhado o prompt, melhor o resultado.
                    </p>
                    <textarea id="ai-img-prompt" rows="2"
                        class="form-input w-full text-sm"
                        placeholder="Ex: Foto profissional do produto em fundo branco, iluminação de estúdio...">Foto profissional de produto para e-commerce: {{ $listing->title }}. Fundo branco puro, iluminação de estúdio, alta resolução, vista frontal, sem texto.</textarea>

                    <div class="flex items-center gap-3">
                        <button type="button" id="btn-run-ai-img"
                            class="btn-primary btn-sm text-xs flex items-center gap-1.5">
                            <x-heroicon-o-sparkles class="w-3.5 h-3.5" />
                            <span id="ai-img-btn-text">Gerar imagem</span>
                        </button>
                        <div id="ai-img-spinner" class="hidden">
                            <svg class="animate-spin w-4 h-4 text-purple-500" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                        </div>
                        <p id="ai-img-status" class="text-xs text-purple-600 dark:text-purple-400 hidden">Gerando imagem (pode levar ~15s)...</p>
                    </div>

                    <div id="ai-img-error" class="hidden text-xs text-red-500 flex items-start gap-1">
                        <x-heroicon-o-exclamation-circle class="w-3.5 h-3.5 flex-shrink-0 mt-0.5" />
                        <span id="ai-img-error-text"></span>
                    </div>

                    {{-- Preview of generated image --}}
                    <div id="ai-img-result" class="hidden space-y-2">
                        <p class="text-xs font-medium text-purple-700 dark:text-purple-300">Imagem gerada — aprovada?</p>
                        <img id="ai-img-preview" src="" alt="Imagem gerada pela IA"
                            class="max-w-xs rounded-lg border border-purple-200 dark:border-purple-700 shadow-sm">
                        <div class="flex gap-2">
                            <button type="button" id="btn-ai-img-upload"
                                class="btn-primary btn-sm text-xs flex items-center gap-1.5">
                                <x-heroicon-o-cloud-arrow-up class="w-3.5 h-3.5" />
                                <span id="ai-img-upload-text">Adicionar ao anúncio no ML</span>
                            </button>
                            <button type="button" id="btn-ai-img-regen"
                                class="btn-secondary btn-sm text-xs flex items-center gap-1.5">
                                <x-heroicon-o-arrow-path class="w-3.5 h-3.5" />
                                Gerar outra
                            </button>
                        </div>
                        <div id="ai-img-upload-status" class="hidden text-xs font-medium"></div>
                    </div>
                </div>
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
            </div>
            @endif

            {{-- Descricao --}}
            <div id="descricao">
            <x-ui.card>
                <x-slot name="title">
                    <div class="flex items-center justify-between">
                        <span>Descrição</span>
                        @if($aiConfigured)
                        <button type="button" id="btn-gen-desc"
                            class="btn-secondary btn-sm text-xs flex items-center gap-1.5 ai-gen-btn">
                            <x-heroicon-o-sparkles class="w-3.5 h-3.5 text-purple-500" />
                            Gerar com IA
                        </button>
                        @else
                        <a href="{{ route('settings.index', ['tab' => 'ai']) }}"
                            class="text-xs text-gray-400 dark:text-zinc-500 flex items-center gap-1 hover:text-purple-500 transition-colors">
                            <x-heroicon-o-sparkles class="w-3.5 h-3.5" />
                            Configurar IA
                        </a>
                        @endif
                    </div>
                </x-slot>

                {{-- AI description panel --}}
                @if($aiConfigured)
                <div id="ai-desc-panel" class="hidden mb-4 p-3 rounded-lg bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800 space-y-2">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-sparkles class="w-4 h-4 text-purple-500 flex-shrink-0" />
                        <p class="text-xs font-medium text-purple-700 dark:text-purple-300">Gerar descrição com IA</p>
                    </div>
                    <p class="text-xs text-purple-600 dark:text-purple-400">
                        A IA irá criar uma descrição profissional baseada no título e atributos do anúncio.
                        A descrição atual será usada como base se preenchida.
                    </p>
                    <div class="flex items-center gap-2 pt-1">
                        <button type="button" id="btn-run-ai-desc"
                            class="btn-primary btn-sm text-xs flex items-center gap-1.5">
                            <x-heroicon-o-sparkles class="w-3.5 h-3.5" />
                            <span id="ai-desc-btn-text">Gerar agora</span>
                        </button>
                        <div id="ai-desc-spinner" class="hidden">
                            <svg class="animate-spin w-4 h-4 text-purple-500" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                        </div>
                        <p id="ai-desc-status" class="text-xs text-purple-600 dark:text-purple-400 hidden">Gerando descrição, aguarde...</p>
                    </div>
                    <div id="ai-desc-error" class="hidden text-xs text-red-500 mt-1 flex items-center gap-1">
                        <x-heroicon-o-exclamation-circle class="w-3.5 h-3.5 flex-shrink-0" />
                        <span id="ai-desc-error-text"></span>
                    </div>
                </div>
                @endif

                <form method="POST" action="{{ route('listings.update-description', $listing) }}" class="space-y-3">
                    @csrf
                    <textarea id="listing-description" name="description" rows="8"
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
            </div>

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

            {{-- Qualidade do Anúncio (/item/{id}/performance — novo endpoint ML) --}}
            <x-ui.card>
                @php
                    // New /performance endpoint: score=0-100, level_wording in PT, buckets[]
                    $qualityUnavailable = !empty($quality['_unavailable']); // ML returned 404 (not generated yet)
                    $hasQuality         = !empty($quality) && isset($quality['score']);
                    $qScore             = $hasQuality ? (int) ($quality['score'] ?? 0) : 0;
                    $qLevelWord     = $quality['level_wording'] ?? ($quality['level'] ?? '');
                    // Normalize level label for MLB (PT-BR)
                    $qLevelLabel    = match(strtolower($qLevelWord)) {
                        'profissional', 'profesional', 'professional', 'good' => 'Profissional',
                        'satisfatória', 'satisfatoria', 'estándar', 'standard' => 'Satisfatória',
                        default => $qLevelWord ?: 'Básica',
                    };
                    $isProfessional = $qScore >= 66;
                    $isStandard     = $qScore >= 50 && $qScore < 66;

                    // Flatten all variables from all buckets for display
                    $allVariables = collect($quality['buckets'] ?? [])
                        ->flatMap(fn($b) => $b['variables'] ?? []);
                    $completedVars = $allVariables->where('status', 'COMPLETED')->count();
                    $totalVars     = $allVariables->count();
                @endphp
                <x-slot name="title">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-1.5">
                            <x-heroicon-o-star class="w-4 h-4 text-amber-400" />
                            <span>Qualidade do Anúncio</span>
                        </div>
                        @if($hasQuality)
                        <span class="text-sm font-bold {{ $isProfessional ? 'text-emerald-500' : ($isStandard ? 'text-amber-500' : 'text-red-500') }}">
                            {{ $qScore }}%
                        </span>
                        @endif
                    </div>
                </x-slot>

                @if($hasQuality)

                {{-- Score bar --}}
                <div class="mb-4">
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-xs font-semibold {{ $isProfessional ? 'text-emerald-500' : ($isStandard ? 'text-amber-500' : 'text-red-500') }}">
                            {{ $qLevelLabel }}
                        </span>
                        <span class="text-xs text-gray-400 dark:text-zinc-500">{{ $qScore }}/100</span>
                    </div>
                    <div class="h-2.5 bg-gray-200 dark:bg-zinc-700 rounded-full overflow-hidden">
                        <div class="h-full rounded-full transition-all duration-700
                            {{ $isProfessional ? 'bg-emerald-500' : ($isStandard ? 'bg-amber-500' : 'bg-red-500') }}"
                             style="width: {{ $qScore }}%"></div>
                    </div>
                    <div class="flex text-[10px] text-gray-400 dark:text-zinc-600 mt-1 justify-between">
                        <span>Básica &lt;50%</span>
                        <span>Satisfatória ≥50%</span>
                        <span>Profissional ≥66%</span>
                    </div>
                </div>

                {{-- Buckets e variáveis --}}
                @if($allVariables->isNotEmpty())
                <div class="space-y-3">
                    <p class="text-xs font-semibold text-gray-500 dark:text-zinc-400 uppercase tracking-wider">
                        Fatores ({{ $completedVars }}/{{ $totalVars }})
                    </p>

                    @foreach($quality['buckets'] as $bucket)
                    @if(!empty($bucket['variables']))
                    <div>
                        {{-- Bucket header --}}
                        <p class="text-[11px] font-medium text-gray-500 dark:text-zinc-500 mb-1.5 flex items-center gap-1">
                            @if($bucket['status'] === 'COMPLETED')
                                <x-heroicon-s-check-circle class="w-3 h-3 text-emerald-500" />
                            @else
                                <x-heroicon-o-ellipsis-horizontal-circle class="w-3 h-3 text-amber-400" />
                            @endif
                            {{ $bucket['title'] ?? $bucket['key'] }}
                        </p>

                        <div class="space-y-1">
                            @foreach($bucket['variables'] as $variable)
                            @php
                                $varCompleted = ($variable['status'] ?? '') === 'COMPLETED';
                                $varScore     = round($variable['score'] ?? 0);
                                // Get first pending rule's action link
                                $actionRule   = collect($variable['rules'] ?? [])
                                    ->firstWhere('status', 'PENDING');
                                $actionLink   = $actionRule['wordings']['link'] ?? null;
                                $actionLabel  = $actionRule['wordings']['label'] ?? null;
                                // Check if we have a local page for this variable
                                $varKey       = $variable['key'] ?? '';
                                $localAnchor  = match(true) {
                                    str_contains($varKey, 'PICTURES') => '#imagens',
                                    str_contains($varKey, 'DESCRIPTION') => '#descricao',
                                    str_contains($varKey, 'TECHNICAL_SPEC') => '#atributos',
                                    str_contains($varKey, 'LISTING_TYPE') || str_contains($varKey, 'FINANCING') => '#tipo-anuncio',
                                    default => null,
                                };
                            @endphp
                            <div class="flex items-start gap-2 py-1 px-1.5 rounded-md text-xs
                                {{ $varCompleted ? 'bg-emerald-50/40 dark:bg-emerald-900/10' : 'bg-red-50/30 dark:bg-red-900/10' }}">
                                {{-- Status icon --}}
                                @if($varCompleted)
                                    <x-heroicon-s-check-circle class="w-3.5 h-3.5 text-emerald-500 flex-shrink-0 mt-0.5" />
                                @else
                                    <x-heroicon-o-x-circle class="w-3.5 h-3.5 text-red-400 flex-shrink-0 mt-0.5" />
                                @endif

                                {{-- Title + progress bar --}}
                                <div class="flex-1 min-w-0">
                                    <p class="{{ $varCompleted ? 'text-gray-500 dark:text-zinc-500' : 'text-gray-700 dark:text-zinc-300 font-medium' }} leading-tight truncate"
                                       title="{{ $variable['title'] ?? '' }}">
                                        {{ $variable['title'] ?? $varKey }}
                                    </p>
                                    @if(!$varCompleted)
                                    <div class="mt-0.5 h-1 bg-gray-200 dark:bg-zinc-700 rounded-full overflow-hidden w-full">
                                        <div class="h-full bg-amber-400 rounded-full" style="width: {{ $varScore }}%"></div>
                                    </div>
                                    @endif
                                </div>

                                {{-- Action link --}}
                                @if(!$varCompleted)
                                    @if($localAnchor)
                                        <a href="{{ $localAnchor }}" class="flex-shrink-0 text-[10px] text-primary-500 hover:underline font-medium">Editar</a>
                                    @elseif($actionLink)
                                        <a href="{{ $actionLink }}" target="_blank" class="flex-shrink-0 text-[10px] text-primary-500 hover:underline font-medium" title="{{ $actionLabel }}">ML↗</a>
                                    @endif
                                @endif
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif
                    @endforeach
                </div>
                @endif

                @elseif($qualityUnavailable)
                {{-- 404: ML ainda não calculou o score para este anúncio --}}
                <div class="flex items-start gap-3 text-xs text-gray-500 dark:text-zinc-400">
                    <div class="w-8 h-8 rounded-full bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center flex-shrink-0 mt-0.5">
                        <x-heroicon-o-clock class="w-4 h-4 text-amber-500" />
                    </div>
                    <div class="space-y-1.5">
                        <p class="font-semibold text-gray-700 dark:text-zinc-300">Pontuação ainda não calculada pelo Mercado Livre</p>
                        <p class="leading-relaxed">O ML pode levar algumas horas para gerar o score de qualidade de anúncios novos ou recém atualizados. Recarregue esta página mais tarde.</p>
                        <div class="flex items-center gap-3 mt-2">
                            <a href="{{ route('listings.show', $listing) }}"
                               class="inline-flex items-center gap-1.5 text-primary-500 hover:text-primary-600 font-medium">
                                <x-heroicon-o-arrow-path class="w-3.5 h-3.5" />
                                Verificar novamente
                            </a>
                            @if(!empty($live['permalink']))
                            <a href="{{ $live['permalink'] }}" target="_blank"
                               class="inline-flex items-center gap-1.5 text-gray-500 hover:text-gray-700 dark:hover:text-zinc-200">
                                <x-heroicon-o-arrow-top-right-on-square class="w-3.5 h-3.5" />
                                Ver no ML
                            </a>
                            @endif
                        </div>
                    </div>
                </div>
                @else
                {{-- Erro ao buscar qualidade (API falhou ou sem credenciais) --}}
                <div class="flex items-start gap-3 text-xs text-gray-500 dark:text-zinc-400">
                    <div class="w-8 h-8 rounded-full bg-gray-100 dark:bg-zinc-800 flex items-center justify-center flex-shrink-0 mt-0.5">
                        <x-heroicon-o-signal-slash class="w-4 h-4 text-gray-400" />
                    </div>
                    <div class="space-y-1">
                        <p class="font-semibold text-gray-600 dark:text-zinc-300">Não foi possível carregar a qualidade</p>
                        <p>Verifique os logs ou tente recarregar a página.</p>
                        <a href="{{ route('listings.show', $listing) }}"
                           class="inline-flex items-center gap-1.5 text-primary-500 hover:text-primary-600 font-medium mt-1">
                            <x-heroicon-o-arrow-path class="w-3.5 h-3.5" />
                            Tentar novamente
                        </a>
                    </div>
                </div>
                @endif

                {{-- ── Botão Melhorar com IA ─────────────────────────────── --}}
                @if($hasQuality && $aiConfigured && $completedVars < $totalVars)
                <div class="mt-4 pt-4 border-t border-gray-100 dark:border-zinc-800" id="ai-improve-section">

                    {{-- Botão principal --}}
                    <button type="button" id="btn-ai-improve"
                        class="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg
                               bg-gradient-to-r from-purple-600 to-violet-600 hover:from-purple-700 hover:to-violet-700
                               text-white text-sm font-semibold shadow-sm transition-all duration-200
                               disabled:opacity-60 disabled:cursor-not-allowed">
                        <x-heroicon-o-sparkles class="w-4 h-4" />
                        Melhorar com IA
                        <span class="text-xs font-normal opacity-80">({{ $totalVars - $completedVars }} pendente{{ ($totalVars - $completedVars) > 1 ? 's' : '' }})</span>
                    </button>
                    <p class="text-[10px] text-center text-gray-400 dark:text-zinc-600 mt-1.5">
                        Aplica automaticamente: descrição, título e atributos técnicos
                    </p>

                    {{-- Loading overlay --}}
                    <div id="ai-improve-loading" class="hidden mt-3 p-3 bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800 rounded-lg">
                        <div class="flex items-center gap-2 mb-2">
                            <svg class="animate-spin w-4 h-4 text-purple-500 flex-shrink-0" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            <p class="text-xs font-medium text-purple-700 dark:text-purple-300">Melhorando anúncio com IA...</p>
                        </div>
                        <p id="ai-improve-status-text" class="text-xs text-purple-600 dark:text-purple-400">
                            Analisando qualidade e aplicando melhorias. Isso pode levar 30–60 segundos...
                        </p>
                    </div>

                    {{-- Result panel --}}
                    <div id="ai-improve-result" class="hidden mt-3 space-y-2">
                        {{-- Message --}}
                        <p id="ai-improve-message" class="text-xs font-semibold text-gray-700 dark:text-zinc-300"></p>

                        {{-- Improvements applied ✅ --}}
                        <div id="ai-improve-done-list" class="hidden space-y-1">
                            <p class="text-[10px] font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">Melhorias aplicadas</p>
                            <ul id="ai-improve-done-items" class="space-y-1"></ul>
                        </div>

                        {{-- Skipped items ⚠️ --}}
                        <div id="ai-improve-skip-list" class="hidden space-y-1">
                            <p class="text-[10px] font-bold text-amber-600 dark:text-amber-400 uppercase tracking-wider">Requerem ação manual</p>
                            <ul id="ai-improve-skip-items" class="space-y-1"></ul>
                        </div>

                        {{-- Errors ❌ --}}
                        <div id="ai-improve-err-list" class="hidden space-y-1">
                            <p class="text-[10px] font-bold text-red-500 uppercase tracking-wider">Erros</p>
                            <ul id="ai-improve-err-items" class="space-y-1"></ul>
                        </div>

                        {{-- Reload button (shown after improvements) --}}
                        <button type="button" id="btn-ai-improve-reload"
                            class="hidden w-full btn-primary btn-sm mt-2 flex items-center justify-center gap-1.5">
                            <x-heroicon-o-arrow-path class="w-3.5 h-3.5" />
                            Recarregar para ver as mudanças
                        </button>
                    </div>

                    {{-- Global error --}}
                    <div id="ai-improve-global-error" class="hidden mt-3 flex items-start gap-2 text-xs text-red-500">
                        <x-heroicon-o-exclamation-circle class="w-4 h-4 flex-shrink-0 mt-0.5" />
                        <span id="ai-improve-global-error-text"></span>
                    </div>
                </div>
                @elseif($hasQuality && !$aiConfigured && $completedVars < $totalVars)
                <div class="mt-4 pt-4 border-t border-gray-100 dark:border-zinc-800">
                    <a href="{{ route('settings.index') }}?tab=ai"
                        class="w-full flex items-center justify-center gap-2 px-4 py-2 rounded-lg border-2 border-dashed border-purple-300 dark:border-purple-700 text-purple-500 text-xs font-medium hover:border-purple-500 transition-colors">
                        <x-heroicon-o-sparkles class="w-4 h-4" />
                        Configurar IA para melhorar automaticamente
                    </a>
                </div>
                @endif

                @if($hasQuality && !empty($live['permalink']))
                <div class="mt-3 pt-3 border-t border-gray-100 dark:border-zinc-800">
                    <a href="{{ $live['permalink'] }}" target="_blank"
                        class="text-xs text-gray-400 dark:text-zinc-500 hover:text-primary-500 flex items-center gap-1 transition-colors">
                        <x-heroicon-o-arrow-top-right-on-square class="w-3.5 h-3.5" />
                        Ver anúncio no Mercado Livre
                    </a>
                </div>
                @endif
            </x-ui.card>

            {{-- ══ Experiência de Compra (/reputation/items/{id}/purchase_experience) ══ --}}
            @php
                $hasPE      = !empty($purchaseExperience) && !isset($purchaseExperience['_redirect']);
                $peRep      = $purchaseExperience['reputation']     ?? null;
                $peColor    = $peRep['color'] ?? 'gray'; // green | yellow | orange | red
                $peValue    = $peRep['value'] ?? null;   // 0-100 or -1
                $peText     = $peRep['text']  ?? '';
                $peProblems = $purchaseExperience['metrics_details']['problems'] ?? [];
                $peTitle    = $purchaseExperience['title']['text'] ?? 'Experiência de compra';
                $peSubs     = $purchaseExperience['subtitles'] ?? [];
                $peFreeze   = $purchaseExperience['freeze'] ?? null;
                $peColorMap = [
                    'green'  => ['bg' => 'bg-emerald-500', 'text' => 'text-emerald-600 dark:text-emerald-400', 'badge' => 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400', 'ring' => '#10b981'],
                    'yellow' => ['bg' => 'bg-amber-400',   'text' => 'text-amber-600 dark:text-amber-400',   'badge' => 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400',   'ring' => '#f59e0b'],
                    'orange' => ['bg' => 'bg-orange-500',  'text' => 'text-orange-600 dark:text-orange-400', 'badge' => 'bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-400', 'ring' => '#f97316'],
                    'red'    => ['bg' => 'bg-red-500',     'text' => 'text-red-600 dark:text-red-400',     'badge' => 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400',     'ring' => '#ef4444'],
                    'gray'   => ['bg' => 'bg-gray-400',    'text' => 'text-gray-500 dark:text-zinc-400',   'badge' => 'bg-gray-100 dark:bg-zinc-800 text-gray-600 dark:text-zinc-400',     'ring' => '#9ca3af'],
                ];
                $peStyle = $peColorMap[$peColor] ?? $peColorMap['gray'];
            @endphp
            <x-ui.card>
                <x-slot name="title">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-1.5">
                            <x-heroicon-o-face-smile class="w-4 h-4 text-blue-400" />
                            <span>Experiência de Compra</span>
                        </div>
                        @if($hasPE && $peValue !== null)
                        <span class="text-sm font-bold {{ $peStyle['text'] }}">
                            {{ $peValue }}%
                        </span>
                        @endif
                    </div>
                </x-slot>

                @if($hasPE)

                {{-- Score bar --}}
                <div class="mb-4">
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full {{ $peStyle['badge'] }}">
                            {{ $peText }}
                        </span>
                        <span class="text-xs text-gray-400 dark:text-zinc-500">{{ $peValue }}/100</span>
                    </div>
                    <div class="h-2 bg-gray-200 dark:bg-zinc-700 rounded-full overflow-hidden">
                        <div class="h-full rounded-full transition-all duration-700 {{ $peStyle['bg'] }}"
                             style="width: {{ max(2, $peValue) }}%"></div>
                    </div>
                </div>

                {{-- Freeze notice --}}
                @if($peFreeze)
                <div class="mb-3 flex items-start gap-2 text-xs bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg px-3 py-2">
                    <x-heroicon-o-information-circle class="w-4 h-4 text-blue-500 flex-shrink-0 mt-0.5" />
                    <p class="text-blue-700 dark:text-blue-300 leading-relaxed">{{ $peFreeze['text'] ?? '' }}</p>
                </div>
                @endif

                {{-- Subtitles --}}
                @if(!empty($peSubs))
                <div class="mb-3 space-y-1">
                    @foreach($peSubs as $sub)
                    <p class="text-xs text-gray-600 dark:text-zinc-400 leading-relaxed">{{ $sub['text'] ?? '' }}</p>
                    @endforeach
                </div>
                @endif

                {{-- Problems list --}}
                @if(!empty($peProblems))
                <div class="space-y-2 mt-3">
                    <p class="text-[11px] font-semibold text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Problemas detectados</p>
                    @foreach($peProblems as $prob)
                    @php
                        $probColor  = $prob['color'] ?? '#9ca3af';
                        $probL2     = $prob['level_two']['title']['text']  ?? ($prob['level_two']['title']['text']  ?? '');
                        $probL3     = $prob['level_three']['title']['text'] ?? ($prob['level_three']['title']['text'] ?? '');
                        $probRemedy = $prob['level_three']['remedy']['text'] ?? ($prob['level_three']['remedy']['text'] ?? '');
                        $cancellations = $prob['cancellations'] ?? 0;
                        $claims        = $prob['claims']        ?? 0;
                    @endphp
                    <div class="rounded-lg border border-gray-100 dark:border-zinc-800 p-3 space-y-1.5">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background-color: {{ $probColor }}"></span>
                                <span class="text-xs font-semibold text-gray-800 dark:text-white">{{ $probL2 }}</span>
                                @if(!empty($prob['tag']))
                                <span class="text-[9px] px-1.5 py-0.5 rounded-full bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 font-bold uppercase">{{ $prob['tag'] }}</span>
                                @endif
                            </div>
                            <div class="flex items-center gap-2 text-[10px] text-gray-500 dark:text-zinc-500">
                                @if($cancellations > 0)
                                <span title="Cancelamentos">❌ {{ $cancellations }}</span>
                                @endif
                                @if($claims > 0)
                                <span title="Reclamações">⚠️ {{ $claims }}</span>
                                @endif
                            </div>
                        </div>
                        @if($probL3)
                        <p class="text-xs text-gray-600 dark:text-zinc-400 pl-4.5">{{ $probL3 }}</p>
                        @endif
                        @if($probRemedy)
                        <div class="mt-1.5 pl-4 border-l-2 border-gray-200 dark:border-zinc-700">
                            <p class="text-[11px] text-gray-500 dark:text-zinc-500 italic leading-relaxed">💡 {{ $probRemedy }}</p>
                        </div>
                        @endif
                    </div>
                    @endforeach
                </div>
                @elseif($peValue >= 100)
                <div class="flex items-center gap-2 text-xs text-emerald-600 dark:text-emerald-400">
                    <x-heroicon-o-check-circle class="w-4 h-4" />
                    <span>Nenhum problema detectado. Experiência excelente!</span>
                </div>
                @endif

                {{-- Period --}}
                @php
                    $peDist = $purchaseExperience['metrics_details']['distribution'] ?? null;
                @endphp
                @if($peDist)
                <p class="text-[10px] text-gray-400 dark:text-zinc-600 mt-3">
                    Período: {{ \Carbon\Carbon::parse($peDist['from'])->format('d/m/Y') }} – {{ \Carbon\Carbon::parse($peDist['to'])->format('d/m/Y') }}
                </p>
                @endif

                @elseif(isset($purchaseExperience['_redirect']))
                <div class="flex items-start gap-2 text-xs text-gray-500 dark:text-zinc-400">
                    <x-heroicon-o-information-circle class="w-4 h-4 text-blue-400 flex-shrink-0 mt-0.5" />
                    <p>Este anúncio usa a estrutura de User Product do ML. A experiência de compra está disponível no painel do vendedor.</p>
                </div>
                @else
                <div class="flex items-start gap-2 text-xs text-gray-500 dark:text-zinc-400">
                    <x-heroicon-o-clock class="w-4 h-4 text-gray-400 flex-shrink-0 mt-0.5" />
                    <div class="space-y-1">
                        <p class="font-medium text-gray-600 dark:text-zinc-300">Sem dados de experiência de compra</p>
                        <p>O ML só gera este indicador após as primeiras vendas do anúncio.</p>
                    </div>
                </div>
                @endif
            </x-ui.card>

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

            {{-- Tipo de Anúncio (editável) --}}
            @if($liveData !== null)
            <x-ui.card>
                @php
                    $currentListingType = $live['listing_type_id'] ?? $listingMeta['listing_type_id'] ?? null;
                    $listingTypeLabels  = [
                        'gold_pro'     => ['label' => 'Premium',  'color' => 'text-yellow-600 dark:text-yellow-400'],
                        'gold_premium' => ['label' => 'Premium',  'color' => 'text-yellow-600 dark:text-yellow-400'],
                        'gold_special' => ['label' => 'Clássico', 'color' => 'text-blue-600 dark:text-blue-400'],
                        'gold'         => ['label' => 'Ouro',     'color' => 'text-yellow-500 dark:text-yellow-300'],
                        'silver'       => ['label' => 'Prata',    'color' => 'text-gray-500 dark:text-zinc-400'],
                        'bronze'       => ['label' => 'Bronze',   'color' => 'text-orange-500 dark:text-orange-400'],
                        'free'         => ['label' => 'Grátis',   'color' => 'text-gray-400 dark:text-zinc-500'],
                    ];
                    $typeInfo = $listingTypeLabels[$currentListingType] ?? ['label' => $currentListingType ?? '—', 'color' => 'text-gray-500'];
                    // $availableListingTypes comes from controller (GET /items/{id}/available_listing_types)
                    $canChangeType = !empty($availableListingTypes);
                @endphp
                <div x-data="{ editing: false }">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-sm font-semibold text-gray-800 dark:text-white">Tipo de Anúncio</h3>
                        @if($canChangeType)
                        <button type="button" @click="editing = !editing"
                            class="text-xs text-primary-500 hover:text-primary-400 flex items-center gap-1">
                            <x-heroicon-o-pencil-square class="w-3.5 h-3.5" />
                            <span x-text="editing ? 'Cancelar' : 'Alterar'"></span>
                        </button>
                        @endif
                    </div>

                    <div x-show="!editing" class="text-sm">
                        <span class="font-semibold {{ $typeInfo['color'] }}">{{ $typeInfo['label'] }}</span>
                        <p class="text-xs text-gray-400 dark:text-zinc-500 mt-0.5 font-mono">{{ $currentListingType }}</p>
                        @if(!$canChangeType)
                        <p class="text-xs text-gray-400 dark:text-zinc-500 mt-2">Nenhuma alteração disponível para este anúncio.</p>
                        @endif
                    </div>

                    @if($canChangeType)
                    <form x-show="editing" x-cloak method="POST"
                          action="{{ route('listings.update-listing-type', $listing) }}">
                        @csrf
                        <div class="space-y-3">
                            <div>
                                <label class="text-xs text-gray-500 dark:text-zinc-400 mb-1.5 block">
                                    Tipos disponíveis para este anúncio
                                </label>
                                <select name="listing_type_id" class="form-input text-sm">
                                    @foreach($availableListingTypes as $lt)
                                    @php
                                        $ltLabel = $listingTypeLabels[$lt['id']] ?? ['label' => $lt['name'] ?? $lt['id']];
                                    @endphp
                                    <option value="{{ $lt['id'] }}" {{ $currentListingType === $lt['id'] ? 'selected' : '' }}>
                                        {{ $ltLabel['label'] }} ({{ $lt['id'] }})
                                        {{ $currentListingType === $lt['id'] ? '— atual' : '' }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <p class="text-xs text-amber-600 dark:text-amber-400 flex items-start gap-1">
                                <x-heroicon-o-information-circle class="w-3.5 h-3.5 flex-shrink-0 mt-0.5" />
                                A troca entre Clássico e Premium é gratuita via API. Para Premium, é necessário ter pacote contratado no ML.
                            </p>
                            <button type="submit" class="btn-primary btn-sm text-xs w-full">
                                <x-heroicon-o-arrow-path class="w-3.5 h-3.5" />
                                Atualizar tipo
                            </button>
                        </div>
                    </form>
                    @endif
                </div>
            </x-ui.card>
            @endif

            {{-- Frete (editável) --}}
            @if(!empty($live['shipping']))
            <x-ui.card>
                @php
                    $shipping = $live['shipping'];
                    $isMandatoryFreeShipping = in_array('mandatory_free_shipping', $shipping['tags'] ?? []);
                    $dims = $shipping['dimensions'] ?? null;
                    $currentHandlingTime = isset($shipping['handling_time']) && $shipping['handling_time'] > 0
                        ? (int) $shipping['handling_time']
                        : null;
                @endphp
                <div x-data="{ editing: false }">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-sm font-semibold text-gray-800 dark:text-white">Frete</h3>
                        <button type="button" @click="editing = !editing"
                            class="text-xs text-primary-500 hover:text-primary-400 flex items-center gap-1">
                            <x-heroicon-o-pencil-square class="w-3.5 h-3.5" />
                            <span x-text="editing ? 'Cancelar' : 'Editar'"></span>
                        </button>
                    </div>

                    {{-- Display mode --}}
                    <div x-show="!editing" class="space-y-2 text-sm">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-500 dark:text-zinc-400">Modo</span>
                            <span class="text-xs font-mono">
                                {{ match($shipping['mode'] ?? '') {
                                    'me2'           => 'Mercado Envios',
                                    'me1'           => 'Mercado Envios 1',
                                    'custom'        => 'Personalizado',
                                    'not_specified' => 'Não especificado',
                                    default         => $shipping['mode'] ?? '—',
                                } }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-500 dark:text-zinc-400">Frete Grátis</span>
                            <x-ui.badge :color="($shipping['free_shipping'] ?? false) ? 'success' : 'neutral'">
                                {{ ($shipping['free_shipping'] ?? false) ? 'Sim' : 'Não' }}
                            </x-ui.badge>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-500 dark:text-zinc-400">Retirada</span>
                            <x-ui.badge :color="($shipping['local_pick_up'] ?? false) ? 'success' : 'neutral'">
                                {{ ($shipping['local_pick_up'] ?? false) ? 'Sim' : 'Não' }}
                            </x-ui.badge>
                        </div>
                        @if($currentHandlingTime !== null)
                        <div class="flex justify-between items-center">
                            <span class="text-gray-500 dark:text-zinc-400">Prazo disponib.</span>
                            <span class="text-xs">{{ $currentHandlingTime }} dia{{ $currentHandlingTime !== 1 ? 's' : '' }}</span>
                        </div>
                        @endif
                        @if($dims)
                        <div class="flex justify-between items-center">
                            <span class="text-gray-500 dark:text-zinc-400">Dimensões</span>
                            <span class="text-xs font-mono">
                                {{ $dims['width'] ?? '?' }}×{{ $dims['height'] ?? '?' }}×{{ $dims['length'] ?? '?' }}cm · {{ $dims['weight'] ?? '?' }}g
                            </span>
                        </div>
                        @endif
                        @if($isMandatoryFreeShipping)
                        <p class="text-xs text-amber-600 dark:text-amber-400 flex items-center gap-1 pt-1">
                            <x-heroicon-o-information-circle class="w-3 h-3" />
                            Frete grátis obrigatório pelo ML (preço acima do limite).
                        </p>
                        @endif
                    </div>

                    {{-- Edit mode --}}
                    <form x-show="editing" x-cloak method="POST"
                          action="{{ route('listings.update-shipping', $listing) }}"
                          class="space-y-3">
                        @csrf
                        @php
                            $shippingMode = $shipping['mode'] ?? 'me2';
                            $isME2        = $shippingMode === 'me2';
                            $isME1        = $shippingMode === 'me1';
                        @endphp
                        <input type="hidden" name="shipping_mode" value="{{ $shippingMode }}">

                        <div class="flex items-center justify-between">
                            <label class="text-xs text-gray-600 dark:text-zinc-400">Frete Grátis</label>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="hidden" name="free_shipping" value="0">
                                <input type="checkbox" name="free_shipping" value="1" class="sr-only peer"
                                    {{ ($shipping['free_shipping'] ?? false) ? 'checked' : '' }}
                                    {{ $isMandatoryFreeShipping ? 'disabled' : '' }}>
                                <div class="w-9 h-5 bg-gray-200 dark:bg-zinc-700 rounded-full peer
                                            peer-checked:bg-primary-500 after:content-[''] after:absolute
                                            after:top-0.5 after:left-0.5 after:bg-white after:rounded-full
                                            after:h-4 after:w-4 after:transition-all
                                            peer-checked:after:translate-x-4"></div>
                            </label>
                        </div>

                        <div class="flex items-center justify-between">
                            <label class="text-xs text-gray-600 dark:text-zinc-400">Retirada na Mão</label>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="hidden" name="local_pick_up" value="0">
                                <input type="checkbox" name="local_pick_up" value="1" class="sr-only peer"
                                    {{ ($shipping['local_pick_up'] ?? false) ? 'checked' : '' }}>
                                <div class="w-9 h-5 bg-gray-200 dark:bg-zinc-700 rounded-full peer
                                            peer-checked:bg-primary-500 after:content-[''] after:absolute
                                            after:top-0.5 after:left-0.5 after:bg-white after:rounded-full
                                            after:h-4 after:w-4 after:transition-all
                                            peer-checked:after:translate-x-4"></div>
                            </label>
                        </div>

                        {{-- handling_time: NOT in ML's list of updatable fields via PUT --}}
                        <div class="flex items-start gap-2 text-xs bg-amber-50 dark:bg-amber-900/10 border border-amber-200 dark:border-amber-700/30 rounded-lg p-3">
                            <x-heroicon-o-information-circle class="w-4 h-4 flex-shrink-0 text-amber-500 mt-0.5" />
                            <div>
                                <p class="font-medium text-amber-700 dark:text-amber-400 mb-0.5">Prazo de disponibilidade</p>
                                <p class="text-amber-600 dark:text-amber-500 leading-relaxed">
                                    @if($isFulfillment)
                                        Gerenciado automaticamente pelo Fulfillment ML. Não pode ser alterado.
                                    @else
                                        O Mercado Livre <strong>não permite</strong> alterar o prazo de disponibilidade (<code>handling_time</code>) via API para anúncios ativos.
                                        Para alterar, acesse: <a href="{{ $live['permalink'] ?? '#' }}" target="_blank" class="underline">ML → Meus Anúncios → Editar</a>.
                                    @endif
                                </p>
                            </div>
                        </div>

                        {{-- Dimensões: apenas ME1 aceita via API --}}
                        <div class="pt-2 border-t border-gray-100 dark:border-zinc-800">
                            @if($isME1)
                                <p class="text-xs text-gray-500 dark:text-zinc-400 mb-2">
                                    Dimensões <span class="text-gray-400">(ME1 — formato: alt × larg × comp, peso)</span>
                                </p>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="text-[10px] text-gray-400 dark:text-zinc-600">Alt. (cm)</label>
                                        <input type="number" name="shipping_height" step="1" min="0"
                                            value="{{ $dims['height'] ?? '' }}" class="form-input text-sm">
                                    </div>
                                    <div>
                                        <label class="text-[10px] text-gray-400 dark:text-zinc-600">Larg. (cm)</label>
                                        <input type="number" name="shipping_width" step="1" min="0"
                                            value="{{ $dims['width'] ?? '' }}" class="form-input text-sm">
                                    </div>
                                    <div>
                                        <label class="text-[10px] text-gray-400 dark:text-zinc-600">Comp. (cm)</label>
                                        <input type="number" name="shipping_length" step="1" min="0"
                                            value="{{ $dims['length'] ?? '' }}" class="form-input text-sm">
                                    </div>
                                    <div>
                                        <label class="text-[10px] text-gray-400 dark:text-zinc-600">Peso (kg)</label>
                                        <input type="number" name="shipping_weight" step="0.001" min="0"
                                            value="{{ isset($dims['weight']) ? round($dims['weight']/1000, 3) : '' }}"
                                            class="form-input text-sm">
                                    </div>
                                </div>
                            @else
                                <div class="flex items-start gap-2 text-xs text-gray-400 dark:text-zinc-500 bg-gray-50 dark:bg-zinc-800/50 rounded-lg p-3">
                                    <x-heroicon-o-information-circle class="w-4 h-4 flex-shrink-0 mt-0.5" />
                                    <div>
                                        <p class="font-medium text-gray-500 dark:text-zinc-400 mb-0.5">Dimensões não editáveis via API</p>
                                        <p class="leading-relaxed">
                                            Este anúncio usa <strong>{{ $isME2 ? 'Mercado Envios 2 (ME2)' : ucfirst($shippingMode) }}</strong>.
                                            @if($isME2)
                                                No ME2, as dimensões são calculadas automaticamente pelo Mercado Livre e não podem ser alteradas via API.
                                                Para ajustar, acesse o painel do ML → "Meus Anúncios" → edite diretamente.
                                            @endif
                                        </p>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <button type="submit" class="btn-primary btn-sm text-xs w-full">
                            <x-heroicon-o-cloud-arrow-up class="w-3.5 h-3.5" />
                            Salvar configurações de envio
                        </button>
                    </form>
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

@if($aiConfigured)
<script>
(function () {
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    // ─── Improve with AI ──────────────────────────────────────────────────────
    const btnImprove        = document.getElementById('btn-ai-improve');
    const improveLoading    = document.getElementById('ai-improve-loading');
    const improveResult     = document.getElementById('ai-improve-result');
    const improveMessage    = document.getElementById('ai-improve-message');
    const improveDoneList   = document.getElementById('ai-improve-done-list');
    const improveDoneItems  = document.getElementById('ai-improve-done-items');
    const improveSkipList   = document.getElementById('ai-improve-skip-list');
    const improveSkipItems  = document.getElementById('ai-improve-skip-items');
    const improveErrList    = document.getElementById('ai-improve-err-list');
    const improveErrItems   = document.getElementById('ai-improve-err-items');
    const btnImproveReload  = document.getElementById('btn-ai-improve-reload');
    const improveGlobalErr  = document.getElementById('ai-improve-global-error');
    const improveGlobalErrT = document.getElementById('ai-improve-global-error-text');

    if (btnImprove) {
        btnImprove.addEventListener('click', async () => {
            // Reset state
            btnImprove.disabled = true;
            improveLoading.classList.remove('hidden');
            improveResult.classList.add('hidden');
            improveGlobalErr.classList.add('hidden');
            improveDoneItems.innerHTML = '';
            improveSkipItems.innerHTML = '';
            improveErrItems.innerHTML  = '';

            try {
                const res = await fetch('{{ route('listings.ai-improve', $listing) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': CSRF,
                        'Accept': 'application/json',
                    },
                });

                const data = await res.json();

                if (data.error) {
                    improveGlobalErrT.textContent = data.error;
                    improveGlobalErr.classList.remove('hidden');
                    btnImprove.disabled = false;
                    return;
                }

                // Populate results
                improveMessage.textContent = data.message ?? '';
                improveResult.classList.remove('hidden');

                const improvements = data.improvements ?? [];
                const skipped      = data.skipped ?? [];
                const errors       = data.errors ?? [];

                if (improvements.length > 0) {
                    improvements.forEach(item => {
                        const li = document.createElement('li');
                        li.className = 'flex items-start gap-1.5 text-xs text-gray-700 dark:text-zinc-300 bg-emerald-50/60 dark:bg-emerald-900/10 rounded px-2 py-1';
                        li.innerHTML = `<span class="flex-shrink-0 mt-0.5">${item.icon ?? '✅'}</span><div><strong>${item.label}</strong>: ${item.detail}</div>`;
                        improveDoneItems.appendChild(li);
                    });
                    improveDoneList.classList.remove('hidden');
                    btnImproveReload.classList.remove('hidden');
                }

                if (skipped.length > 0) {
                    skipped.forEach(item => {
                        const li = document.createElement('li');
                        li.className = 'flex items-start gap-1.5 text-xs text-gray-600 dark:text-zinc-400 bg-amber-50/40 dark:bg-amber-900/10 rounded px-2 py-1';
                        li.innerHTML = `<span class="flex-shrink-0 mt-0.5">⚠️</span><div><strong>${item.label}</strong>: ${item.reason}</div>`;
                        improveSkipItems.appendChild(li);
                    });
                    improveSkipList.classList.remove('hidden');
                }

                if (errors.length > 0) {
                    errors.forEach(msg => {
                        const li = document.createElement('li');
                        li.className = 'text-xs text-red-500 bg-red-50/40 dark:bg-red-900/10 rounded px-2 py-1';
                        li.textContent = '❌ ' + msg;
                        improveErrItems.appendChild(li);
                    });
                    improveErrList.classList.remove('hidden');
                }

            } catch (e) {
                improveGlobalErrT.textContent = 'Erro ao conectar com o servidor. Tente novamente.';
                improveGlobalErr.classList.remove('hidden');
                btnImprove.disabled = false;
            } finally {
                improveLoading.classList.add('hidden');
            }
        });
    }

    if (btnImproveReload) {
        btnImproveReload.addEventListener('click', () => window.location.reload());
    }

    // ─── Description AI ───────────────────────────────────────────────────────
    const btnGenDesc    = document.getElementById('btn-gen-desc');
    const aiDescPanel   = document.getElementById('ai-desc-panel');
    const btnRunAiDesc  = document.getElementById('btn-run-ai-desc');
    const descBtnText   = document.getElementById('ai-desc-btn-text');
    const descSpinner   = document.getElementById('ai-desc-spinner');
    const descStatus    = document.getElementById('ai-desc-status');
    const descError     = document.getElementById('ai-desc-error');
    const descErrorText = document.getElementById('ai-desc-error-text');
    const descTextarea  = document.getElementById('listing-description');

    if (btnGenDesc && aiDescPanel) {
        btnGenDesc.addEventListener('click', () => {
            const visible = !aiDescPanel.classList.contains('hidden');
            aiDescPanel.classList.toggle('hidden', visible);
            btnGenDesc.classList.toggle('btn-primary', !visible);
            btnGenDesc.classList.toggle('btn-secondary', visible);
        });
    }

    if (btnRunAiDesc) {
        btnRunAiDesc.addEventListener('click', async () => {
            descBtnText.textContent = 'Gerando...';
            btnRunAiDesc.disabled = true;
            descSpinner.classList.remove('hidden');
            descStatus.classList.remove('hidden');
            descError.classList.add('hidden');

            try {
                const res = await fetch('{{ route('listings.ai-description', $listing) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': CSRF,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        existing_description: descTextarea?.value ?? '',
                    }),
                });

                const data = await res.json();

                if (data.error) {
                    descErrorText.textContent = data.error;
                    descError.classList.remove('hidden');
                } else if (data.description) {
                    descTextarea.value = data.description;
                    // Visual feedback: flash the textarea
                    descTextarea.classList.add('ring-2', 'ring-purple-400');
                    setTimeout(() => descTextarea.classList.remove('ring-2', 'ring-purple-400'), 2000);
                    // Hide panel after success
                    aiDescPanel.classList.add('hidden');
                    btnGenDesc.classList.remove('btn-primary');
                    btnGenDesc.classList.add('btn-secondary');
                }
            } catch (e) {
                descErrorText.textContent = 'Erro ao conectar com a IA. Tente novamente.';
                descError.classList.remove('hidden');
            } finally {
                descBtnText.textContent = 'Gerar agora';
                btnRunAiDesc.disabled = false;
                descSpinner.classList.add('hidden');
                descStatus.classList.add('hidden');
            }
        });
    }

    // ─── Image AI ─────────────────────────────────────────────────────────────
    const btnGenImg      = document.getElementById('btn-gen-img');
    const aiImgPanel     = document.getElementById('ai-img-panel');
    const btnRunAiImg    = document.getElementById('btn-run-ai-img');
    const imgBtnText     = document.getElementById('ai-img-btn-text');
    const imgSpinner     = document.getElementById('ai-img-spinner');
    const imgStatus      = document.getElementById('ai-img-status');
    const imgError       = document.getElementById('ai-img-error');
    const imgErrorText   = document.getElementById('ai-img-error-text');
    const imgResult      = document.getElementById('ai-img-result');
    const imgPreview     = document.getElementById('ai-img-preview');
    const btnUploadImg   = document.getElementById('btn-ai-img-upload');
    const imgUploadText  = document.getElementById('ai-img-upload-text');
    const imgUploadStatus= document.getElementById('ai-img-upload-status');
    const btnRegenImg    = document.getElementById('btn-ai-img-regen');
    const imgPrompt      = document.getElementById('ai-img-prompt');

    let lastGeneratedUrl = null;

    if (btnGenImg && aiImgPanel) {
        btnGenImg.addEventListener('click', () => {
            const visible = !aiImgPanel.classList.contains('hidden');
            aiImgPanel.classList.toggle('hidden', visible);
            btnGenImg.classList.toggle('btn-primary', !visible);
            btnGenImg.classList.toggle('btn-secondary', visible);
        });
    }

    async function runImageGeneration() {
        imgBtnText.textContent = 'Gerando...';
        btnRunAiImg.disabled = true;
        imgSpinner.classList.remove('hidden');
        imgStatus.classList.remove('hidden');
        imgError.classList.add('hidden');
        imgResult.classList.add('hidden');
        lastGeneratedUrl = null;

        try {
            const res = await fetch('{{ route('listings.ai-image', $listing) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    prompt: imgPrompt?.value ?? '',
                    upload_to_ml: false,
                }),
            });

            const data = await res.json();

            if (data.error) {
                imgErrorText.textContent = data.error;
                imgError.classList.remove('hidden');
            } else if (data.url) {
                lastGeneratedUrl = data.url;
                imgPreview.src = data.url;
                imgResult.classList.remove('hidden');
                imgUploadText.textContent = 'Adicionar ao anúncio no ML';
                imgUploadStatus.classList.add('hidden');
                imgUploadStatus.className = 'hidden text-xs font-medium';
            }
        } catch (e) {
            imgErrorText.textContent = 'Erro ao conectar com a IA. Tente novamente.';
            imgError.classList.remove('hidden');
        } finally {
            imgBtnText.textContent = 'Gerar imagem';
            btnRunAiImg.disabled = false;
            imgSpinner.classList.add('hidden');
            imgStatus.classList.add('hidden');
        }
    }

    if (btnRunAiImg) {
        btnRunAiImg.addEventListener('click', runImageGeneration);
    }

    if (btnRegenImg) {
        btnRegenImg.addEventListener('click', runImageGeneration);
    }

    if (btnUploadImg) {
        btnUploadImg.addEventListener('click', async () => {
            if (! lastGeneratedUrl) return;

            imgUploadText.textContent = 'Enviando para o ML...';
            btnUploadImg.disabled = true;

            try {
                const res = await fetch('{{ route('listings.ai-image', $listing) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': CSRF,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        prompt: imgPrompt?.value ?? '',
                        upload_to_ml: true,
                    }),
                });

                const data = await res.json();

                if (data.error) {
                    imgUploadStatus.textContent = '❌ ' + data.error;
                    imgUploadStatus.className = 'text-xs font-medium text-red-500';
                    imgUploadStatus.classList.remove('hidden');
                } else if (data.uploaded) {
                    imgUploadStatus.textContent = '✅ Imagem adicionada ao anúncio! Recarregue a página para ver.';
                    imgUploadStatus.className = 'text-xs font-medium text-emerald-600';
                    imgUploadStatus.classList.remove('hidden');
                    btnUploadImg.disabled = true;
                } else {
                    const msg = data.upload_error ?? 'Erro desconhecido ao enviar.';
                    imgUploadStatus.textContent = '⚠️ ' + msg;
                    imgUploadStatus.className = 'text-xs font-medium text-amber-600';
                    imgUploadStatus.classList.remove('hidden');
                }
            } catch (e) {
                imgUploadStatus.textContent = '❌ Erro ao enviar. Tente novamente.';
                imgUploadStatus.className = 'text-xs font-medium text-red-500';
                imgUploadStatus.classList.remove('hidden');
            } finally {
                if (! imgUploadStatus.textContent.includes('✅')) {
                    imgUploadText.textContent = 'Adicionar ao anúncio no ML';
                    btnUploadImg.disabled = false;
                }
            }
        });
    }
})();
</script>
@endif

</x-app-layout>
