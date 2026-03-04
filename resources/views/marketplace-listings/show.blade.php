<x-app-layout>
    <x-slot name="header">{{ $listing->title }}</x-slot>
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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Main --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Listing info --}}
            <x-ui.card title="Dados do Anuncio">
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="text-gray-500 dark:text-zinc-400">Titulo</span>
                        <p class="font-medium mt-0.5">{{ $listing->title }}</p>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-zinc-400">ID no Marketplace</span>
                        <p class="font-mono mt-0.5">{{ $listing->external_id }}</p>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-zinc-400">Preco</span>
                        <p class="font-mono font-medium mt-0.5">R$ {{ number_format($listing->price, 2, ',', '.') }}</p>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-zinc-400">Estoque Disponivel</span>
                        <p class="mt-0.5">{{ $listing->available_quantity ?? '—' }}</p>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-zinc-400">Status</span>
                        <div class="mt-0.5">
                            <x-ui.badge :color="$listing->status_color">
                                {{ match($listing->status) {
                                    'active'  => 'Ativo',
                                    'paused'  => 'Pausado',
                                    'closed'  => 'Encerrado',
                                    'deleted' => 'Deletado',
                                    default   => $listing->status,
                                } }}
                            </x-ui.badge>
                        </div>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-zinc-400">Conta</span>
                        <p class="mt-0.5">{{ $listing->marketplaceAccount?->account_name }}</p>
                    </div>
                    @if($listing->meta['ml_permalink'] ?? null)
                    <div class="col-span-2">
                        <span class="text-gray-500 dark:text-zinc-400">Link no Marketplace</span>
                        <a href="{{ $listing->meta['ml_permalink'] }}" target="_blank"
                           class="flex items-center gap-1 text-primary-500 hover:text-primary-400 mt-0.5 break-all">
                            {{ $listing->meta['ml_permalink'] }}
                            <x-heroicon-o-arrow-top-right-on-square class="w-3.5 h-3.5 flex-shrink-0" />
                        </a>
                    </div>
                    @endif
                </div>
            </x-ui.card>

            {{-- Product link --}}
            @if($listing->product)
            {{-- Linked --}}
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
                    <form method="POST" action="{{ route('listings.link-product', $listing) }}"
                          class="flex items-end gap-3">
                        @csrf
                        <input type="hidden" name="product_id" value="{{ $listing->product_id }}">
                        <div class="flex-1">
                            <label class="form-label">Quantidade por venda</label>
                            <input type="number" name="product_quantity" value="{{ $listing->product_quantity }}"
                                min="1" class="form-input" placeholder="Ex: 100 (para Kit 100un)">
                            <p class="text-xs text-gray-400 dark:text-zinc-500 mt-1">
                                Quantas unidades do produto sao descontadas do estoque a cada venda deste anuncio.
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
            {{-- Not linked --}}
            <x-ui.card title="Vincular Produto">
                <p class="text-sm text-gray-500 dark:text-zinc-400 mb-4">
                    Este anuncio ainda nao esta vinculado a nenhum produto do catalogo interno.
                    Vincule um produto existente ou crie um novo a partir deste anuncio.
                </p>

                {{-- Link existing --}}
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
                    <span class="absolute -top-2.5 left-1/2 -translate-x-1/2 px-3 bg-white dark:bg-zinc-900 text-xs text-gray-400 dark:text-zinc-500">
                        ou
                    </span>
                </div>

                {{-- Create new product --}}
                <details class="group">
                    <summary class="cursor-pointer text-sm font-medium text-primary-400 hover:text-primary-300 list-none flex items-center gap-1.5">
                        <x-heroicon-o-plus-circle class="w-4 h-4" />
                        Criar novo produto a partir deste anuncio
                        <x-heroicon-o-chevron-down class="w-4 h-4 group-open:rotate-180 transition-transform" />
                    </summary>
                    <form method="POST" action="{{ route('listings.create-product', $listing) }}"
                          class="mt-4 space-y-3">
                        @csrf
                        <div>
                            <label class="form-label">Nome do Produto *</label>
                            <input type="text" name="name" value="{{ $listing->title }}"
                                class="form-input" required>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="form-label">Preco (R$) *</label>
                                <input type="number" name="price" value="{{ $listing->price }}"
                                    step="0.01" min="0" class="form-input" required>
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

        {{-- Sidebar --}}
        <div class="space-y-6">
            <x-ui.card title="Sincronizacao">
                <div class="text-sm space-y-2">
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-zinc-400">Criado em</span>
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
