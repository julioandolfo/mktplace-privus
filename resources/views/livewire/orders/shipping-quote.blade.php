<div class="space-y-4">

    {{-- Etiqueta já comprada --}}
    @if($existingLabel && !$purchased)
    <div class="flex items-center gap-3 p-3 bg-green-50 dark:bg-green-900/20 rounded-lg border border-green-200 dark:border-green-700">
        <x-heroicon-s-check-circle class="w-5 h-5 text-green-500 flex-shrink-0" />
        <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold text-green-800 dark:text-green-300">
                {{ $existingLabel->carrier }} — {{ $existingLabel->service }}
            </p>
            <p class="text-xs text-green-600 dark:text-green-400">
                R$ {{ number_format($existingLabel->cost, 2, ',', '.') }} · {{ $existingLabel->tracking_code ?? 'Sem rastreio' }}
            </p>
        </div>
        @if($existingLabel->label_url)
        <a href="{{ $existingLabel->label_url }}" target="_blank" class="btn-secondary btn-xs flex-shrink-0">
            <x-heroicon-o-printer class="w-3.5 h-3.5" />
            Imprimir
        </a>
        @endif
    </div>
    @endif

    {{-- Dimensões do pacote --}}
    @unless($existingLabel && !$purchased)
    <div class="grid grid-cols-2 gap-2">
        <div>
            <label class="text-xs text-gray-500 dark:text-zinc-400 block mb-1">Peso (kg)</label>
            <input type="number" wire:model.live.debounce.500ms="weight" step="0.01" min="0.01"
                   class="form-input text-sm h-8 py-1">
        </div>
        <div>
            <label class="text-xs text-gray-500 dark:text-zinc-400 block mb-1">Largura (cm)</label>
            <input type="number" wire:model.live.debounce.500ms="width" step="1" min="1"
                   class="form-input text-sm h-8 py-1">
        </div>
        <div>
            <label class="text-xs text-gray-500 dark:text-zinc-400 block mb-1">Altura (cm)</label>
            <input type="number" wire:model.live.debounce.500ms="height" step="1" min="1"
                   class="form-input text-sm h-8 py-1">
        </div>
        <div>
            <label class="text-xs text-gray-500 dark:text-zinc-400 block mb-1">Comprimento (cm)</label>
            <input type="number" wire:model.live.debounce.500ms="length" step="1" min="1"
                   class="form-input text-sm h-8 py-1">
        </div>
    </div>

    <button wire:click="calculateQuote" wire:loading.attr="disabled"
            class="btn-secondary btn-sm w-full justify-center">
        <span wire:loading.remove wire:target="calculateQuote">
            <x-heroicon-o-calculator class="w-4 h-4" />
            Cotar Frete
        </span>
        <span wire:loading wire:target="calculateQuote">Calculando...</span>
    </button>
    @endunless

    {{-- Erro --}}
    @if($error)
    <div class="flex items-start gap-2 p-3 bg-red-50 dark:bg-red-900/20 rounded-lg border border-red-200 dark:border-red-700">
        <x-heroicon-o-exclamation-triangle class="w-4 h-4 text-red-500 flex-shrink-0 mt-0.5" />
        <p class="text-xs text-red-600 dark:text-red-400">{{ $error }}</p>
    </div>
    @endif

    {{-- Lista de cotações --}}
    @if(!empty($quotes))
    <div class="space-y-2">
        <p class="text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Opções de Frete</p>
        @foreach($quotes as $key => $quote)
        @php
            $isSelected = $selectedQuoteKey == $key;
            $company    = $quote['company'] ?? [];
            $delivery   = $quote['delivery_range'] ?? [];
            $minDays    = $delivery['min'] ?? null;
            $maxDays    = $delivery['max'] ?? null;
        @endphp
        <button wire:click="selectQuote({{ $key }})"
                class="w-full text-left border rounded-lg p-3 transition-all {{ $isSelected
                    ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20'
                    : 'border-gray-200 dark:border-zinc-700 hover:border-gray-300 dark:hover:border-zinc-600' }}">
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-2 min-w-0">
                    @if(!empty($company['picture']))
                    <img src="{{ $company['picture'] }}" alt="{{ $company['name'] }}"
                         class="w-6 h-6 object-contain flex-shrink-0">
                    @endif
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                            {{ $company['name'] ?? '' }} — {{ $quote['name'] ?? '' }}
                        </p>
                        @if($minDays !== null)
                        <p class="text-xs text-gray-400">
                            Prazo: {{ $minDays }} – {{ $maxDays }} dias úteis
                        </p>
                        @endif
                    </div>
                </div>
                <div class="text-right flex-shrink-0">
                    <p class="font-bold text-primary-600 dark:text-primary-400">
                        R$ {{ number_format($quote['price'], 2, ',', '.') }}
                    </p>
                    @if($isSelected)
                        <x-heroicon-s-check-circle class="w-4 h-4 text-primary-500 ml-auto mt-0.5" />
                    @endif
                </div>
            </div>
        </button>
        @endforeach

        {{-- Botão comprar --}}
        @if($selectedQuoteKey !== null)
        @php $sel = $quotes[$selectedQuoteKey] ?? null; @endphp
        @if($sel)
        <button wire:click="purchaseLabel" wire:loading.attr="disabled"
                class="btn-primary w-full justify-center mt-2">
            <span wire:loading.remove wire:target="purchaseLabel">
                <x-heroicon-o-credit-card class="w-4 h-4" />
                Comprar {{ ($sel['company']['name'] ?? '') }} — R$ {{ number_format($sel['price'], 2, ',', '.') }}
            </span>
            <span wire:loading wire:target="purchaseLabel">Comprando...</span>
        </button>
        @endif
        @endif
    </div>
    @endif

    {{-- Sucesso pós-compra --}}
    @if($purchased && $existingLabel)
    <div class="flex items-start gap-3 p-4 bg-green-50 dark:bg-green-900/20 rounded-lg border border-green-400">
        <x-heroicon-s-check-circle class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" />
        <div class="flex-1">
            <p class="text-sm font-semibold text-green-800 dark:text-green-300">
                Etiqueta comprada com sucesso!
            </p>
            @if($existingLabel->label_url)
            <a href="{{ $existingLabel->label_url }}" target="_blank" class="btn-secondary btn-xs mt-2">
                <x-heroicon-o-printer class="w-3.5 h-3.5" />
                Imprimir etiqueta
            </a>
            @endif
        </div>
    </div>
    @endif
</div>
