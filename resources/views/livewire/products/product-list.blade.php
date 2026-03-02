<div>
    {{-- Filters --}}
    <div class="card p-4 mb-6">
        <div class="flex flex-col sm:flex-row gap-4">
            <div class="flex-1">
                <div class="relative">
                    <x-heroicon-o-magnifying-glass class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-zinc-500" />
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Buscar por nome, SKU ou EAN..."
                           class="form-input pl-10">
                </div>
            </div>
            <div class="flex gap-3">
                <select wire:model.live="status" class="form-input w-40">
                    <option value="">Todos Status</option>
                    @foreach($statuses as $s)
                        <option value="{{ $s->value }}">{{ $s->label() }}</option>
                    @endforeach
                </select>
                <select wire:model.live="type" class="form-input w-40">
                    <option value="">Todos Tipos</option>
                    @foreach($types as $t)
                        <option value="{{ $t->value }}">{{ $t->label() }}</option>
                    @endforeach
                </select>
                <select wire:model.live="category" class="form-input w-40">
                    <option value="">Todas Categorias</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    {{-- Table --}}
    @if($products->isEmpty())
        <x-ui.card>
            <x-ui.empty-state
                title="Nenhum produto encontrado"
                description="{{ $search ? 'Tente refinar sua busca.' : 'Cadastre seu primeiro produto para comecar.' }}">
                <x-slot name="icon">
                    <x-heroicon-o-cube class="w-8 h-8 text-gray-400 dark:text-zinc-500" />
                </x-slot>
                @unless($search)
                <x-slot name="action">
                    <a href="{{ route('products.create') }}" class="btn-primary">
                        <x-heroicon-s-plus class="w-4 h-4" />
                        Novo Produto
                    </a>
                </x-slot>
                @endunless
            </x-ui.empty-state>
        </x-ui.card>
    @else
        <x-ui.card :padding="false">
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="w-12"></th>
                        <th>
                            <button wire:click="sortBy('name')" class="flex items-center gap-1 hover:text-gray-700 dark:hover:text-zinc-200">
                                Produto
                                @if($sortField === 'name')
                                    <x-heroicon-s-chevron-up class="w-3 h-3 {{ $sortDirection === 'desc' ? 'rotate-180' : '' }}" />
                                @endif
                            </button>
                        </th>
                        <th>
                            <button wire:click="sortBy('sku')" class="flex items-center gap-1 hover:text-gray-700 dark:hover:text-zinc-200">
                                SKU
                                @if($sortField === 'sku')
                                    <x-heroicon-s-chevron-up class="w-3 h-3 {{ $sortDirection === 'desc' ? 'rotate-180' : '' }}" />
                                @endif
                            </button>
                        </th>
                        <th>Tipo</th>
                        <th>
                            <button wire:click="sortBy('price')" class="flex items-center gap-1 hover:text-gray-700 dark:hover:text-zinc-200">
                                Preco
                                @if($sortField === 'price')
                                    <x-heroicon-s-chevron-up class="w-3 h-3 {{ $sortDirection === 'desc' ? 'rotate-180' : '' }}" />
                                @endif
                            </button>
                        </th>
                        <th>Estoque</th>
                        <th>Status</th>
                        <th class="text-right">Acoes</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($products as $product)
                    <tr wire:key="product-{{ $product->id }}">
                        <td>
                            @if($product->primaryImage)
                                <img src="{{ asset('storage/' . $product->primaryImage->path) }}" alt="" class="w-10 h-10 rounded-lg object-cover">
                            @else
                                <div class="w-10 h-10 rounded-lg bg-gray-100 dark:bg-zinc-700 flex items-center justify-center">
                                    <x-heroicon-o-photo class="w-5 h-5 text-gray-400 dark:text-zinc-500" />
                                </div>
                            @endif
                        </td>
                        <td>
                            <div>
                                <a href="{{ route('products.edit', $product) }}" class="font-medium text-gray-900 dark:text-white hover:text-primary-600 dark:hover:text-primary-400">
                                    {{ $product->name }}
                                </a>
                                @if($product->category)
                                <p class="text-xs text-gray-500 dark:text-zinc-400">{{ $product->category->name }}</p>
                                @endif
                            </div>
                        </td>
                        <td class="font-mono text-sm">{{ $product->sku }}</td>
                        <td>
                            <x-ui.badge color="info">{{ $product->type->label() }}</x-ui.badge>
                        </td>
                        <td class="font-medium">R$ {{ number_format($product->price, 2, ',', '.') }}</td>
                        <td>
                            @php $stock = $product->total_stock; @endphp
                            <span class="{{ $stock <= 0 ? 'text-red-600 dark:text-red-400 font-medium' : '' }}">
                                {{ $stock }}
                            </span>
                        </td>
                        <td>
                            <x-ui.badge :color="$product->status->color()">{{ $product->status->label() }}</x-ui.badge>
                        </td>
                        <td class="text-right">
                            <div class="flex items-center justify-end gap-1" x-data="{ open: false }">
                                <a href="{{ route('products.edit', $product) }}" class="btn-ghost btn-xs">
                                    <x-heroicon-o-pencil-square class="w-4 h-4" />
                                </a>
                                <div class="relative">
                                    <button @click="open = !open" class="btn-ghost btn-xs">
                                        <x-heroicon-o-ellipsis-vertical class="w-4 h-4" />
                                    </button>
                                    <div x-show="open" @click.away="open = false" x-transition
                                         class="absolute right-0 mt-1 w-40 bg-white dark:bg-zinc-800 rounded-lg shadow-lg border border-gray-200 dark:border-zinc-700 py-1 z-10">
                                        <button wire:click="duplicateProduct({{ $product->id }})" @click="open = false"
                                                class="w-full flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-zinc-300 hover:bg-gray-100 dark:hover:bg-zinc-700">
                                            <x-heroicon-o-document-duplicate class="w-4 h-4" />
                                            Duplicar
                                        </button>
                                        <button wire:click="deleteProduct({{ $product->id }})" wire:confirm="Tem certeza que deseja remover este produto?" @click="open = false"
                                                class="w-full flex items-center gap-2 px-3 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-gray-100 dark:hover:bg-zinc-700">
                                            <x-heroicon-o-trash class="w-4 h-4" />
                                            Remover
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            @if($products->hasPages())
            <div class="px-4 py-3 border-t border-gray-200 dark:border-zinc-700">
                {{ $products->links() }}
            </div>
            @endif
        </x-ui.card>
    @endif
</div>
