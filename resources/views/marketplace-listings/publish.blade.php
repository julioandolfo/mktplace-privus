<x-app-layout>
    <x-slot name="header">Publicar Novo Anúncio</x-slot>
    <x-slot name="breadcrumbs">
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <a href="{{ route('listings.index') }}" class="hover:text-gray-700 dark:hover:text-zinc-200">Anuncios</a>
        </li>
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <span class="text-gray-700 dark:text-zinc-200">Publicar</span>
        </li>
    </x-slot>


    <form method="POST" action="{{ route('listings.publish') }}" enctype="multipart/form-data"
          x-data="publishForm()"
          class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        @csrf

        {{-- ═══ Main Column ══════════════════════════════════════════════════ --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Produto e Conta --}}
            <x-ui.card title="Origem">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Conta do Marketplace *</label>
                        <select name="marketplace_account_id" x-model="accountId"
                            @change="categoryId=''; categoryName=''; catQuery=''; attributes=[]; variationAttributes=[]; variations=[];"
                            class="form-input @error('marketplace_account_id') border-red-500 @enderror" required>
                            <option value="">Selecione a conta...</option>
                            @foreach($accounts as $account)
                            <option value="{{ $account->id }}">
                                {{ $account->account_name }}
                                ({{ $account->marketplace_type->label() }})
                            </option>
                            @endforeach
                        </select>
                        @error('marketplace_account_id') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Produto Interno *</label>
                        <select name="product_id"
                            class="form-input @error('product_id') border-red-500 @enderror" required>
                            <option value="">Selecione o produto...</option>
                            @foreach($products as $product)
                            <option value="{{ $product->id }}"
                                data-price="{{ $product->price }}"
                                @selected(old('product_id', $preselectedProduct?->id) == $product->id)>
                                {{ $product->name }}
                                @if($product->sku) ({{ $product->sku }}) @endif
                            </option>
                            @endforeach
                        </select>
                        @error('product_id') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </x-ui.card>

            {{-- Dados do Anúncio --}}
            <x-ui.card title="Dados do Anuncio">
                <div class="space-y-4">

                    <div>
                        <label class="form-label">Título do Anúncio * <span class="text-gray-400 text-xs font-normal">(max 60 caracteres)</span></label>
                        <input type="text" name="title" maxlength="60"
                            value="{{ old('title', $preselectedProduct?->name) }}"
                            class="form-input @error('title') border-red-500 @enderror" required>
                        @error('title') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Category search --}}
                    <div>
                        <label class="form-label">Categoria do ML *</label>
                        <div class="relative">
                            <div x-show="!categoryId" class="relative">
                                <input type="text"
                                    :placeholder="accountId ? 'Buscar categoria... (ex: Tênis, Notebook, Câmera)' : 'Selecione uma conta primeiro...'"
                                    x-model="catQuery"
                                    @input.debounce.500ms="searchCategory()"
                                    @focus="catOpen = catResults.length > 0"
                                    :disabled="!accountId"
                                    class="form-input w-full"
                                    :class="!accountId ? 'opacity-50 cursor-not-allowed' : (catLoading ? 'pr-32' : '')"
                                    autocomplete="off">
                                <div x-show="catLoading" x-cloak
                                     class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                    <svg class="w-4 h-4 animate-spin text-primary-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                    <span class="ml-1.5 text-xs text-primary-500 font-medium">Buscando...</span>
                                </div>
                            </div>
                            {{-- Error message for no results --}}
                            <p x-show="catError" x-text="catError" class="text-xs text-red-500 mt-1" x-cloak></p>
                            <input type="hidden" name="category_id" x-model="categoryId"
                                class="@error('category_id') border-red-500 @enderror" required>

                            {{-- Selected category display --}}
                            <div x-show="categoryId" class="flex items-center gap-2 text-sm">
                                <x-heroicon-o-check-circle class="w-4 h-4 text-emerald-500" />
                                <span class="text-gray-700 dark:text-zinc-300" x-text="categoryName"></span>
                                <button type="button" @click="categoryId=''; categoryName=''; catQuery=''; attributes=[]; variationAttributes=[]; variations=[];" class="text-gray-400 hover:text-gray-600">
                                    <x-heroicon-o-x-mark class="w-3.5 h-3.5" />
                                </button>
                            </div>

                            {{-- Dropdown results --}}
                            <div x-show="catOpen && catResults.length > 0" @click.outside="catOpen=false"
                                 class="absolute z-20 w-full mt-1 bg-white dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 rounded-lg shadow-lg max-h-64 overflow-y-auto">
                                <template x-for="cat in catResults" :key="cat.category_id">
                                    <button type="button"
                                        @click="selectCategory(cat)"
                                        class="w-full text-left px-4 py-3 text-sm hover:bg-gray-50 dark:hover:bg-zinc-700/50 border-b border-gray-100 dark:border-zinc-700/50 last:border-0">
                                        <span x-text="cat.category_name" class="font-medium"></span>
                                        <span class="text-xs text-gray-400 dark:text-zinc-500 block" x-text="cat.domain_name"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                        @error('category_id') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <div>
                            <label class="form-label">Preço (R$) *</label>
                            <input type="number" name="price" step="0.01" min="0"
                                value="{{ old('price', $preselectedProduct?->price) }}"
                                class="form-input @error('price') border-red-500 @enderror" required>
                            @error('price') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="form-label">Estoque *</label>
                            <input type="number" name="available_quantity" min="1"
                                value="{{ old('available_quantity', 1) }}"
                                class="form-input @error('available_quantity') border-red-500 @enderror" required>
                            @error('available_quantity') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="form-label">Condição *</label>
                            <select name="condition" class="form-input @error('condition') border-red-500 @enderror" required>
                                <option value="new" @selected(old('condition','new')==='new')>Novo</option>
                                <option value="used" @selected(old('condition')==='used')>Usado</option>
                                <option value="not_specified" @selected(old('condition')==='not_specified')>Não especificado</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Prazo Preparo</label>
                            <select name="handling_time" class="form-input">
                                <option value="0">Mesmo dia</option>
                                @for($d=1;$d<=20;$d++)
                                <option value="{{ $d }}" @selected(old('handling_time')==$d)>
                                    {{ $d }} dia{{ $d>1?'s':'' }}
                                </option>
                                @endfor
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="form-label">Tipo de Anúncio *</label>
                        <div class="grid grid-cols-3 gap-3 mt-1">
                            @foreach([
                                ['gold_pro', 'Premium', 'Máxima visibilidade. Taxa maior.'],
                                ['gold_special', 'Clássico', 'Boa visibilidade. Taxa intermediária.'],
                                ['free', 'Grátis', 'Sem taxa. Visibilidade reduzida.'],
                            ] as [$val, $label, $desc])
                            <label class="relative flex flex-col border border-gray-200 dark:border-zinc-700 rounded-lg p-3 cursor-pointer hover:border-primary-500 has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50 dark:has-[:checked]:bg-primary-900/20 transition-colors">
                                <input type="radio" name="listing_type_id" value="{{ $val }}"
                                    @checked(old('listing_type_id','gold_special') === $val)
                                    class="sr-only">
                                <span class="font-medium text-sm">{{ $label }}</span>
                                <span class="text-xs text-gray-400 dark:text-zinc-500 mt-0.5">{{ $desc }}</span>
                            </label>
                            @endforeach
                        </div>
                        @error('listing_type_id') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </x-ui.card>

            {{-- Atributos da Categoria --}}
            <div x-show="attributes.length > 0" x-cloak>
            <x-ui.card title="Atributos da Categoria">
                <div class="space-y-3">
                    {{-- Botão preencher com IA --}}
                    <div class="flex justify-end">
                        <button type="button" @click="fillAttributesWithAi()"
                                :disabled="aiAttrLoading"
                                class="btn-secondary btn-sm inline-flex items-center gap-1.5 text-xs">
                            <template x-if="!aiAttrLoading">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 00-2.455 2.456z"/></svg>
                            </template>
                            <template x-if="aiAttrLoading">
                                <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                            </template>
                            <span x-text="aiAttrLoading ? 'Preenchendo...' : 'Preencher com IA'"></span>
                        </button>
                    </div>

                    <template x-for="attr in attributes" :key="attr.id">
                        <div x-show="!hasTag(attr.tags, 'read_only')" class="flex items-center gap-3">
                            <div class="w-44 flex-shrink-0">
                                <span class="text-sm text-gray-600 dark:text-zinc-400" x-text="attr.name"></span>
                                <span x-show="hasTag(attr.tags, 'required')" class="text-red-400 text-xs ml-0.5">*</span>
                            </div>
                            {{-- Select for allowed_values, input otherwise --}}
                            <template x-if="attr.allowed_values?.length > 0">
                                <select :name="'attributes[' + attr.id + ']'"
                                        x-model="attrValues[attr.id]"
                                        class="form-input flex-1 text-sm py-1.5">
                                    <option value="">Selecione...</option>
                                    <template x-for="val in attr.allowed_values" :key="val.id">
                                        <option :value="val.name" x-text="val.name"></option>
                                    </template>
                                </select>
                            </template>
                            <template x-if="!attr.allowed_values?.length">
                                <input :type="attr.value_type === 'number' ? 'number' : 'text'"
                                    :name="'attributes[' + attr.id + ']'"
                                    x-model="attrValues[attr.id]"
                                    class="form-input flex-1 text-sm py-1.5"
                                    placeholder="Não informado">
                            </template>
                        </div>
                    </template>
                </div>
            </x-ui.card>
            </div>

            {{-- Imagens --}}
            <x-ui.card title="Imagens">
                <div class="space-y-4">
                    {{-- Upload de arquivos com preview --}}
                    <div>
                        <label class="form-label">Upload de Imagens</label>
                        <div class="border-2 border-dashed border-gray-300 dark:border-zinc-600 rounded-lg p-4 text-center cursor-pointer hover:border-primary-500 dark:hover:border-primary-500 transition-colors"
                             @click="$refs.fileInput.click()"
                             @dragover.prevent="$event.currentTarget.classList.add('border-primary-500','bg-primary-50','dark:bg-primary-900/10')"
                             @dragleave.prevent="$event.currentTarget.classList.remove('border-primary-500','bg-primary-50','dark:bg-primary-900/10')"
                             @drop.prevent="$event.currentTarget.classList.remove('border-primary-500','bg-primary-50','dark:bg-primary-900/10'); handleFilesDrop($event)">
                            <svg class="w-8 h-8 mx-auto text-gray-400 dark:text-zinc-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
                            <p class="text-sm text-gray-500 dark:text-zinc-400">Clique ou arraste imagens aqui</p>
                            <p class="text-xs text-gray-400 dark:text-zinc-500 mt-1">JPG/PNG, max 10MB. Min. 500x500px</p>
                        </div>
                        <input type="file" x-ref="fileInput" @change="handleFilesSelect($event)" multiple accept="image/jpeg,image/png" class="hidden">
                    </div>

                    {{-- Preview das imagens selecionadas --}}
                    <div x-show="uploadedImages.length > 0" class="grid grid-cols-4 sm:grid-cols-6 gap-2" x-cloak>
                        <template x-for="(img, idx) in uploadedImages" :key="idx">
                            <div class="relative group aspect-square rounded-lg overflow-hidden border border-gray-200 dark:border-zinc-700">
                                <img :src="img.preview" class="w-full h-full object-cover">
                                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                    <button type="button" @click="removeImage(idx)" class="text-white hover:text-red-300">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                                <span x-show="idx === 0" class="absolute top-1 left-1 bg-primary-600 text-white text-[10px] px-1.5 py-0.5 rounded font-medium">Principal</span>
                            </div>
                        </template>
                    </div>

                    {{-- Hidden file inputs para envio do form --}}
                    <template x-for="(img, idx) in uploadedImages" :key="'file-'+idx">
                        <input type="file" :name="'picture_files[]'" class="hidden" x-ref="'hiddenFile'+idx">
                    </template>

                    {{-- URLs de imagens --}}
                    <div>
                        <label class="form-label">Ou adicione por URL</label>
                        <div class="space-y-2" id="pictures-container">
                            <div class="flex gap-2">
                                <input type="url" name="pictures[]"
                                    placeholder="URL da imagem (https://...)"
                                    class="form-input flex-1 text-sm">
                                <button type="button" onclick="addPictureField()"
                                    class="btn-secondary btn-sm flex-shrink-0">
                                    <x-heroicon-o-plus class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <p class="text-xs text-gray-400 dark:text-zinc-500 mt-2">
                    A primeira imagem (upload ou URL) será a principal do anúncio.
                </p>
            </x-ui.card>

            {{-- Variações --}}
            <div x-show="variationAttributes.length > 0" x-cloak>
            <x-ui.card title="Variacoes">
                <div class="space-y-4">
                    <div class="flex items-start gap-3 text-sm bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-lg p-3">
                        <x-heroicon-o-information-circle class="w-4 h-4 text-amber-500 flex-shrink-0 mt-0.5" />
                        <p class="text-amber-700 dark:text-amber-300">
                            Se o produto tem variações (cor, tamanho, etc.), adicione-as abaixo.
                            Cada variação terá seu próprio estoque e preço.
                        </p>
                    </div>

                    <template x-for="(variation, vi) in variations" :key="vi">
                        <div class="border border-gray-200 dark:border-zinc-700 rounded-lg p-4 space-y-3 relative">
                            <button type="button" @click="variations.splice(vi, 1)"
                                    class="absolute top-2 right-2 text-red-400 hover:text-red-600 dark:hover:text-red-300">
                                <x-heroicon-o-x-mark class="w-4 h-4" />
                            </button>
                            <div class="text-xs font-semibold text-gray-500 dark:text-zinc-400 uppercase tracking-wider">
                                Variação <span x-text="vi + 1"></span>
                            </div>
                            {{-- Variation attribute combos --}}
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                <template x-for="vAttr in variationAttributes" :key="vAttr.id">
                                    <div>
                                        <label class="text-xs text-gray-600 dark:text-zinc-400" x-text="vAttr.name"></label>
                                        <template x-if="vAttr.allowed_values?.length > 0">
                                            <select :name="'variations[' + vi + '][attributes][' + vAttr.id + ']'"
                                                    x-model="variation.attributes[vAttr.id]"
                                                    class="form-input text-sm py-1.5">
                                                <option value="">Selecione...</option>
                                                <template x-for="val in vAttr.allowed_values" :key="val.id">
                                                    <option :value="val.name" x-text="val.name"></option>
                                                </template>
                                            </select>
                                        </template>
                                        <template x-if="!vAttr.allowed_values?.length">
                                            <input type="text"
                                                   :name="'variations[' + vi + '][attributes][' + vAttr.id + ']'"
                                                   x-model="variation.attributes[vAttr.id]"
                                                   class="form-input text-sm py-1.5"
                                                   placeholder="Valor...">
                                        </template>
                                    </div>
                                </template>
                            </div>
                            {{-- Imagens da variação --}}
                            <div x-show="uploadedImages.length > 0">
                                <label class="text-xs text-gray-600 dark:text-zinc-400 mb-1 block">Imagens desta variação</label>
                                <div class="flex flex-wrap gap-2">
                                    <template x-for="(img, imgIdx) in uploadedImages" :key="'var-img-'+vi+'-'+imgIdx">
                                        <label class="relative cursor-pointer">
                                            <input type="checkbox"
                                                   :value="imgIdx"
                                                   @change="toggleVariationImage(vi, imgIdx, $event.target.checked)"
                                                   :checked="variation.picture_indices?.includes(imgIdx)"
                                                   class="sr-only peer">
                                            <img :src="img.preview"
                                                 class="w-12 h-12 rounded object-cover border-2 border-gray-200 dark:border-zinc-700 peer-checked:border-primary-500 peer-checked:ring-2 peer-checked:ring-primary-500/30 transition-all">
                                        </label>
                                    </template>
                                </div>
                            </div>
                            {{-- Price, qty, SKU --}}
                            <div class="grid grid-cols-3 gap-3">
                                <div>
                                    <label class="text-xs text-gray-600 dark:text-zinc-400">Preço (R$)</label>
                                    <input type="number" step="0.01" min="0"
                                           :name="'variations[' + vi + '][price]'"
                                           x-model="variation.price"
                                           class="form-input text-sm py-1.5"
                                           placeholder="Mesmo do anúncio">
                                </div>
                                <div>
                                    <label class="text-xs text-gray-600 dark:text-zinc-400">Estoque *</label>
                                    <input type="number" min="1"
                                           :name="'variations[' + vi + '][available_quantity]'"
                                           x-model="variation.available_quantity"
                                           class="form-input text-sm py-1.5" required>
                                </div>
                                <div>
                                    <label class="text-xs text-gray-600 dark:text-zinc-400">SKU</label>
                                    <input type="text"
                                           :name="'variations[' + vi + '][seller_custom_field]'"
                                           x-model="variation.seller_custom_field"
                                           class="form-input text-sm py-1.5 font-mono"
                                           placeholder="Opcional">
                                </div>
                            </div>
                        </div>
                    </template>

                    <button type="button" @click="addVariation()" class="btn-secondary btn-sm w-full justify-center">
                        <x-heroicon-o-plus class="w-4 h-4" />
                        Adicionar Variação
                    </button>
                </div>
            </x-ui.card>
            </div>

            {{-- Descrição --}}
            <x-ui.card title="Descricao">
                <div class="flex justify-end mb-2">
                    <button type="button" @click="generateDescriptionAi()"
                            :disabled="aiDescLoading"
                            class="btn-secondary btn-sm inline-flex items-center gap-1.5 text-xs">
                        <template x-if="!aiDescLoading">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 00-2.455 2.456z"/></svg>
                        </template>
                        <template x-if="aiDescLoading">
                            <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        </template>
                        <span x-text="aiDescLoading ? 'Gerando...' : 'Gerar com IA'"></span>
                    </button>
                </div>
                <textarea name="description" x-model="description" rows="8"
                    class="form-input w-full text-sm leading-relaxed resize-y"
                    placeholder="Descrição detalhada do produto...">{{ old('description') }}</textarea>
                <p class="text-xs text-gray-400 dark:text-zinc-500 mt-1">
                    A descrição é enviada após a publicação do anúncio.
                </p>
            </x-ui.card>

        </div>

        {{-- ═══ Sidebar ══════════════════════════════════════════════════════ --}}
        <div class="space-y-6">
            <x-ui.card title="Publicar">
                <div class="space-y-4">
                    <div class="flex items-start gap-3 text-sm bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg p-3">
                        <x-heroicon-o-information-circle class="w-4 h-4 text-blue-500 flex-shrink-0 mt-0.5" />
                        <p class="text-blue-700 dark:text-blue-300">
                            O anúncio será publicado diretamente no Mercado Livre e vinculado ao produto selecionado.
                        </p>
                    </div>

                    <button type="submit" class="btn-primary w-full justify-center">
                        <x-heroicon-o-rocket-launch class="w-4 h-4" />
                        Publicar no Mercado Livre
                    </button>

                    <a href="{{ route('listings.index') }}" class="btn-secondary w-full justify-center">
                        Cancelar
                    </a>
                </div>
            </x-ui.card>

            <x-ui.card title="Dicas">
                <ul class="text-sm text-gray-600 dark:text-zinc-400 space-y-2">
                    <li class="flex gap-2">
                        <x-heroicon-o-check-circle class="w-4 h-4 text-emerald-500 flex-shrink-0 mt-0.5" />
                        Título claro e descritivo (evite repetir a categoria)
                    </li>
                    <li class="flex gap-2">
                        <x-heroicon-o-check-circle class="w-4 h-4 text-emerald-500 flex-shrink-0 mt-0.5" />
                        Mínimo 4 fotos de alta qualidade
                    </li>
                    <li class="flex gap-2">
                        <x-heroicon-o-check-circle class="w-4 h-4 text-emerald-500 flex-shrink-0 mt-0.5" />
                        Preencha todos os atributos obrigatórios
                    </li>
                    <li class="flex gap-2">
                        <x-heroicon-o-check-circle class="w-4 h-4 text-emerald-500 flex-shrink-0 mt-0.5" />
                        Descrição detalhada aumenta conversão
                    </li>
                </ul>
            </x-ui.card>
        </div>
    </form>

    <script>
        function publishForm() {
            return {
                accountId: '{{ old('marketplace_account_id', $accounts->count() === 1 ? $accounts->first()->id : '') }}',
                categoryId: '{{ old('category_id', '') }}',
                categoryName: '{{ old('category_id', '') ? 'Categoria selecionada' : '' }}',
                attributes: [],
                variationAttributes: [],
                variations: [],
                attrValues: {},
                description: '{{ old('description', '') }}',

                // Category search state
                catQuery: '',
                catResults: [],
                catOpen: false,
                catLoading: false,
                catError: '',

                // AI state
                aiAttrLoading: false,
                aiDescLoading: false,

                // Images state
                uploadedImages: [],
                fileTransfer: null,

                hasTag(tags, key) {
                    return Array.isArray(tags) ? tags.includes(key) : !!(tags && tags[key]);
                },

                searchCategory() {
                    if (!this.accountId) {
                        this.catError = 'Selecione uma conta do marketplace primeiro.';
                        return;
                    }
                    if (!this.catQuery || this.catQuery.length < 2) return;

                    this.catLoading = true;
                    this.catError = '';

                    fetch(`/listings/categories/search?q=${encodeURIComponent(this.catQuery)}&account_id=${this.accountId}`)
                        .then(r => {
                            if (!r.ok) throw new Error(`HTTP ${r.status}`);
                            return r.json();
                        })
                        .then(data => {
                            this.catResults = Array.isArray(data) ? data : (data.domain_categories ?? []);
                            this.catOpen    = this.catResults.length > 0;
                            this.catLoading = false;
                            if (this.catResults.length === 0) {
                                this.catError = 'Nenhuma categoria encontrada para "' + this.catQuery + '". Tente descrever o produto (ex: "caneca de porcelana", "copo térmico").';
                            }
                        })
                        .catch((err) => {
                            this.catLoading = false;
                            this.catError = 'Erro ao buscar categorias. Verifique as credenciais da conta.';
                            console.error('searchCategory error:', err);
                        });
                },

                selectCategory(cat) {
                    this.categoryId   = cat.category_id;
                    this.categoryName = cat.category_name + ' (' + cat.domain_name + ')';
                    this.catOpen      = false;
                    this.catQuery     = '';
                    this.loadCategoryAttributes();
                },

                loadCategoryAttributes() {
                    if (!this.categoryId || !this.accountId) return;

                    fetch(`/listings/categories/attributes?category_id=${this.categoryId}&account_id=${this.accountId}`)
                        .then(r => r.json())
                        .then(data => {
                            const allAttrs = Array.isArray(data) ? data : [];
                            this.attributes = allAttrs.filter(a => !this.hasTag(a.tags, 'read_only') && !this.hasTag(a.tags, 'allow_variations'));
                            this.variationAttributes = allAttrs.filter(a => this.hasTag(a.tags, 'allow_variations'));
                            this.attrValues = {};
                            this.variations = [];
                        })
                        .catch(() => { this.attributes = []; this.variationAttributes = []; });
                },

                addVariation() {
                    this.variations.push({
                        attributes: {},
                        price: '',
                        available_quantity: 1,
                        seller_custom_field: '',
                        picture_indices: [],
                    });
                },

                // === Image handling ===
                handleFilesSelect(event) {
                    this.addFiles(event.target.files);
                },

                handleFilesDrop(event) {
                    this.addFiles(event.dataTransfer.files);
                },

                addFiles(fileList) {
                    for (const file of fileList) {
                        if (!file.type.match(/^image\/(jpeg|png)$/)) continue;
                        this.uploadedImages.push({
                            file: file,
                            preview: URL.createObjectURL(file),
                        });
                    }
                    this.syncFileInput();
                },

                removeImage(idx) {
                    URL.revokeObjectURL(this.uploadedImages[idx].preview);
                    this.uploadedImages.splice(idx, 1);
                    // Update variation picture_indices
                    this.variations.forEach(v => {
                        v.picture_indices = (v.picture_indices || [])
                            .filter(i => i !== idx)
                            .map(i => i > idx ? i - 1 : i);
                    });
                    this.syncFileInput();
                },

                syncFileInput() {
                    // Rebuild a real FileList via DataTransfer for form submission
                    const dt = new DataTransfer();
                    this.uploadedImages.forEach(img => dt.items.add(img.file));
                    this.$refs.fileInput.files = dt.files;
                },

                toggleVariationImage(vi, imgIdx, checked) {
                    if (!this.variations[vi].picture_indices) {
                        this.variations[vi].picture_indices = [];
                    }
                    if (checked) {
                        if (!this.variations[vi].picture_indices.includes(imgIdx)) {
                            this.variations[vi].picture_indices.push(imgIdx);
                        }
                    } else {
                        this.variations[vi].picture_indices = this.variations[vi].picture_indices.filter(i => i !== imgIdx);
                    }
                },

                // === AI: Fill attributes ===
                fillAttributesWithAi() {
                    const title = document.querySelector('input[name="title"]')?.value;
                    if (!title) { alert('Preencha o título do anúncio primeiro.'); return; }
                    if (!this.categoryId) { alert('Selecione uma categoria primeiro.'); return; }

                    this.aiAttrLoading = true;

                    const attrList = this.attributes.map(a => ({
                        id: a.id,
                        name: a.name,
                        value_type: a.value_type,
                        allowed_values: (a.allowed_values || []).map(v => v.name),
                    }));

                    fetch('/listings/ai/fill-attributes', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                        body: JSON.stringify({
                            title: title,
                            category_name: this.categoryName,
                            attributes: attrList,
                        }),
                    })
                    .then(r => {
                        if (!r.ok) throw new Error(`HTTP ${r.status}`);
                        return r.json();
                    })
                    .then(data => {
                        // data = { ATTR_ID: "value", ... }
                        Object.entries(data).forEach(([key, value]) => {
                            if (value) this.attrValues[key] = value;
                        });
                        this.aiAttrLoading = false;
                    })
                    .catch(err => {
                        this.aiAttrLoading = false;
                        alert('Erro ao preencher com IA. Verifique se a IA está configurada em Configurações.');
                        console.error('AI fill attributes error:', err);
                    });
                },

                // === AI: Generate description ===
                generateDescriptionAi() {
                    const title = document.querySelector('input[name="title"]')?.value;
                    if (!title) { alert('Preencha o título do anúncio primeiro.'); return; }

                    this.aiDescLoading = true;

                    fetch('/listings/ai/generate-description', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                        body: JSON.stringify({
                            title: title,
                            category_name: this.categoryName || '',
                            attributes: this.attrValues || {},
                            existing_description: this.description || '',
                        }),
                    })
                    .then(r => {
                        if (!r.ok) throw new Error(`HTTP ${r.status}`);
                        return r.json();
                    })
                    .then(data => {
                        this.description = data.description || '';
                        this.aiDescLoading = false;
                    })
                    .catch(err => {
                        this.aiDescLoading = false;
                        alert('Erro ao gerar descrição. Verifique se a IA está configurada em Configurações.');
                        console.error('AI generate description error:', err);
                    });
                },
            };
        }

        function addPictureField() {
            const container = document.getElementById('pictures-container');
            const div = document.createElement('div');
            div.className = 'flex gap-2';
            div.innerHTML = `
                <input type="url" name="pictures[]"
                    placeholder="URL da imagem (https://...)"
                    class="form-input flex-1 text-sm">
                <button type="button" onclick="this.closest('div').remove()"
                    class="btn-ghost btn-sm text-red-500 flex-shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            `;
            container.appendChild(div);
        }
    </script>
</x-app-layout>
