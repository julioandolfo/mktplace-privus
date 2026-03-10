<x-app-layout>
    <x-slot name="header">Operadores de Expedição</x-slot>
    <x-slot name="subtitle">Cadastre as pessoas que conferem, embalam e despacham pedidos</x-slot>
    <x-slot name="breadcrumbs">
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <a href="{{ route('settings.index') }}" class="hover:text-gray-700 dark:hover:text-zinc-200">Configurações</a>
        </li>
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <span class="text-gray-700 dark:text-zinc-200">Operadores de Expedição</span>
        </li>
    </x-slot>
    <x-slot name="actions">
        <button wire:click="openForm" class="btn-primary">
            <x-heroicon-o-plus class="w-4 h-4" />
            Novo Operador
        </button>
    </x-slot>

    <div class="space-y-4">
        {{-- Lista --}}
        <x-ui.card :padding="false">
            @if(empty($operators))
                <div class="text-center py-10">
                    <x-heroicon-o-user-group class="w-10 h-10 text-gray-300 dark:text-zinc-600 mx-auto mb-2" />
                    <p class="text-gray-500 dark:text-zinc-400">Nenhum operador cadastrado.</p>
                    <p class="text-sm text-gray-400 dark:text-zinc-500 mt-1">Cadastre os operadores que trabalham na expedição.</p>
                </div>
            @else
                <table class="data-table">
                    <thead>
                        <tr>
                            <th class="w-10">Ordem</th>
                            <th>Nome</th>
                            <th>Função</th>
                            <th class="text-center">Padrão</th>
                            <th class="text-center">Ativo</th>
                            <th class="text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($operators as $op)
                        <tr class="{{ !$op['is_active'] ? 'opacity-50' : '' }}">
                            <td>
                                <div class="flex items-center gap-0.5">
                                    <button wire:click="moveUp({{ $op['id'] }})" class="text-gray-400 hover:text-gray-600 dark:hover:text-zinc-300" title="Subir">
                                        <x-heroicon-o-chevron-up class="w-4 h-4" />
                                    </button>
                                    <button wire:click="moveDown({{ $op['id'] }})" class="text-gray-400 hover:text-gray-600 dark:hover:text-zinc-300" title="Descer">
                                        <x-heroicon-o-chevron-down class="w-4 h-4" />
                                    </button>
                                    <span class="text-xs text-gray-400 ml-1">{{ $op['sort_order'] }}</span>
                                </div>
                            </td>
                            <td>
                                <span class="font-semibold text-gray-900 dark:text-white">{{ $op['name'] }}</span>
                            </td>
                            <td>
                                @if($op['role'])
                                    <span class="text-sm text-gray-500 dark:text-zinc-400">{{ $op['role'] }}</span>
                                @else
                                    <span class="text-gray-300 dark:text-zinc-600">—</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($op['is_default'])
                                    <span class="inline-flex items-center gap-1 text-xs font-bold px-2 py-0.5 rounded-full bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-400">
                                        <x-heroicon-s-star class="w-3 h-3" /> Padrão
                                    </span>
                                @else
                                    <button wire:click="setDefault({{ $op['id'] }})" class="text-xs text-gray-400 hover:text-primary-600 dark:hover:text-primary-400">
                                        Definir padrão
                                    </button>
                                @endif
                            </td>
                            <td class="text-center">
                                <button wire:click="toggleActive({{ $op['id'] }})"
                                        class="relative inline-flex h-5 w-9 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 {{ $op['is_active'] ? 'bg-primary-500' : 'bg-gray-300 dark:bg-zinc-600' }}">
                                    <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform duration-200 {{ $op['is_active'] ? 'translate-x-4' : 'translate-x-0' }}"></span>
                                </button>
                            </td>
                            <td class="text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <button wire:click="openForm({{ $op['id'] }})" class="btn-ghost btn-xs" title="Editar">
                                        <x-heroicon-o-pencil class="w-3.5 h-3.5" />
                                    </button>
                                    <button wire:click="delete({{ $op['id'] }})"
                                            wire:confirm="Remover operador {{ $op['name'] }}?"
                                            class="btn-ghost btn-xs text-red-500 hover:text-red-700" title="Remover">
                                        <x-heroicon-o-trash class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-ui.card>

        {{-- Dica --}}
        <div class="flex items-start gap-3 bg-blue-50 dark:bg-blue-900/10 border border-blue-200 dark:border-blue-800 rounded-xl px-4 py-3">
            <x-heroicon-o-information-circle class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" />
            <div class="text-sm text-blue-700 dark:text-blue-300">
                <p class="font-medium">Como funciona?</p>
                <p class="mt-1 text-blue-600 dark:text-blue-400">
                    Os operadores aparecem nos modais de conferência, embalagem e despacho para registrar quem executou cada etapa.
                    Defina um como <strong>padrão</strong> para que ele já venha pré-selecionado. Use a <strong>ordem</strong> para controlar a posição na lista.
                </p>
            </div>
        </div>
    </div>

    {{-- Modal de criação/edição --}}
    @if($showForm)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
         @keydown.escape.window="$wire.set('showForm', false)">
        <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-xl w-full max-w-md mx-4 p-6"
             @click.outside="$wire.set('showForm', false)">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                {{ $editingId ? 'Editar Operador' : 'Novo Operador' }}
            </h3>

            <div class="space-y-4">
                <div>
                    <label class="form-label">Nome *</label>
                    <input type="text" wire:model="formName" class="form-input" placeholder="Ex: João, Maria..."
                           autofocus>
                    @error('formName') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">Função <span class="text-gray-400 font-normal">(opcional)</span></label>
                    <input type="text" wire:model="formRole" class="form-input" placeholder="Ex: Conferente, Embalador, Expedidor...">
                </div>

                <div>
                    <label class="form-label">Ordem de exibição</label>
                    <input type="number" wire:model="formSort" class="form-input w-24" min="0">
                </div>

                <div class="flex items-center gap-2">
                    <input type="checkbox" wire:model="formDefault" id="op-default"
                           class="rounded border-gray-300 dark:border-zinc-600 text-primary-600 focus:ring-primary-500">
                    <label for="op-default" class="text-sm text-gray-700 dark:text-zinc-300">
                        Definir como operador padrão
                    </label>
                </div>
            </div>

            <div class="flex justify-end gap-3 mt-6">
                <button wire:click="$set('showForm', false)" class="btn-secondary">Cancelar</button>
                <button wire:click="save" class="btn-primary">
                    {{ $editingId ? 'Salvar' : 'Criar Operador' }}
                </button>
            </div>
        </div>
    </div>
    @endif
</x-app-layout>
