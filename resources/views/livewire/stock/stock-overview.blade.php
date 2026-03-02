<div>
    {{-- Filters --}}
    <div class="card p-4 mb-6">
        <div class="flex flex-col sm:flex-row gap-4">
            <div class="flex-1">
                <div class="relative">
                    <x-heroicon-o-magnifying-glass class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Buscar produto..."
                           class="form-input pl-10">
                </div>
            </div>
            <div class="flex gap-3">
                <select wire:model.live="filter" class="form-input w-40">
                    <option value="all">Todos</option>
                    <option value="low">Estoque Baixo</option>
                    <option value="out">Sem Estoque</option>
                </select>
                <select wire:model.live="locationId" class="form-input w-48">
                    <option value="">Todos Locais</option>
                    @foreach($locations as $loc)
                        <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    {{-- Table --}}
    @if($items->isEmpty())
        <x-ui.card>
            <x-ui.empty-state
                title="Nenhum item de estoque"
                description="Itens de estoque serao criados ao cadastrar produtos com estoque inicial.">
                <x-slot name="icon">
                    <x-heroicon-o-archive-box class="w-8 h-8 text-gray-400 dark:text-zinc-500" />
                </x-slot>
            </x-ui.empty-state>
        </x-ui.card>
    @else
        <x-ui.card :padding="false">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Produto</th>
                        <th>Variante</th>
                        <th>Local</th>
                        <th class="text-center">Quantidade</th>
                        <th class="text-center">Reservado</th>
                        <th class="text-center">Disponivel</th>
                        <th class="text-center">Minimo</th>
                        <th>Status</th>
                        <th class="text-right">Acoes</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $item)
                    <tr wire:key="stock-{{ $item->id }}">
                        <td>
                            <p class="font-medium text-gray-900 dark:text-white">{{ $item->product->name }}</p>
                            <p class="text-xs text-gray-500 dark:text-zinc-400 font-mono">{{ $item->product->sku }}</p>
                        </td>
                        <td>{{ $item->variant?->name ?? '-' }}</td>
                        <td>{{ $item->location->name }}</td>
                        <td class="text-center font-medium">{{ $item->quantity }}</td>
                        <td class="text-center">{{ $item->reserved_quantity }}</td>
                        <td class="text-center font-medium {{ $item->available_quantity <= 0 ? 'text-red-600 dark:text-red-400' : '' }}">
                            {{ $item->available_quantity }}
                        </td>
                        <td class="text-center">{{ $item->min_quantity }}</td>
                        <td>
                            @if($item->available_quantity <= 0)
                                <x-ui.badge color="danger">Sem Estoque</x-ui.badge>
                            @elseif($item->is_low_stock)
                                <x-ui.badge color="warning">Estoque Baixo</x-ui.badge>
                            @else
                                <x-ui.badge color="success">OK</x-ui.badge>
                            @endif
                        </td>
                        <td class="text-right">
                            <button wire:click="openAdjustment({{ $item->id }})" class="btn-ghost btn-xs">
                                <x-heroicon-o-adjustments-horizontal class="w-4 h-4" />
                                Ajustar
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            @if($items->hasPages())
            <div class="px-4 py-3 border-t border-gray-200 dark:border-zinc-700">
                {{ $items->links() }}
            </div>
            @endif
        </x-ui.card>
    @endif

    {{-- Adjustment Modal --}}
    @if($showAdjustModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" x-data>
        <div class="fixed inset-0 bg-black/50" wire:click="$set('showAdjustModal', false)"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative w-full max-w-md bg-white dark:bg-zinc-800 rounded-xl shadow-xl border border-gray-200 dark:border-zinc-700">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-zinc-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Ajustar Estoque</h3>
                    <button wire:click="$set('showAdjustModal', false)" class="text-gray-400 hover:text-gray-600">
                        <x-heroicon-o-x-mark class="w-5 h-5" />
                    </button>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <label class="form-label">Tipo de Movimentacao</label>
                        <select wire:model="adjustType" class="form-input">
                            <option value="adjustment">Ajuste</option>
                            <option value="in">Entrada</option>
                            <option value="out">Saida</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Quantidade (positivo = entrada, negativo = saida)</label>
                        <input type="number" wire:model="adjustQuantity" class="form-input">
                        @error('adjustQuantity') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="form-label">Motivo *</label>
                        <input type="text" wire:model="adjustReason" class="form-input" placeholder="Ex: Conferencia de inventario">
                        @error('adjustReason') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="px-6 py-4 border-t border-gray-200 dark:border-zinc-700 flex justify-end gap-3">
                    <button wire:click="$set('showAdjustModal', false)" class="btn-secondary">Cancelar</button>
                    <button wire:click="saveAdjustment" class="btn-primary">Salvar Ajuste</button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
