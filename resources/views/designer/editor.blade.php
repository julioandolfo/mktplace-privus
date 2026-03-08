<!DOCTYPE html>
<html lang="pt-BR" class="h-full dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Editor de Designer Visual — {{ $assignment->order->order_number }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js"></script>
    <style>
        body { overflow: hidden; user-select: none; }
        #canvas-wrap canvas { display: block; }
        .panel-left  { width: 260px; min-width: 260px; }
        .panel-right { width: 280px; min-width: 280px; }
        .editor-center { flex: 1; overflow: hidden; position: relative; }
        .tool-btn { @apply flex flex-col items-center justify-center gap-1 p-2 rounded-lg text-xs font-medium text-gray-600 dark:text-zinc-400 hover:bg-gray-100 dark:hover:bg-zinc-700 transition-colors cursor-pointer w-full; }
        .tool-btn.active { @apply bg-primary-100 dark:bg-primary-900/40 text-primary-700 dark:text-primary-300; }
        .layer-item { @apply flex items-center gap-2 px-3 py-2 rounded-lg cursor-pointer hover:bg-gray-100 dark:hover:bg-zinc-700 text-sm; }
        .layer-item.selected { @apply bg-primary-50 dark:bg-primary-900/30; }
    </style>
</head>
<body class="h-screen flex flex-col bg-zinc-950 text-gray-900 dark:text-zinc-100 font-sans">

{{-- TOP BAR ----------------------------------------------------------------}}
<header class="h-12 flex items-center justify-between px-4 bg-zinc-900 border-b border-zinc-800 flex-shrink-0 z-30">
    <div class="flex items-center gap-3">
        <a href="{{ route('designer.index') }}"
           class="text-zinc-400 hover:text-white transition-colors p-1 rounded">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div class="w-px h-5 bg-zinc-700"></div>
        <x-heroicon-o-paint-brush class="w-4 h-4 text-primary-400" />
        <span class="text-sm font-semibold text-white">Editor de Designer Visual</span>
        <span class="text-zinc-500 text-sm">·</span>
        <span class="text-sm text-zinc-300">{{ $assignment->order->order_number }}</span>
        <x-ui.badge :color="$assignment->statusColor()">{{ $assignment->statusLabel() }}</x-ui.badge>
    </div>

    <div class="flex items-center gap-2">
        <button id="btn-save-draft"
                class="flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-zinc-300 hover:text-white bg-zinc-800 hover:bg-zinc-700 rounded-lg transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
            Salvar Rascunho
        </button>
        <button id="btn-gen-mockup"
                class="flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-zinc-300 hover:text-white bg-zinc-800 hover:bg-zinc-700 rounded-lg transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            Gerar Mockup
        </button>
        <button id="btn-finalize"
                class="flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-white bg-green-600 hover:bg-green-500 rounded-lg transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            Finalizar Designer
        </button>
    </div>
</header>

{{-- MAIN AREA --------------------------------------------------------------}}
<main class="flex flex-1 overflow-hidden">

    {{-- PANEL LEFT ----------------------------------------------------------}}
    <aside class="panel-left flex flex-col bg-zinc-900 border-r border-zinc-800 overflow-y-auto">

        {{-- Tabs do painel esquerdo --}}
        <div x-data="{ leftTab: 'tools' }" class="flex flex-col h-full">
            <div class="flex border-b border-zinc-800 text-xs">
                @foreach([['tools','Ferramentas'],['specs','Especificações'],['ai','✦ IA']] as [$tab,$label])
                <button @click="leftTab = '{{ $tab }}'"
                        :class="leftTab === '{{ $tab }}' ? 'text-primary-400 border-b-2 border-primary-400' : 'text-zinc-500 hover:text-zinc-300'"
                        class="flex-1 px-2 py-2.5 font-medium transition-colors">{{ $label }}</button>
                @endforeach
            </div>

            {{-- TAB FERRAMENTAS --}}
            <div x-show="leftTab === 'tools'" class="p-3 space-y-4">
                <p class="text-[10px] font-semibold text-zinc-500 uppercase tracking-wider">Ferramentas</p>
                <div class="grid grid-cols-3 gap-1">
                    <button id="tool-select" class="tool-btn active" title="Selecionar">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"/></svg>
                        <span>Selecionar</span>
                    </button>
                    <button id="tool-text" class="tool-btn" title="Texto">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/></svg>
                        <span>Texto</span>
                    </button>
                    <button id="tool-image" class="tool-btn" title="Imagem">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <span>Imagem</span>
                    </button>
                    <button id="tool-rect" class="tool-btn" title="Retângulo">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" ry="2" stroke-width="2"/></svg>
                        <span>Retângulo</span>
                    </button>
                    <button id="tool-circle" class="tool-btn" title="Círculo">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" stroke-width="2"/></svg>
                        <span>Círculo</span>
                    </button>
                    <button id="tool-draw" class="tool-btn" title="Desenho livre">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                        <span>Desenho</span>
                    </button>
                </div>

                <div class="border-t border-zinc-800 pt-3">
                    <p class="text-[10px] font-semibold text-zinc-500 uppercase tracking-wider mb-2">Fundo do Canvas</p>
                    <div class="space-y-2">
                        <p class="text-xs text-zinc-400">Imagem do Produto</p>
                        <div class="grid grid-cols-2 gap-1">
                            @foreach($productImages->take(4) as $img)
                            <button onclick="DesignEditor.setBackground('{{ $img->url }}')"
                                    class="aspect-square rounded-lg overflow-hidden border-2 border-transparent hover:border-primary-500 transition-colors">
                                <img src="{{ $img->url }}" alt="" class="w-full h-full object-cover" />
                            </button>
                            @endforeach
                        </div>
                        <label class="block w-full cursor-pointer">
                            <span class="flex items-center justify-center gap-1.5 w-full py-2 text-xs text-zinc-400 hover:text-zinc-200 bg-zinc-800 hover:bg-zinc-700 rounded-lg transition-colors border border-zinc-700">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                                Substituir fundo
                            </span>
                            <input type="file" accept="image/*" class="hidden" id="bg-file-input">
                        </label>
                    </div>
                </div>
            </div>

            {{-- TAB ESPECIFICAÇÕES --}}
            <div x-show="leftTab === 'specs'" class="p-3 space-y-3">
                <p class="text-[10px] font-semibold text-zinc-500 uppercase tracking-wider">Especificações do Pedido</p>

                {{-- Produto / Variações --}}
                @foreach($assignment->order->items as $item)
                <div class="rounded-lg bg-zinc-800 p-3 text-xs space-y-1.5">
                    <p class="font-semibold text-zinc-200 leading-tight">{{ $item->name }}</p>
                    @if($item->sku)
                    <p class="text-zinc-500 font-mono">SKU: {{ $item->sku }}</p>
                    @endif
                    <p class="text-zinc-400">Qtd: {{ $item->quantity }}</p>
                    @if($item->variant)
                        @foreach($item->variant->attributes ?? [] as $attr => $val)
                        <div class="flex justify-between">
                            <span class="text-zinc-500 capitalize">{{ $attr }}:</span>
                            <span class="text-zinc-200 font-medium">{{ $val }}</span>
                        </div>
                        @endforeach
                    @endif
                    @foreach($item->meta ?? [] as $key => $val)
                    @if(! in_array($key, ['ml_item_id','ml_category','ml_variation_id','ml_variation_attrs']))
                    <div class="flex justify-between">
                        <span class="text-zinc-500 capitalize">{{ str_replace('_', ' ', $key) }}:</span>
                        <span class="text-zinc-200 font-medium truncate max-w-[100px]">{{ is_array($val) ? json_encode($val) : $val }}</span>
                    </div>
                    @endif
                    @endforeach
                </div>
                @endforeach

                {{-- Info do cliente --}}
                <div class="rounded-lg bg-zinc-800 p-3 text-xs space-y-1">
                    <p class="font-semibold text-zinc-200">Cliente</p>
                    <p class="text-zinc-400">{{ $assignment->order->customer_name }}</p>
                    @if($assignment->order->customer_email)
                    <p class="text-zinc-500 truncate">{{ $assignment->order->customer_email }}</p>
                    @endif
                </div>

                @if($assignment->notes)
                <div class="rounded-lg bg-amber-900/30 border border-amber-700 p-3 text-xs">
                    <p class="font-semibold text-amber-300 mb-1">Observações</p>
                    <p class="text-amber-200">{{ $assignment->notes }}</p>
                </div>
                @endif
            </div>

            {{-- TAB IA --}}
            <div x-show="leftTab === 'ai'" x-cloak class="p-3 space-y-4"
                 x-data="{
                     prompt: '',
                     generating: false,
                     aiImages: [],
                     error: '',
                     async generate() {
                         if (!this.$refs.logoFile.files[0]) { this.error = 'Selecione um logo/arte'; return; }
                         this.generating = true; this.error = ''; this.aiImages = [];
                         const fd = new FormData();
                         fd.append('logo', this.$refs.logoFile.files[0]);
                         fd.append('prompt', this.prompt);
                         fd.append('product_url', DesignEditor.bgUrl || '');
                         const resp = await fetch('{{ route('designer.ai-mockup', $assignment) }}', {
                             method: 'POST',
                             headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                             body: fd
                         });
                         const data = await resp.json();
                         this.generating = false;
                         if (data.error) { this.error = data.error; return; }
                         this.aiImages = data.images || [];
                     },
                     async approve(img) {
                         const resp = await fetch('{{ route('designer.ai-mockup.approve', $assignment) }}', {
                             method: 'POST',
                             headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Content-Type': 'application/json' },
                             body: JSON.stringify({ file_path: img.path, ai_prompt: this.prompt })
                         });
                         const data = await resp.json();
                         if (data.success) {
                             DesignEditor.setBackground(data.mockup_url);
                             this.aiImages = [];
                             alert('Mockup aprovado e carregado no canvas!');
                         }
                     }
                 }">

                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-lg bg-violet-600 flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-white">Gerar com IA</p>
                        <p class="text-[10px] text-zinc-400">OpenAI Image Editing</p>
                    </div>
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="text-xs font-medium text-zinc-400 block mb-1.5">Logo / Arte (PNG recomendado)</label>
                        <label class="block cursor-pointer">
                            <div class="border-2 border-dashed border-zinc-700 hover:border-violet-500 rounded-xl p-4 text-center transition-colors">
                                <svg class="w-6 h-6 text-zinc-500 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                                <p class="text-xs text-zinc-500">Clique para selecionar</p>
                                <p class="text-[10px] text-zinc-600 mt-0.5">PNG com fundo transparente ideal</p>
                            </div>
                            <input type="file" accept="image/*" class="hidden" x-ref="logoFile">
                        </label>
                    </div>

                    <div>
                        <label class="text-xs font-medium text-zinc-400 block mb-1.5">Instruções adicionais (opcional)</label>
                        <textarea x-model="prompt" rows="3" placeholder="Ex: centralizar o logo na frente, manter as cores..."
                                  class="w-full bg-zinc-800 border border-zinc-700 rounded-lg text-xs text-zinc-200 placeholder-zinc-600 p-2.5 resize-none focus:border-violet-500 focus:outline-none"></textarea>
                    </div>

                    <button @click="generate()" :disabled="generating"
                            class="w-full py-2.5 text-sm font-semibold text-white bg-violet-600 hover:bg-violet-500 disabled:opacity-50 rounded-xl transition-colors flex items-center justify-center gap-2">
                        <template x-if="!generating">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                        </template>
                        <template x-if="generating">
                            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        </template>
                        <span x-text="generating ? 'Gerando...' : 'Gerar com IA'"></span>
                    </button>

                    <p x-show="error" class="text-xs text-red-400 bg-red-900/20 rounded-lg p-2" x-text="error"></p>
                </div>

                {{-- Variações geradas --}}
                <template x-if="aiImages.length > 0">
                    <div class="space-y-2">
                        <p class="text-xs font-medium text-zinc-400">Variações geradas — clique em Usar para aplicar</p>
                        <div class="grid grid-cols-2 gap-2">
                            <template x-for="(img, idx) in aiImages" :key="idx">
                                <div class="relative group rounded-lg overflow-hidden border-2 border-zinc-700 hover:border-violet-500 transition-colors cursor-pointer"
                                     @click="approve(img)">
                                    <img :src="img.url" class="w-full aspect-square object-cover" alt="Variação IA">
                                    <div class="absolute inset-0 bg-black/50 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                                        <span class="text-white text-xs font-semibold bg-violet-600 px-2 py-1 rounded-lg">Usar</span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </aside>

    {{-- CENTER CANVAS -------------------------------------------------------}}
    <section class="editor-center flex flex-col bg-zinc-950 items-center justify-center"
             id="canvas-wrap"
             x-data="{ zoom: 100 }">

        {{-- Floating toolbar --}}
        <div class="absolute top-3 left-1/2 -translate-x-1/2 flex items-center gap-2 bg-zinc-800/90 backdrop-blur rounded-xl px-3 py-1.5 z-10 border border-zinc-700">
            <button id="btn-remove" title="Remover objeto selecionado"
                    class="flex items-center gap-1.5 text-xs text-red-400 hover:text-red-300 hover:bg-zinc-700 px-2 py-1 rounded-lg transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                Remover
            </button>
            <div class="w-px h-4 bg-zinc-600"></div>
            <button onclick="DesignEditor.center()" title="Centralizar canvas"
                    class="text-xs text-zinc-400 hover:text-white hover:bg-zinc-700 px-2 py-1 rounded-lg transition-colors">
                Centralizar
            </button>
            <div class="w-px h-4 bg-zinc-600"></div>
            <span class="text-xs text-zinc-500">Zoom:</span>
            <button @click="DesignEditor.zoom(-10); zoom = Math.max(10, zoom-10)" class="w-6 h-6 flex items-center justify-center text-zinc-400 hover:text-white hover:bg-zinc-700 rounded transition-colors text-base leading-none">−</button>
            <span class="text-xs text-zinc-300 w-8 text-center" x-text="zoom + '%'"></span>
            <button @click="DesignEditor.zoom(10); zoom = Math.min(200, zoom+10)" class="w-6 h-6 flex items-center justify-center text-zinc-400 hover:text-white hover:bg-zinc-700 rounded transition-colors text-base leading-none">+</button>
        </div>

        <canvas id="fabric-canvas"></canvas>

        {{-- Upload de imagem (arquivo) oculto --}}
        <input type="file" id="img-file-input" accept="image/*" class="hidden">
    </section>

    {{-- PANEL RIGHT ---------------------------------------------------------}}
    <aside class="panel-right flex flex-col bg-zinc-900 border-l border-zinc-800 overflow-y-auto">

        {{-- Camadas --}}
        <div class="p-3 border-b border-zinc-800">
            <p class="text-[10px] font-semibold text-zinc-500 uppercase tracking-wider mb-2">Camadas</p>
            <div id="layers-list" class="space-y-0.5 max-h-48 overflow-y-auto">
                <p class="text-xs text-zinc-600 italic">Sem objetos no canvas.</p>
            </div>
        </div>

        {{-- Propriedades do objeto selecionado --}}
        <div class="p-3 border-b border-zinc-800" id="props-panel">
            <p class="text-[10px] font-semibold text-zinc-500 uppercase tracking-wider mb-3">Propriedades</p>

            <div id="no-selection" class="text-xs text-zinc-600 italic">
                Selecione um elemento para editar suas propriedades.
            </div>

            <div id="obj-props" class="hidden space-y-3">
                {{-- Posição / Tamanho --}}
                <div>
                    <p class="text-[10px] text-zinc-500 mb-1.5">Dimensões precisas (cm)</p>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-[10px] text-zinc-500">L</label>
                            <input type="number" id="prop-width" class="w-full bg-zinc-800 border border-zinc-700 rounded text-xs text-zinc-200 px-2 py-1 focus:outline-none focus:border-primary-500">
                        </div>
                        <div>
                            <label class="text-[10px] text-zinc-500">A</label>
                            <input type="number" id="prop-height" class="w-full bg-zinc-800 border border-zinc-700 rounded text-xs text-zinc-200 px-2 py-1 focus:outline-none focus:border-primary-500">
                        </div>
                        <div>
                            <label class="text-[10px] text-zinc-500">X</label>
                            <input type="number" id="prop-x" class="w-full bg-zinc-800 border border-zinc-700 rounded text-xs text-zinc-200 px-2 py-1 focus:outline-none focus:border-primary-500">
                        </div>
                        <div>
                            <label class="text-[10px] text-zinc-500">Y</label>
                            <input type="number" id="prop-y" class="w-full bg-zinc-800 border border-zinc-700 rounded text-xs text-zinc-200 px-2 py-1 focus:outline-none focus:border-primary-500">
                        </div>
                    </div>
                    <button id="btn-apply-dims" class="mt-1.5 w-full text-xs bg-zinc-700 hover:bg-zinc-600 text-zinc-200 rounded py-1 transition-colors">Aplicar</button>
                </div>

                {{-- Opacidade --}}
                <div>
                    <label class="text-[10px] text-zinc-500 block mb-1">Opacidade</label>
                    <input type="range" id="prop-opacity" min="0" max="100" value="100"
                           class="w-full accent-primary-500">
                </div>

                {{-- Cor (objetos) --}}
                <div id="color-section">
                    <label class="text-[10px] text-zinc-500 block mb-1">Cor de preenchimento</label>
                    <input type="color" id="prop-fill" value="#ffffff"
                           class="w-full h-8 rounded cursor-pointer border border-zinc-700 bg-zinc-800">
                </div>

                {{-- Texto --}}
                <div id="text-section" class="hidden space-y-2">
                    <div>
                        <label class="text-[10px] text-zinc-500 block mb-1">Fonte</label>
                        <select id="prop-font" class="w-full bg-zinc-800 border border-zinc-700 rounded text-xs text-zinc-200 px-2 py-1 focus:outline-none">
                            <option>Arial</option><option>Helvetica</option><option>Times New Roman</option>
                            <option>Georgia</option><option>Courier New</option><option>Verdana</option>
                            <option>Impact</option><option>Comic Sans MS</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-[10px] text-zinc-500 block mb-1">Tamanho</label>
                        <input type="number" id="prop-fontsize" value="24" min="8" max="200"
                               class="w-full bg-zinc-800 border border-zinc-700 rounded text-xs text-zinc-200 px-2 py-1 focus:outline-none">
                    </div>
                    <div class="flex gap-2">
                        <button id="prop-bold" class="flex-1 text-xs bg-zinc-800 hover:bg-zinc-700 text-zinc-300 rounded py-1.5 font-bold transition-colors">B</button>
                        <button id="prop-italic" class="flex-1 text-xs bg-zinc-800 hover:bg-zinc-700 text-zinc-300 rounded py-1.5 italic transition-colors">I</button>
                        <button id="prop-underline" class="flex-1 text-xs bg-zinc-800 hover:bg-zinc-700 text-zinc-300 rounded py-1.5 underline transition-colors">U</button>
                    </div>
                </div>

                {{-- Borda --}}
                <div>
                    <label class="text-[10px] text-zinc-500 block mb-1">Cor da borda</label>
                    <input type="color" id="prop-stroke" value="#000000"
                           class="w-full h-7 rounded cursor-pointer border border-zinc-700 bg-zinc-800">
                    <input type="range" id="prop-stroke-width" min="0" max="20" value="0"
                           class="w-full mt-1 accent-primary-500">
                </div>
            </div>
        </div>

        {{-- Arquivos de produção --}}
        <div class="p-3" x-data="FileUploader()">
            <div class="flex items-center justify-between mb-2">
                <p class="text-[10px] font-semibold text-zinc-500 uppercase tracking-wider">Arquivos de Produção</p>
                <button @click="$refs.fileInput.click()" :disabled="uploading"
                        class="text-xs text-primary-400 hover:text-primary-300 disabled:opacity-50">
                    + Adicionar
                </button>
            </div>
            <input type="file" multiple x-ref="fileInput" class="hidden"
                   accept=".pdf,.ai,.eps,.svg,.png,.jpg,.psd"
                   @change="upload($event.target.files)">

            <div class="space-y-1.5" id="files-list">
                @foreach($assignment->productionFiles as $file)
                <div class="flex items-center gap-2 p-2 bg-zinc-800 rounded-lg text-xs group">
                    <svg class="w-3.5 h-3.5 text-zinc-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <a href="{{ $file->publicUrl() }}" target="_blank" class="flex-1 truncate text-zinc-300 hover:text-white">{{ $file->file_name }}</a>
                    <span class="text-zinc-600 flex-shrink-0">{{ $file->fileSizeFormatted() }}</span>
                    <button onclick="deleteFile({{ $file->id }}, this.closest('div'))" class="opacity-0 group-hover:opacity-100 text-red-400 hover:text-red-300 transition-opacity">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                @endforeach
            </div>

            <div x-show="uploading" class="mt-2 text-xs text-zinc-500 flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                Enviando...
            </div>
        </div>

    </aside>
</main>

{{-- SCRIPTS ----------------------------------------------------------------}}
<script>
const ASSIGNMENT_ID = {{ $assignment->id }};
const SAVE_URL      = '{{ route('designer.save', $assignment) }}';
const COMPLETE_URL  = '{{ route('designer.complete', $assignment) }}';
const FILES_URL     = '{{ route('designer.files.store', $assignment) }}';
const CSRF_TOKEN    = document.querySelector('meta[name=csrf-token]').content;

const INITIAL_STATE = @json($assignment->canvas_state);
const INITIAL_BG    = @json(optional($productImages->first())->url);

// ─── Fabric.js Editor ──────────────────────────────────────────────────────
const DesignEditor = (() => {
    let canvas, currentZoom = 1, selectedObj = null, bgUrl = INITIAL_BG || '';

    function init() {
        canvas = new fabric.Canvas('fabric-canvas', {
            width: 700,
            height: 700,
            backgroundColor: '#1a1a1a',
            preserveObjectStacking: true,
        });

        // Carregar estado salvo ou fundo padrão
        if (INITIAL_STATE && INITIAL_STATE.objects) {
            canvas.loadFromJSON(INITIAL_STATE, () => {
                canvas.renderAll();
                updateLayers();
            });
        } else if (INITIAL_BG) {
            setBackground(INITIAL_BG);
        }

        // Eventos
        canvas.on('selection:created', onSelect);
        canvas.on('selection:updated', onSelect);
        canvas.on('selection:cleared', onDeselect);
        canvas.on('object:added', updateLayers);
        canvas.on('object:removed', updateLayers);
        canvas.on('object:modified', updateLayers);

        // Keyboard shortcuts
        document.addEventListener('keydown', e => {
            if ((e.key === 'Delete' || e.key === 'Backspace') && !['INPUT','TEXTAREA'].includes(e.target.tagName)) {
                removeSelected();
            }
        });

        bindControls();
    }

    function setBackground(url) {
        bgUrl = url;
        fabric.Image.fromURL(url, img => {
            const scaleX = canvas.width  / img.width;
            const scaleY = canvas.height / img.height;
            const scale  = Math.min(scaleX, scaleY);
            img.scale(scale).set({ originX: 'center', originY: 'center', left: canvas.width/2, top: canvas.height/2 });
            canvas.setBackgroundImage(img, canvas.renderAll.bind(canvas));
        }, { crossOrigin: 'Anonymous' });
    }

    function addText() {
        const text = new fabric.IText('Clique para editar', {
            left: 100, top: 100, fontSize: 32, fill: '#ffffff',
            fontFamily: 'Arial', shadow: 'rgba(0,0,0,0.5) 2px 2px 4px',
        });
        canvas.add(text).setActiveObject(text);
    }

    function addRect() {
        const rect = new fabric.Rect({ left: 100, top: 100, width: 200, height: 100, fill: '#4F46E5', rx: 8, ry: 8 });
        canvas.add(rect).setActiveObject(rect);
    }

    function addCircle() {
        const circle = new fabric.Circle({ left: 100, top: 100, radius: 60, fill: '#7C3AED' });
        canvas.add(circle).setActiveObject(circle);
    }

    function addImageFromFile(file) {
        const reader = new FileReader();
        reader.onload = e => {
            fabric.Image.fromURL(e.target.result, img => {
                img.scaleToWidth(Math.min(300, canvas.width * 0.5));
                img.set({ left: 50, top: 50 });
                canvas.add(img).setActiveObject(img);
            });
        };
        reader.readAsDataURL(file);
    }

    function addImageFromUrl(url) {
        fabric.Image.fromURL(url, img => {
            img.scaleToWidth(Math.min(300, canvas.width * 0.5));
            img.set({ left: 50, top: 50 });
            canvas.add(img).setActiveObject(img);
        }, { crossOrigin: 'Anonymous' });
    }

    function removeSelected() {
        const obj = canvas.getActiveObject();
        if (obj) { canvas.remove(obj); canvas.discardActiveObject(); canvas.renderAll(); }
    }

    function zoom(delta) {
        currentZoom = Math.max(0.1, Math.min(2, currentZoom + delta / 100));
        canvas.setZoom(currentZoom);
        canvas.setWidth(700 * currentZoom);
        canvas.setHeight(700 * currentZoom);
    }

    function center() {
        currentZoom = 1;
        canvas.setZoom(1);
        canvas.setWidth(700);
        canvas.setHeight(700);
    }

    function updateLayers() {
        const list = document.getElementById('layers-list');
        const objs = canvas.getObjects();
        if (objs.length === 0) {
            list.innerHTML = '<p class="text-xs text-zinc-600 italic">Sem objetos.</p>';
            return;
        }
        list.innerHTML = [...objs].reverse().map((obj, i) => {
            const idx = objs.length - 1 - i;
            const label = obj.type === 'i-text' || obj.type === 'text'
                ? `"${obj.text?.substring(0, 20) || 'Texto'}"`
                : obj.type === 'image' ? '🖼 Imagem'
                : obj.type === 'rect' ? '▭ Retângulo'
                : obj.type === 'circle' ? '○ Círculo'
                : obj.type;
            return `<div class="layer-item ${canvas.getActiveObject() === obj ? 'selected' : ''}"
                         onclick="DesignEditor.selectLayer(${idx})">
                <span class="text-zinc-400 text-xs">${label}</span>
            </div>`;
        }).join('');
    }

    function selectLayer(idx) {
        const objs = canvas.getObjects();
        if (objs[idx]) { canvas.setActiveObject(objs[idx]); canvas.renderAll(); updateLayers(); }
    }

    function onSelect(e) {
        selectedObj = canvas.getActiveObject();
        if (!selectedObj) return;
        document.getElementById('no-selection').classList.add('hidden');
        document.getElementById('obj-props').classList.remove('hidden');

        document.getElementById('prop-width').value  = Math.round(selectedObj.width  * selectedObj.scaleX);
        document.getElementById('prop-height').value = Math.round(selectedObj.height * selectedObj.scaleY);
        document.getElementById('prop-x').value = Math.round(selectedObj.left);
        document.getElementById('prop-y').value = Math.round(selectedObj.top);
        document.getElementById('prop-opacity').value = Math.round((selectedObj.opacity || 1) * 100);
        document.getElementById('prop-fill').value = rgbToHex(selectedObj.fill) || '#ffffff';
        document.getElementById('prop-stroke').value = rgbToHex(selectedObj.stroke) || '#000000';
        document.getElementById('prop-stroke-width').value = selectedObj.strokeWidth || 0;

        const isText = ['i-text','text','textbox'].includes(selectedObj.type);
        document.getElementById('text-section').classList.toggle('hidden', !isText);
        if (isText) {
            document.getElementById('prop-font').value = selectedObj.fontFamily || 'Arial';
            document.getElementById('prop-fontsize').value = selectedObj.fontSize || 24;
        }
        updateLayers();
    }

    function onDeselect() {
        selectedObj = null;
        document.getElementById('no-selection').classList.remove('hidden');
        document.getElementById('obj-props').classList.add('hidden');
        updateLayers();
    }

    function bindControls() {
        // Ferramentas
        document.querySelectorAll('.tool-btn').forEach(btn => btn.addEventListener('click', function() {
            document.querySelectorAll('.tool-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
        }));
        document.getElementById('tool-text').addEventListener('click', addText);
        document.getElementById('tool-rect').addEventListener('click', addRect);
        document.getElementById('tool-circle').addEventListener('click', addCircle);
        document.getElementById('tool-draw').addEventListener('click', () => {
            canvas.isDrawingMode = !canvas.isDrawingMode;
            canvas.freeDrawingBrush.color = '#ffffff';
            canvas.freeDrawingBrush.width = 3;
        });
        document.getElementById('tool-image').addEventListener('click', () => {
            document.getElementById('img-file-input').click();
        });
        document.getElementById('img-file-input').addEventListener('change', e => {
            if (e.target.files[0]) addImageFromFile(e.target.files[0]);
        });
        document.getElementById('bg-file-input')?.addEventListener('change', e => {
            if (e.target.files[0]) {
                const reader = new FileReader();
                reader.onload = ev => setBackground(ev.target.result);
                reader.readAsDataURL(e.target.files[0]);
            }
        });

        // Botões do topo
        document.getElementById('btn-remove').addEventListener('click', removeSelected);
        document.getElementById('btn-save-draft').addEventListener('click', saveDraft);
        document.getElementById('btn-gen-mockup').addEventListener('click', generateMockup);
        document.getElementById('btn-finalize').addEventListener('click', finalize);

        // Propriedades
        document.getElementById('prop-opacity').addEventListener('input', e => {
            if (selectedObj) { selectedObj.set('opacity', e.target.value / 100); canvas.renderAll(); }
        });
        document.getElementById('prop-fill').addEventListener('input', e => {
            if (selectedObj) { selectedObj.set('fill', e.target.value); canvas.renderAll(); }
        });
        document.getElementById('prop-stroke').addEventListener('input', e => {
            if (selectedObj) { selectedObj.set('stroke', e.target.value); canvas.renderAll(); }
        });
        document.getElementById('prop-stroke-width').addEventListener('input', e => {
            if (selectedObj) { selectedObj.set('strokeWidth', parseInt(e.target.value)); canvas.renderAll(); }
        });
        document.getElementById('prop-font')?.addEventListener('change', e => {
            if (selectedObj && selectedObj.set) { selectedObj.set('fontFamily', e.target.value); canvas.renderAll(); }
        });
        document.getElementById('prop-fontsize')?.addEventListener('input', e => {
            if (selectedObj) { selectedObj.set('fontSize', parseInt(e.target.value)); canvas.renderAll(); }
        });
        document.getElementById('prop-bold')?.addEventListener('click', () => {
            if (selectedObj) { selectedObj.set('fontWeight', selectedObj.fontWeight === 'bold' ? 'normal' : 'bold'); canvas.renderAll(); }
        });
        document.getElementById('prop-italic')?.addEventListener('click', () => {
            if (selectedObj) { selectedObj.set('fontStyle', selectedObj.fontStyle === 'italic' ? 'normal' : 'italic'); canvas.renderAll(); }
        });
        document.getElementById('prop-underline')?.addEventListener('click', () => {
            if (selectedObj) { selectedObj.set('underline', !selectedObj.underline); canvas.renderAll(); }
        });
        document.getElementById('btn-apply-dims')?.addEventListener('click', () => {
            if (!selectedObj) return;
            const w = parseFloat(document.getElementById('prop-width').value);
            const h = parseFloat(document.getElementById('prop-height').value);
            const x = parseFloat(document.getElementById('prop-x').value);
            const y = parseFloat(document.getElementById('prop-y').value);
            selectedObj.set({ left: x, top: y, scaleX: w / selectedObj.width, scaleY: h / selectedObj.height });
            canvas.renderAll();
        });
    }

    async function saveDraft() {
        const state = canvas.toJSON(['id','name']);
        const btn   = document.getElementById('btn-save-draft');
        btn.disabled = true; btn.textContent = 'Salvando...';
        try {
            const resp = await fetch(SAVE_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
                body: JSON.stringify({ canvas_state: state }),
            });
            const data = await resp.json();
            btn.textContent = data.success ? '✓ Salvo' : 'Erro';
            setTimeout(() => { btn.textContent = 'Salvar Rascunho'; btn.disabled = false; }, 2000);
        } catch { btn.textContent = 'Erro'; btn.disabled = false; }
    }

    function generateMockup() {
        const dataUrl = canvas.toDataURL({ format: 'png', quality: 0.95 });
        const link = document.createElement('a');
        link.href = dataUrl; link.download = `mockup-${ASSIGNMENT_ID}.png`; link.click();
    }

    async function finalize() {
        if (!confirm('Finalizar o design? O mockup gerado será salvo e o pedido avançará na produção.')) return;
        const state   = canvas.toJSON(['id','name']);
        const png     = canvas.toDataURL({ format: 'png', quality: 0.95 });
        const btn     = document.getElementById('btn-finalize');
        btn.disabled  = true; btn.textContent = 'Finalizando...';
        try {
            const resp = await fetch(COMPLETE_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
                body: JSON.stringify({ canvas_state: state, mockup_base64: png }),
            });
            const data = await resp.json();
            if (data.success) {
                window.location.href = data.redirect;
            } else {
                btn.disabled = false; btn.textContent = 'Finalizar Designer';
                alert('Erro: ' + (data.message || 'Tente novamente.'));
            }
        } catch { btn.disabled = false; btn.textContent = 'Finalizar Designer'; }
    }

    function rgbToHex(color) {
        if (!color || color.startsWith('#')) return color;
        const m = color.match(/\d+/g);
        if (!m || m.length < 3) return '#000000';
        return '#' + m.slice(0,3).map(n => parseInt(n).toString(16).padStart(2,'0')).join('');
    }

    return { init, setBackground, zoom, center, selectLayer, bgUrl: () => bgUrl };
})();

// ─── Alpine Data: File Uploader ────────────────────────────────────────────
function FileUploader() {
    return {
        uploading: false,
        async upload(files) {
            if (!files.length) return;
            this.uploading = true;
            const fd = new FormData();
            for (const f of files) fd.append('files[]', f);
            fd.append('file_type', 'production_file');
            try {
                const resp = await fetch(FILES_URL, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
                    body: fd,
                });
                const data = await resp.json();
                if (data.success) {
                    const list = document.getElementById('files-list');
                    data.files.forEach(f => {
                        list.insertAdjacentHTML('beforeend', `
                            <div class="flex items-center gap-2 p-2 bg-zinc-800 rounded-lg text-xs group">
                                <svg class="w-3.5 h-3.5 text-zinc-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                <a href="${f.url}" target="_blank" class="flex-1 truncate text-zinc-300 hover:text-white">${f.name}</a>
                                <span class="text-zinc-600 flex-shrink-0">${f.size}</span>
                                <button onclick="deleteFile(${f.id}, this.closest('div'))" class="opacity-0 group-hover:opacity-100 text-red-400 hover:text-red-300 transition-opacity">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>`);
                    });
                }
            } finally {
                this.uploading = false;
            }
        }
    };
}

async function deleteFile(fileId, el) {
    if (!confirm('Remover arquivo?')) return;
    const resp = await fetch(`/designer/files/${fileId}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
    });
    if ((await resp.json()).success) el?.remove();
}

// Init
document.addEventListener('DOMContentLoaded', () => DesignEditor.init());
</script>

</body>
</html>
