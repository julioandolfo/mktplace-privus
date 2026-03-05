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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- ═══ Main Column ══════════════════════════════════════════════════ --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Desempenho --}}
            <x-ui.card title="Desempenho de Vendas">
                {{-- KPIs --}}
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

                {{-- Bar chart CSS --}}
                @if($salesStats->count() > 0)
                @php
                    $maxRevenue = $salesStats->max('revenue') ?: 1;
                @endphp
                <div>
                    <div class="text-xs font-medium text-gray-500 dark:text-zinc-400 mb-3">Receita por mês (R$)</div>
                    <div class="flex items-end gap-1.5 h-28">
                        @foreach($salesStats as $stat)
                        @php
                            $pct = round(($stat->revenue / $maxRevenue) * 100);
                            $month = \Carbon\Carbon::createFromFormat('Y-m', $stat->month)->translatedFormat('M/y');
                        @endphp
                        <div class="flex-1 flex flex-col items-center gap-1 min-w-0 group" title="{{ $month }}: R$ {{ number_format($stat->revenue, 2, ',', '.') }} ({{ $stat->qty }} venda{{ $stat->qty != 1 ? 's' : '' }})">
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
                        <label class="form-label">Titulo <span class="text-gray-400 text-xs font-normal">(max 60 caracteres)</span></label>
                        <input type="text" name="title" maxlength="60"
                            value="{{ old('title', $liveData['title'] ?? $listing->title) }}"
                            class="form-input @error('title') border-red-500 @enderror" required>
                        @error('title')
                            <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="form-label">Preco (R$)</label>
                            <input type="number" name="price" step="0.01" min="0"
                                value="{{ old('price', $liveData['price'] ?? $listing->price) }}"
                                class="form-input @error('price') border-red-500 @enderror" required>
                            @error('price')
                                <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="form-label">Estoque Disponivel</label>
                            <input type="number" name="available_quantity" min="0"
                                value="{{ old('available_quantity', $liveData['available_quantity'] ?? $listing->available_quantity) }}"
                                class="form-input @error('available_quantity') border-red-500 @enderror" required>
                            @error('available_quantity')
                                <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div>
                        <label class="form-label">Prazo de Disponibilidade</label>
                        @php
                            $currentHandling = old('handling_time', $liveData['shipping']['handling_time'] ?? 0);
                            $handlingOptions = [
                                0 => 'Mesmo dia',
                                1 => '1 dia útil',
                                2 => '2 dias úteis',
                                3 => '3 dias úteis',
                                4 => '4 dias úteis',
                                5 => '5 dias úteis',
                            ];
                        @endphp
                        <select name="handling_time" class="form-input @error('handling_time') border-red-500 @enderror">
                            @foreach($handlingOptions as $val => $label)
                                <option value="{{ $val }}" @selected((int)$currentHandling === $val)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-400 dark:text-zinc-500 mt-1">
                            Tempo de preparação do pedido antes do envio.
                        </p>
                        @error('handling_time')
                            <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                        @enderror
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

            {{-- Imagens --}}
            @if(!empty($liveData['pictures']))
            <x-ui.card title="Imagens">
                <div class="grid grid-cols-4 sm:grid-cols-6 gap-2">
                    @foreach($liveData['pictures'] as $pic)
                    <a href="{{ $pic['url'] }}" target="_blank" class="block aspect-square rounded-md overflow-hidden border border-gray-200 dark:border-zinc-700 hover:border-primary-500 transition-colors">
                        <img src="{{ $pic['url'] }}" alt="Imagem do anuncio"
                             class="w-full h-full object-cover"
                             loading="lazy">
                    </a>
                    @endforeach
                </div>
            </x-ui.card>
            @endif

            {{-- Atributos --}}
            @if(!empty($liveData['attributes']))
            <x-ui.card title="Atributos">
                <div class="divide-y divide-gray-100 dark:divide-zinc-800">
                    @foreach($liveData['attributes'] as $attr)
                    @if(!empty($attr['value_name']))
                    <div class="flex justify-between py-2 text-sm">
                        <span class="text-gray-500 dark:text-zinc-400">{{ $attr['name'] }}</span>
                        <span class="text-gray-900 dark:text-white font-medium text-right max-w-[60%]">{{ $attr['value_name'] }}</span>
                    </div>
                    @endif
                    @endforeach
                </div>
            </x-ui.card>
            @endif

            {{-- Descricao --}}
            @if(!empty($description['plain_text']))
            <x-ui.card title="Descricao">
                <p class="text-sm text-gray-700 dark:text-zinc-300 whitespace-pre-line leading-relaxed">
                    {{ $description['plain_text'] }}
                </p>
            </x-ui.card>
            @endif

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
                    $health = $quality['health'] ?? 0;
                    $pct    = round($health * 100);
                    $color  = $pct >= 70 ? 'bg-emerald-500' : ($pct >= 40 ? 'bg-amber-500' : 'bg-red-500');
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
                <div class="space-y-2 text-sm">
                    <div>
                        <span class="text-gray-500 dark:text-zinc-400">ID</span>
                        <p class="font-mono mt-0.5">{{ $listing->external_id }}</p>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-zinc-400">Conta</span>
                        <p class="mt-0.5">{{ $listing->marketplaceAccount?->account_name ?? '—' }}</p>
                    </div>
                    @if($liveData['listing_type_id'] ?? null)
                    <div>
                        <span class="text-gray-500 dark:text-zinc-400">Tipo de anuncio</span>
                        <p class="font-mono mt-0.5 text-xs">{{ $liveData['listing_type_id'] }}</p>
                    </div>
                    @endif
                    @if($liveData['category_id'] ?? null)
                    <div>
                        <span class="text-gray-500 dark:text-zinc-400">Categoria</span>
                        <p class="font-mono mt-0.5 text-xs">{{ $liveData['category_id'] }}</p>
                    </div>
                    @endif
                    @if($liveData['permalink'] ?? ($listing->meta['ml_permalink'] ?? null))
                    <div>
                        <span class="text-gray-500 dark:text-zinc-400">Link</span>
                        <a href="{{ $liveData['permalink'] ?? $listing->meta['ml_permalink'] }}" target="_blank"
                           class="flex items-center gap-1 text-primary-500 hover:text-primary-400 mt-0.5 text-xs break-all">
                            Ver no ML
                            <x-heroicon-o-arrow-top-right-on-square class="w-3 h-3 flex-shrink-0" />
                        </a>
                    </div>
                    @endif
                </div>
            </x-ui.card>

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
