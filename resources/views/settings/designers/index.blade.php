<x-app-layout>
    <x-slot name="header">Configurações de Designers</x-slot>
    <x-slot name="breadcrumbs">
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <a href="{{ route('settings.index') }}" class="hover:text-gray-700 dark:hover:text-zinc-200">Configurações</a>
        </li>
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <span class="text-gray-700 dark:text-zinc-200">Designers</span>
        </li>
    </x-slot>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Lista de designers --}}
        <div class="lg:col-span-2 space-y-4">
            <x-ui.card title="Designers da Empresa">
                @if($designers->isEmpty())
                <div class="text-center py-8">
                    <x-heroicon-o-paint-brush class="w-10 h-10 text-gray-300 dark:text-zinc-600 mx-auto mb-2" />
                    <p class="text-gray-500 dark:text-zinc-400 text-sm">Nenhum designer cadastrado.</p>
                </div>
                @else
                <div class="space-y-2">
                    @foreach($designers as $designer)
                    @php $isActive = in_array($designer->id, $designerSettings['designer_ids']); @endphp
                    <div class="flex items-center gap-3 p-3 rounded-xl border
                                {{ $isActive ? 'border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-900/10' : 'border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800' }}">
                        <div class="w-8 h-8 rounded-full bg-primary-600 flex items-center justify-center text-white text-sm font-bold flex-shrink-0">
                            {{ strtoupper(substr($designer->name, 0, 1)) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-sm">{{ $designer->name }}</p>
                            <p class="text-xs text-gray-400 dark:text-zinc-500">{{ $designer->email }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-gray-400">{{ $designer->design_assignments_count }} pedidos</span>
                            @if($isActive)
                            <x-ui.badge color="success" class="text-xs">Ativo no RR</x-ui.badge>
                            @else
                            <x-ui.badge color="default" class="text-xs">Inativo</x-ui.badge>
                            @endif
                            <form method="POST" action="{{ route('settings.designers.toggle', $designer) }}">
                                @csrf @method('PATCH')
                                <button type="submit"
                                        class="text-xs {{ $isActive ? 'btn-secondary text-red-600' : 'btn-secondary text-green-600' }} btn-xs">
                                    {{ $isActive ? 'Remover do RR' : 'Adicionar ao RR' }}
                                </button>
                            </form>
                            <form method="POST" action="{{ route('settings.designers.remove', $designer) }}"
                                  onsubmit="return confirm('Remover {{ $designer->name }}?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-danger btn-xs">
                                    <x-heroicon-o-trash class="w-3.5 h-3.5" />
                                </button>
                            </form>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif

                {{-- Preview da fila round-robin --}}
                @if(count($designerSettings['designer_ids']) > 0)
                <div class="mt-4 p-3 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800">
                    <p class="text-xs font-medium text-blue-700 dark:text-blue-300 mb-1.5">Fila Round-Robin atual</p>
                    <div class="flex items-center gap-1.5 flex-wrap">
                        @foreach($designerSettings['designer_ids'] as $idx => $did)
                        @php $d = $designers->firstWhere('id', $did); @endphp
                        @if($d)
                        <div class="flex items-center gap-1.5 text-xs px-2 py-1 rounded-full
                                    {{ $idx === $designerSettings['rr_pointer'] ? 'bg-blue-600 text-white' : 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300' }}">
                            @if($idx === $designerSettings['rr_pointer'])
                            <x-heroicon-s-arrow-right class="w-3 h-3" />
                            @endif
                            {{ $d->name }}
                        </div>
                        @endif
                        @endforeach
                    </div>
                    <p class="text-[10px] text-blue-500 dark:text-blue-400 mt-1.5">
                        Próximo pedido → <strong>{{ $designers->firstWhere('id', $designerSettings['designer_ids'][$designerSettings['rr_pointer']] ?? null)?->name ?? '—' }}</strong>
                    </p>
                </div>
                @endif
            </x-ui.card>

            {{-- Convidar novo designer --}}
            <x-ui.card title="Convidar Novo Designer">
                <form method="POST" action="{{ route('settings.designers.invite') }}" class="space-y-3">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div>
                            <label class="form-label">Nome <span class="text-red-500">*</span></label>
                            <input type="text" name="name" value="{{ old('name') }}"
                                   placeholder="Nome do designer" class="form-input" required>
                        </div>
                        <div>
                            <label class="form-label">E-mail <span class="text-red-500">*</span></label>
                            <input type="email" name="email" value="{{ old('email') }}"
                                   placeholder="email@empresa.com" class="form-input" required>
                        </div>
                        <div>
                            <label class="form-label">Senha <span class="text-red-500">*</span></label>
                            <input type="password" name="password" placeholder="Mínimo 8 caracteres"
                                   class="form-input" required minlength="8">
                        </div>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="btn-primary btn-sm">
                            <x-heroicon-o-user-plus class="w-4 h-4" />
                            Criar Designer
                        </button>
                    </div>
                </form>
            </x-ui.card>
        </div>

        {{-- Configurações de distribuição e IA --}}
        <div class="space-y-4">
            <x-ui.card title="Distribuição de Pedidos">
                <form method="POST" action="{{ route('settings.designers.update') }}" class="space-y-4">
                    @csrf @method('PUT')

                    <div>
                        <label class="form-label">Método de Distribuição</label>
                        <select name="distribution" class="form-input">
                            <option value="round_robin" {{ $designerSettings['distribution'] === 'round_robin' ? 'selected' : '' }}>
                                Round-Robin (sequencial)
                            </option>
                            <option value="random" {{ $designerSettings['distribution'] === 'random' ? 'selected' : '' }}>
                                Aleatório
                            </option>
                            <option value="manual" {{ $designerSettings['distribution'] === 'manual' ? 'selected' : '' }}>
                                Manual (sem auto-distribuição)
                            </option>
                        </select>
                        <p class="text-xs text-gray-400 dark:text-zinc-500 mt-1">
                            Round-Robin distribui sequencialmente entre os designers ativos.
                        </p>
                    </div>

                    <div>
                        <label class="form-label">Designers Participantes</label>
                        <div class="space-y-2 mt-1">
                            @foreach($designers as $d)
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="designer_ids[]" value="{{ $d->id }}"
                                       {{ in_array($d->id, $designerSettings['designer_ids']) ? 'checked' : '' }}
                                       class="rounded border-gray-300 text-primary-600">
                                <span class="text-sm">{{ $d->name }}</span>
                            </label>
                            @endforeach
                            @if($designers->isEmpty())
                            <p class="text-xs text-gray-400 italic">Cadastre designers primeiro.</p>
                            @endif
                        </div>
                    </div>

                    <button type="submit" class="btn-primary w-full">
                        <x-heroicon-o-check class="w-4 h-4" />
                        Salvar Configurações
                    </button>
                </form>
            </x-ui.card>

            {{-- Configurações de IA --}}
            <x-ui.card title="Geração de Mockup com IA">
                <form method="POST" action="{{ route('settings.designers.update') }}" class="space-y-4">
                    @csrf @method('PUT')
                    {{-- Mantém os valores de distribuição --}}
                    <input type="hidden" name="distribution" value="{{ $designerSettings['distribution'] }}">
                    @foreach($designerSettings['designer_ids'] as $did)
                    <input type="hidden" name="designer_ids[]" value="{{ $did }}">
                    @endforeach

                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium">Habilitar IA no Editor</p>
                            <p class="text-xs text-gray-400 dark:text-zinc-500">Usa OpenAI para gerar mockups automáticos</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="ai_enabled" value="1"
                                   {{ $designerSettings['ai_enabled'] ? 'checked' : '' }} class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 dark:bg-zinc-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary-600"></div>
                        </label>
                    </div>

                    <div>
                        <label class="form-label">Prefix do Prompt de IA</label>
                        <textarea name="ai_prompt" rows="3" class="form-input text-sm"
                                  placeholder="Ex: Produto de brinde personalizado, manter cores da marca...">{{ $designerSettings['ai_prompt'] }}</textarea>
                        <p class="text-xs text-gray-400 dark:text-zinc-500 mt-1">
                            Adicionado ao prompt de cada geração. Descreva características específicas dos produtos da empresa.
                        </p>
                    </div>

                    <button type="submit" class="btn-primary w-full">
                        <x-heroicon-o-check class="w-4 h-4" />
                        Salvar IA
                    </button>
                </form>
            </x-ui.card>
        </div>
    </div>
</x-app-layout>
