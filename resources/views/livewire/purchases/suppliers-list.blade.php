<div>
    <div class="flex justify-end mb-4">
        <button wire:click="openForm" class="btn-primary">
            <x-heroicon-o-plus class="w-4 h-4" /> Novo Fornecedor
        </button>
    </div>

    <x-ui.card :padding="false">
        @if(empty($suppliers))
            <div class="text-center py-10">
                <x-heroicon-o-building-storefront class="w-10 h-10 text-gray-300 dark:text-zinc-600 mx-auto mb-2" />
                <p class="text-gray-500 dark:text-zinc-400">Nenhum fornecedor cadastrado.</p>
            </div>
        @else
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Contato</th>
                        <th>Email</th>
                        <th>Telefone</th>
                        <th class="text-center">Ativo</th>
                        <th class="text-right">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($suppliers as $s)
                    <tr class="{{ !$s['is_active'] ? 'opacity-50' : '' }}">
                        <td>
                            <span class="font-semibold text-gray-900 dark:text-white">{{ $s['name'] }}</span>
                            @if($s['website'])
                                <a href="{{ $s['website'] }}" target="_blank" class="block text-xs text-primary-600 dark:text-primary-400 hover:underline truncate max-w-xs">{{ $s['website'] }}</a>
                            @endif
                        </td>
                        <td class="text-sm text-gray-500 dark:text-zinc-400">{{ $s['contact_name'] ?? '—' }}</td>
                        <td class="text-sm text-gray-500 dark:text-zinc-400">{{ $s['email'] ?? '—' }}</td>
                        <td class="text-sm text-gray-500 dark:text-zinc-400">{{ $s['phone'] ?? $s['whatsapp'] ?? '—' }}</td>
                        <td class="text-center">
                            <button wire:click="toggleActive({{ $s['id'] }})"
                                    class="relative inline-flex h-5 w-9 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 {{ $s['is_active'] ? 'bg-primary-500' : 'bg-gray-300 dark:bg-zinc-600' }}">
                                <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform duration-200 {{ $s['is_active'] ? 'translate-x-4' : 'translate-x-0' }}"></span>
                            </button>
                        </td>
                        <td class="text-right">
                            <div class="flex items-center justify-end gap-1">
                                <button wire:click="openForm({{ $s['id'] }})" class="btn-ghost btn-xs" title="Editar">
                                    <x-heroicon-o-pencil class="w-3.5 h-3.5" />
                                </button>
                                <button wire:click="delete({{ $s['id'] }})" wire:confirm="Remover fornecedor {{ $s['name'] }}?"
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

    {{-- Modal --}}
    @if($showForm)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
         @keydown.escape.window="$wire.set('showForm', false)">
        <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-xl w-full max-w-lg mx-4 p-6"
             @click.outside="$wire.set('showForm', false)">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                {{ $editingId ? 'Editar Fornecedor' : 'Novo Fornecedor' }}
            </h3>

            <div class="space-y-4">
                <div>
                    <label class="form-label">Nome *</label>
                    <input type="text" wire:model="formName" class="form-input" placeholder="Nome do fornecedor" autofocus>
                    @error('formName') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Contato</label>
                        <input type="text" wire:model="formContactName" class="form-input" placeholder="Nome do contato">
                    </div>
                    <div>
                        <label class="form-label">Email</label>
                        <input type="email" wire:model="formEmail" class="form-input" placeholder="email@fornecedor.com">
                        @error('formEmail') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Telefone</label>
                        <input type="text" wire:model="formPhone" class="form-input" placeholder="(11) 9999-9999">
                    </div>
                    <div>
                        <label class="form-label">WhatsApp</label>
                        <input type="text" wire:model="formWhatsapp" class="form-input" placeholder="(11) 99999-9999">
                    </div>
                </div>
                <div>
                    <label class="form-label">Website</label>
                    <input type="text" wire:model="formWebsite" class="form-input" placeholder="https://...">
                </div>
                <div>
                    <label class="form-label">Observações</label>
                    <textarea wire:model="formNotes" rows="2" class="form-input" placeholder="Notas sobre o fornecedor..."></textarea>
                </div>
            </div>

            <div class="flex justify-end gap-3 mt-6">
                <button wire:click="$set('showForm', false)" class="btn-secondary">Cancelar</button>
                <button wire:click="save" class="btn-primary">{{ $editingId ? 'Salvar' : 'Criar' }}</button>
            </div>
        </div>
    </div>
    @endif
</div>
