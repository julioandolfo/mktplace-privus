<x-app-layout>
    <x-slot name="header">Gerenciamento de Usuarios</x-slot>
    <x-slot name="subtitle">Criar e gerenciar usuarios do sistema</x-slot>
    <x-slot name="breadcrumbs">
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <a href="{{ route('settings.index') }}" class="hover:text-gray-700 dark:hover:text-zinc-200">Configuracoes</a>
        </li>
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <span class="text-gray-700 dark:text-zinc-200">Usuarios</span>
        </li>
    </x-slot>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Lista de usuarios --}}
        <div class="lg:col-span-2 space-y-4">
            <x-ui.card title="Usuarios do Sistema">
                @if($users->isEmpty())
                <div class="text-center py-8">
                    <x-heroicon-o-users class="w-10 h-10 text-gray-300 dark:text-zinc-600 mx-auto mb-2" />
                    <p class="text-gray-500 dark:text-zinc-400 text-sm">Nenhum usuario cadastrado.</p>
                </div>
                @else
                <div class="space-y-2">
                    @foreach($users as $user)
                    <div x-data="{ editing: false }" class="rounded-xl border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800">
                        {{-- Visualizacao --}}
                        <div x-show="!editing" class="flex items-center gap-3 p-3">
                            <div class="w-9 h-9 rounded-full flex items-center justify-center text-white text-sm font-bold flex-shrink-0
                                {{ $user->role === 'admin' ? 'bg-red-500' : ($user->role === 'operator' ? 'bg-blue-500' : 'bg-primary-600') }}">
                                {{ strtoupper(substr($user->name, 0, 1)) }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-sm truncate">{{ $user->name }}</p>
                                <p class="text-xs text-gray-400 dark:text-zinc-500">{{ $user->email }}</p>
                            </div>
                            <div class="flex items-center gap-2 flex-shrink-0">
                                @if($user->role === 'admin')
                                    <x-ui.badge color="danger" class="text-xs">Admin</x-ui.badge>
                                @elseif($user->role === 'operator')
                                    <x-ui.badge color="info" class="text-xs">Operador</x-ui.badge>
                                @elseif($user->role === 'designer')
                                    <x-ui.badge color="success" class="text-xs">Designer</x-ui.badge>
                                @endif

                                <button @click="editing = true" class="btn-secondary btn-xs" title="Editar">
                                    <x-heroicon-o-pencil class="w-3.5 h-3.5" />
                                </button>

                                @if($user->id !== auth()->id())
                                <form method="POST" action="{{ route('settings.users.destroy', $user) }}"
                                      onsubmit="return confirm('Remover {{ $user->name }}? Esta acao nao pode ser desfeita.')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn-danger btn-xs" title="Remover">
                                        <x-heroicon-o-trash class="w-3.5 h-3.5" />
                                    </button>
                                </form>
                                @endif
                            </div>
                        </div>

                        {{-- Edicao inline --}}
                        <div x-show="editing" x-transition class="p-4">
                            <form method="POST" action="{{ route('settings.users.update', $user) }}">
                                @csrf @method('PUT')
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label class="form-label">Nome</label>
                                        <input type="text" name="name" value="{{ $user->name }}" class="form-input" required>
                                    </div>
                                    <div>
                                        <label class="form-label">E-mail</label>
                                        <input type="email" name="email" value="{{ $user->email }}" class="form-input" required>
                                    </div>
                                    <div>
                                        <label class="form-label">Nova Senha</label>
                                        <input type="password" name="password" placeholder="Deixe vazio para manter" class="form-input" minlength="8">
                                    </div>
                                    <div>
                                        <label class="form-label">Perfil</label>
                                        <select name="role" class="form-input">
                                            <option value="admin" {{ $user->role === 'admin' ? 'selected' : '' }}>Administrador</option>
                                            <option value="operator" {{ $user->role === 'operator' ? 'selected' : '' }}>Operador</option>
                                            <option value="designer" {{ $user->role === 'designer' ? 'selected' : '' }}>Designer</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="flex justify-end gap-2 mt-3">
                                    <button type="button" @click="editing = false" class="btn-secondary btn-sm">Cancelar</button>
                                    <button type="submit" class="btn-primary btn-sm">
                                        <x-heroicon-o-check class="w-4 h-4" />
                                        Salvar
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
            </x-ui.card>
        </div>

        {{-- Criar novo usuario --}}
        <div>
            <x-ui.card title="Novo Usuario">
                <form method="POST" action="{{ route('settings.users.store') }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="form-label">Nome <span class="text-red-500">*</span></label>
                        <input type="text" name="name" value="{{ old('name') }}"
                               placeholder="Nome completo" class="form-input" required>
                        @error('name') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="form-label">E-mail <span class="text-red-500">*</span></label>
                        <input type="email" name="email" value="{{ old('email') }}"
                               placeholder="email@empresa.com" class="form-input" required>
                        @error('email') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="form-label">Senha <span class="text-red-500">*</span></label>
                        <input type="password" name="password" placeholder="Minimo 8 caracteres"
                               class="form-input" required minlength="8">
                        @error('password') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="form-label">Perfil <span class="text-red-500">*</span></label>
                        <select name="role" class="form-input" required>
                            <option value="admin">Administrador</option>
                            <option value="operator" selected>Operador</option>
                            <option value="designer">Designer</option>
                        </select>
                        @error('role') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="pt-2">
                        <button type="submit" class="btn-primary w-full">
                            <x-heroicon-o-user-plus class="w-4 h-4" />
                            Criar Usuario
                        </button>
                    </div>
                </form>
            </x-ui.card>

            {{-- Info sobre perfis --}}
            <div class="mt-4">
                <x-ui.card title="Perfis de Acesso">
                    <div class="space-y-3 text-sm">
                        <div class="flex items-start gap-2">
                            <span class="w-2 h-2 rounded-full bg-red-500 mt-1.5 flex-shrink-0"></span>
                            <div>
                                <p class="font-medium text-gray-700 dark:text-zinc-300">Administrador</p>
                                <p class="text-xs text-gray-400 dark:text-zinc-500">Acesso total ao sistema, configuracoes e gestao de usuarios.</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-2">
                            <span class="w-2 h-2 rounded-full bg-blue-500 mt-1.5 flex-shrink-0"></span>
                            <div>
                                <p class="font-medium text-gray-700 dark:text-zinc-300">Operador</p>
                                <p class="text-xs text-gray-400 dark:text-zinc-500">Acesso a expedicao, pedidos e operacoes do dia a dia.</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-2">
                            <span class="w-2 h-2 rounded-full bg-primary-500 mt-1.5 flex-shrink-0"></span>
                            <div>
                                <p class="font-medium text-gray-700 dark:text-zinc-300">Designer</p>
                                <p class="text-xs text-gray-400 dark:text-zinc-500">Acesso ao modulo de design e criacao de artes.</p>
                            </div>
                        </div>
                    </div>
                </x-ui.card>
            </div>
        </div>
    </div>
</x-app-layout>
