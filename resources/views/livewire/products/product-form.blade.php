<div>
    <form wire:submit="save">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Main content (2/3) --}}
            <div class="lg:col-span-2 space-y-6">
                {{-- Basic info --}}
                <x-ui.card title="Informacoes Basicas">
                    <div class="space-y-4">
                        <div>
                            <label for="name" class="form-label">Nome do Produto *</label>
                            <input type="text" id="name" wire:model="name" class="form-input" required>
                            @error('name') <p class="form-error">{{ $message }}</p> @enderror
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="sku" class="form-label">SKU *</label>
                                <input type="text" id="sku" wire:model="sku" class="form-input font-mono" required>
                                @error('sku') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="ean_gtin" class="form-label">EAN / GTIN</label>
                                <input type="text" id="ean_gtin" wire:model="ean_gtin" class="form-input font-mono">
                            </div>
                        </div>

                        <div>
                            <label for="short_description" class="form-label">Descricao Curta</label>
                            <input type="text" id="short_description" wire:model="short_description" class="form-input" maxlength="500">
                        </div>

                        <div>
                            <label for="description" class="form-label">Descricao Completa</label>
                            <textarea id="description" wire:model="description" class="form-input" rows="5"></textarea>
                        </div>
                    </div>
                </x-ui.card>

                {{-- Pricing --}}
                <x-ui.card title="Precos">
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label for="price" class="form-label">Preco de Venda (R$) *</label>
                            <input type="number" step="0.01" id="price" wire:model="price" class="form-input" required>
                            @error('price') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="cost_price" class="form-label">Preco de Custo (R$)</label>
                            <input type="number" step="0.01" id="cost_price" wire:model="cost_price" class="form-input">
                        </div>
                        <div>
                            <label for="compare_at_price" class="form-label">Preco Comparativo (R$)</label>
                            <input type="number" step="0.01" id="compare_at_price" wire:model="compare_at_price" class="form-input">
                            <p class="text-xs text-gray-500 dark:text-zinc-400 mt-1">Preco "De:" para promos</p>
                        </div>
                    </div>
                </x-ui.card>

                {{-- Variants --}}
                @if($type === 'variable')
                <x-ui.card title="Variantes">
                    <div class="space-y-4">
                        @forelse($variants as $index => $variant)
                        <div wire:key="variant-{{ $index }}" class="p-4 rounded-lg border border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800/50">
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-sm font-medium text-gray-700 dark:text-zinc-300">Variante {{ $index + 1 }}</span>
                                <button type="button" wire:click="removeVariant({{ $index }})" class="text-red-500 hover:text-red-700">
                                    <x-heroicon-o-trash class="w-4 h-4" />
                                </button>
                            </div>
                            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                                <div>
                                    <label class="form-label">Nome *</label>
                                    <input type="text" wire:model="variants.{{ $index }}.name" class="form-input" placeholder="Ex: Azul / M">
                                    @error("variants.{$index}.name") <p class="form-error">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="form-label">SKU *</label>
                                    <input type="text" wire:model="variants.{{ $index }}.sku" class="form-input font-mono">
                                    @error("variants.{$index}.sku") <p class="form-error">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="form-label">Preco (R$)</label>
                                    <input type="number" step="0.01" wire:model="variants.{{ $index }}.price" class="form-input" placeholder="Usar pai">
                                </div>
                                <div>
                                    <label class="form-label">Custo (R$)</label>
                                    <input type="number" step="0.01" wire:model="variants.{{ $index }}.cost_price" class="form-input">
                                </div>
                            </div>
                        </div>
                        @empty
                        <p class="text-sm text-gray-500 dark:text-zinc-400">Nenhuma variante adicionada.</p>
                        @endforelse

                        <button type="button" wire:click="addVariant" class="btn-secondary">
                            <x-heroicon-s-plus class="w-4 h-4" />
                            Adicionar Variante
                        </button>
                    </div>
                </x-ui.card>
                @endif

                {{-- Dimensions & Weight --}}
                <x-ui.card title="Dimensoes e Peso">
                    <div class="grid grid-cols-4 gap-4">
                        <div>
                            <label for="weight" class="form-label">Peso (g)</label>
                            <input type="number" step="0.001" id="weight" wire:model="weight" class="form-input">
                        </div>
                        <div>
                            <label for="width" class="form-label">Largura (cm)</label>
                            <input type="number" step="0.01" id="width" wire:model="width" class="form-input">
                        </div>
                        <div>
                            <label for="height" class="form-label">Altura (cm)</label>
                            <input type="number" step="0.01" id="height" wire:model="height" class="form-input">
                        </div>
                        <div>
                            <label for="length" class="form-label">Comprimento (cm)</label>
                            <input type="number" step="0.01" id="length" wire:model="length" class="form-input">
                        </div>
                    </div>
                    <div class="mt-4 max-w-xs">
                        <label for="expedition_points" class="form-label">
                            Pontos de Expedição
                            <span class="text-gray-400 font-normal">(opcional)</span>
                        </label>
                        <input type="number" min="0" id="expedition_points" wire:model="expedition_points" class="form-input" placeholder="Padrão da config">
                        <p class="text-xs text-gray-400 dark:text-zinc-500 mt-1">Pontos por unidade para bonificação. Vazio = padrão global.</p>
                    </div>
                </x-ui.card>

                {{-- Processo --}}
                <x-ui.card title="Processo">
                    <div class="space-y-3">
                        <div class="flex items-center gap-2">
                            <input type="checkbox" wire:model="requires_production" id="requires_production"
                                   class="rounded border-gray-300 dark:border-zinc-600 text-primary-600 focus:ring-primary-500">
                            <label for="requires_production" class="text-sm text-gray-700 dark:text-zinc-300">
                                Exige produção <span class="text-gray-400">(entra na fila de produção)</span>
                            </label>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="checkbox" wire:model="requires_artwork" id="requires_artwork"
                                   class="rounded border-gray-300 dark:border-zinc-600 text-primary-600 focus:ring-primary-500">
                            <label for="requires_artwork" class="text-sm text-gray-700 dark:text-zinc-300">
                                Exige arte/design <span class="text-gray-400">(entra na fila de designer)</span>
                            </label>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="checkbox" wire:model="requires_purchase" id="requires_purchase"
                                   class="rounded border-gray-300 dark:border-zinc-600 text-primary-600 focus:ring-primary-500">
                            <label for="requires_purchase" class="text-sm text-gray-700 dark:text-zinc-300">
                                Exige compra <span class="text-gray-400">(gera solicitação de compra automática)</span>
                            </label>
                        </div>
                    </div>
                </x-ui.card>

                {{-- Fiscal --}}
                <x-ui.card title="Dados Fiscais">
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label for="ncm" class="form-label">NCM</label>
                            <input type="text" id="ncm" wire:model="ncm" class="form-input font-mono" maxlength="10">
                        </div>
                        <div>
                            <label for="cest" class="form-label">CEST</label>
                            <input type="text" id="cest" wire:model="cest" class="form-input font-mono">
                        </div>
                        <div>
                            <label for="origin" class="form-label">Origem</label>
                            <select id="origin" wire:model="origin" class="form-input">
                                <option value="">Selecione</option>
                                <option value="0">0 - Nacional</option>
                                <option value="1">1 - Estrangeira (importacao direta)</option>
                                <option value="2">2 - Estrangeira (adquirida no mercado interno)</option>
                                <option value="3">3 - Nacional, conteudo importacao > 40%</option>
                                <option value="5">5 - Nacional, conteudo importacao <= 40%</option>
                                <option value="8">8 - Nacional, conteudo importacao > 70%</option>
                            </select>
                        </div>
                    </div>
                </x-ui.card>

                {{-- SEO --}}
                <x-ui.card title="SEO">
                    <div class="space-y-4">
                        <div>
                            <label for="seo_title" class="form-label">Titulo SEO</label>
                            <input type="text" id="seo_title" wire:model="seo_title" class="form-input" maxlength="70">
                            <p class="text-xs text-gray-500 dark:text-zinc-400 mt-1">{{ strlen($seo_title) }}/70 caracteres</p>
                        </div>
                        <div>
                            <label for="seo_description" class="form-label">Descricao SEO</label>
                            <textarea id="seo_description" wire:model="seo_description" class="form-input" rows="2" maxlength="160"></textarea>
                            <p class="text-xs text-gray-500 dark:text-zinc-400 mt-1">{{ strlen($seo_description) }}/160 caracteres</p>
                        </div>
                    </div>
                </x-ui.card>
            </div>

            {{-- Sidebar (1/3) --}}
            <div class="space-y-6">
                {{-- Status & Type --}}
                <x-ui.card title="Publicacao">
                    <div class="space-y-4">
                        <div>
                            <label for="status" class="form-label">Status</label>
                            <select id="status" wire:model="status" class="form-input">
                                @foreach($statuses as $s)
                                    <option value="{{ $s->value }}">{{ $s->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="type" class="form-label">Tipo</label>
                            <select id="type" wire:model.live="type" class="form-input">
                                @foreach($types as $t)
                                    <option value="{{ $t->value }}">{{ $t->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </x-ui.card>

                {{-- Organization --}}
                <x-ui.card title="Organizacao">
                    <div class="space-y-4">
                        <div>
                            <label for="category_id" class="form-label">Categoria</label>
                            <select id="category_id" wire:model="category_id" class="form-input">
                                <option value="">Nenhuma</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="brand_id" class="form-label">Marca</label>
                            <select id="brand_id" wire:model="brand_id" class="form-input">
                                <option value="">Nenhuma</option>
                                @foreach($brands as $b)
                                    <option value="{{ $b->id }}">{{ $b->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </x-ui.card>

                {{-- Images --}}
                <x-ui.card title="Imagens">
                    <div class="space-y-3">
                        @foreach($existingImages as $index => $img)
                        <div wire:key="img-{{ $img['id'] }}" class="flex items-center gap-3 p-2 rounded-lg border border-gray-200 dark:border-zinc-700">
                            <img src="{{ asset('storage/' . $img['path']) }}" alt="" class="w-12 h-12 rounded object-cover">
                            <div class="flex-1 min-w-0">
                                @if($img['is_primary'])
                                    <x-ui.badge color="success">Principal</x-ui.badge>
                                @else
                                    <button type="button" wire:click="setAsPrimary({{ $index }})" class="text-xs text-primary-600 hover:underline">Definir como principal</button>
                                @endif
                            </div>
                            <button type="button" wire:click="removeExistingImage({{ $index }})" class="text-red-500 hover:text-red-700">
                                <x-heroicon-o-x-mark class="w-4 h-4" />
                            </button>
                        </div>
                        @endforeach

                        <div>
                            <label class="block w-full cursor-pointer">
                                <div class="flex items-center justify-center gap-2 px-4 py-3 border-2 border-dashed border-gray-300 dark:border-zinc-600 rounded-lg hover:border-primary-500 dark:hover:border-primary-500 transition-colors">
                                    <x-heroicon-o-photo class="w-5 h-5 text-gray-400 dark:text-zinc-500" />
                                    <span class="text-sm text-gray-600 dark:text-zinc-400">Adicionar imagens</span>
                                </div>
                                <input type="file" wire:model="newImages" multiple accept="image/*" class="hidden">
                            </label>
                            @error('newImages.*') <p class="form-error">{{ $message }}</p> @enderror
                        </div>

                        @if($newImages)
                        <div class="flex flex-wrap gap-2 mt-2">
                            @foreach($newImages as $img)
                            <img src="{{ $img->temporaryUrl() }}" alt="" class="w-12 h-12 rounded object-cover border border-gray-200 dark:border-zinc-700">
                            @endforeach
                        </div>
                        @endif
                    </div>
                </x-ui.card>

                {{-- Initial Stock (only for new products) --}}
                @unless($product)
                <x-ui.card title="Estoque Inicial">
                    <div class="space-y-4">
                        <div>
                            <label for="initial_stock" class="form-label">Quantidade</label>
                            <input type="number" id="initial_stock" wire:model="initial_stock" class="form-input" min="0">
                        </div>
                        @if($stockLocations->isNotEmpty())
                        <div>
                            <label for="stock_location_id" class="form-label">Local de Estoque</label>
                            <select id="stock_location_id" wire:model="stock_location_id" class="form-input">
                                @foreach($stockLocations as $loc)
                                    <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        @endif
                    </div>
                </x-ui.card>
                @endunless
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex items-center justify-end gap-3 mt-6">
            <a href="{{ route('products.index') }}" class="btn-secondary">Cancelar</a>
            <button type="submit" class="btn-primary" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="save">
                    <x-heroicon-s-check class="w-4 h-4" />
                    {{ $product ? 'Salvar Alteracoes' : 'Cadastrar Produto' }}
                </span>
                <span wire:loading wire:target="save">Salvando...</span>
            </button>
        </div>
    </form>
</div>
