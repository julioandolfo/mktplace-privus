<div>
    {{-- Search to add component --}}
    <div class="mb-4 relative">
        <label class="form-label">Adicionar produto ao kit</label>
        <div class="relative">
            <x-heroicon-o-magnifying-glass class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
            <input type="text" wire:model.live.debounce.300ms="searchComponent"
                   placeholder="Buscar produto por nome ou SKU..."
                   class="form-input pl-10">
        </div>

        @if(count($searchResults) > 0)
        <div class="absolute z-20 w-full mt-1 bg-white dark:bg-zinc-800 rounded-lg shadow-lg border border-gray-200 dark:border-zinc-700 max-h-60 overflow-y-auto">
            @foreach($searchResults as $result)
            <button type="button" wire:click="addComponent({{ $result['id'] }})"
                    class="w-full flex items-center justify-between px-4 py-3 hover:bg-gray-50 dark:hover:bg-zinc-700 text-left">
                <div>
                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $result['name'] }}</p>
                    <p class="text-xs text-gray-500 dark:text-zinc-400">SKU: {{ $result['sku'] }}</p>
                </div>
                <span class="text-sm text-gray-600 dark:text-zinc-300">R$ {{ number_format($result['price'], 2, ',', '.') }}</span>
            </button>
            @endforeach
        </div>
        @endif
    </div>

    {{-- Components list --}}
    @if(count($components) > 0)
    <div class="space-y-3">
        @foreach($components as $index => $component)
        <div wire:key="kit-{{ $index }}" class="flex items-center gap-4 p-3 rounded-lg border border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800/50">
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $component['name'] }}</p>
                <p class="text-xs text-gray-500 dark:text-zinc-400">SKU: {{ $component['sku'] }} | R$ {{ number_format($component['price'], 2, ',', '.') }} un.</p>
            </div>
            <div class="flex items-center gap-2">
                <label class="text-xs text-gray-500 dark:text-zinc-400">Qtd:</label>
                <input type="number" min="1"
                       value="{{ $component['quantity'] }}"
                       wire:change="updateQuantity({{ $index }}, $event.target.value)"
                       class="form-input w-20 text-center">
            </div>
            <span class="text-sm font-medium text-gray-700 dark:text-zinc-300 w-24 text-right">
                R$ {{ number_format($component['price'] * $component['quantity'], 2, ',', '.') }}
            </span>
            <button type="button" wire:click="removeComponent({{ $index }})" class="text-red-500 hover:text-red-700 flex-shrink-0">
                <x-heroicon-o-x-mark class="w-5 h-5" />
            </button>
        </div>
        @endforeach

        {{-- Total --}}
        <div class="flex items-center justify-between pt-3 border-t border-gray-200 dark:border-zinc-700">
            <span class="text-sm font-medium text-gray-700 dark:text-zinc-300">Custo total dos componentes:</span>
            <span class="text-lg font-bold text-gray-900 dark:text-white">R$ {{ number_format($this->kitTotal, 2, ',', '.') }}</span>
        </div>
    </div>
    @else
    <x-ui.empty-state
        title="Kit vazio"
        description="Busque e adicione produtos para compor este kit.">
        <x-slot name="icon">
            <x-heroicon-o-rectangle-group class="w-8 h-8 text-gray-400 dark:text-zinc-500" />
        </x-slot>
    </x-ui.empty-state>
    @endif
</div>
