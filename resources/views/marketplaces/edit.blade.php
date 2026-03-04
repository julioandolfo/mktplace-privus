<x-app-layout>
    <x-slot name="header">Editar Conta</x-slot>
    <x-slot name="subtitle">{{ $marketplace->account_name }}</x-slot>
    <x-slot name="breadcrumbs">
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <a href="{{ route('marketplaces.index') }}" class="hover:text-gray-700 dark:hover:text-zinc-200">Marketplaces</a>
        </li>
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <span class="text-gray-700 dark:text-zinc-200">{{ $marketplace->account_name }}</span>
        </li>
    </x-slot>

    @php $isOAuth = $marketplace->marketplace_type->supportsOAuth(); @endphp

    <form method="POST" action="{{ route('marketplaces.update', $marketplace) }}">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Main (2/3) --}}
            <div class="lg:col-span-2 space-y-6">

                {{-- Account info --}}
                <x-ui.card title="Informacoes da Conta">
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="account_name" class="form-label">Nome da Conta *</label>
                                <input type="text" id="account_name" name="account_name"
                                       value="{{ old('account_name') ?: $marketplace->account_name }}"
                                       class="form-input" required>
                                @error('account_name') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="shop_id" class="form-label">Shop ID</label>
                                <input type="text" id="shop_id" name="shop_id"
                                       value="{{ old('shop_id') ?: $marketplace->shop_id }}"
                                       class="form-input font-mono">
                            </div>
                        </div>
                    </div>
                </x-ui.card>

                {{-- Credentials --}}
                <x-ui.card title="Credenciais de API">
                    <div class="space-y-4">

                        @if($isOAuth)
                            <div class="flex items-start gap-3 p-3 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800">
                                <x-heroicon-o-information-circle class="w-5 h-5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" />
                                <div class="text-sm text-blue-700 dark:text-blue-300">
                                    <p class="font-medium">Credenciais gerenciadas automaticamente</p>
                                    <p class="mt-0.5">Client ID e Secret sao configurados em
                                        <a href="{{ route('settings.index') }}#marketplaces" class="underline font-medium">Configuracoes &rsaquo; Marketplaces</a>.
                                        Os tokens OAuth sao renovados pelo fluxo de conexao.
                                    </p>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="form-label">Client ID</label>
                                    <input type="text" value="{{ $sysClientId ?: 'Nao configurado' }}"
                                           class="form-input font-mono text-sm bg-gray-50 dark:bg-zinc-800/50 text-gray-400 dark:text-zinc-500 cursor-not-allowed" disabled>
                                </div>
                                <div>
                                    <label class="form-label">Client Secret</label>
                                    <input type="text" value="{{ $sysSecret ? '••••••••' : 'Nao configurado' }}"
                                           class="form-input font-mono text-sm bg-gray-50 dark:bg-zinc-800/50 text-gray-400 dark:text-zinc-500 cursor-not-allowed" disabled>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="form-label">Access Token</label>
                                    <div class="flex items-center gap-2">
                                        <input type="text" value="{{ !empty($creds['access_token']) ? '••••••••••••••••' : 'Nao configurado' }}"
                                               class="form-input font-mono text-sm bg-gray-50 dark:bg-zinc-800/50 text-gray-400 dark:text-zinc-500 cursor-not-allowed flex-1" disabled>
                                        @if(!empty($creds['access_token']))
                                            <x-heroicon-s-check-circle class="w-5 h-5 text-green-500 flex-shrink-0" />
                                        @endif
                                    </div>
                                </div>
                                <div>
                                    <label class="form-label">Refresh Token</label>
                                    <div class="flex items-center gap-2">
                                        <input type="text" value="{{ !empty($creds['refresh_token']) ? '••••••••••••••••' : 'Nao configurado' }}"
                                               class="form-input font-mono text-sm bg-gray-50 dark:bg-zinc-800/50 text-gray-400 dark:text-zinc-500 cursor-not-allowed flex-1" disabled>
                                        @if(!empty($creds['refresh_token']))
                                            <x-heroicon-s-check-circle class="w-5 h-5 text-green-500 flex-shrink-0" />
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="pt-2 border-t border-gray-200 dark:border-zinc-700">
                                <a href="{{ route('marketplaces.oauth.redirect', $marketplace->marketplace_type->value) }}"
                                   class="btn-secondary btn-sm">
                                    <x-heroicon-o-arrow-path class="w-4 h-4" />
                                    Reconectar via OAuth
                                </a>
                            </div>

                        @else
                            <div class="p-3 rounded-lg bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800">
                                <div class="flex gap-2">
                                    <x-heroicon-o-shield-check class="w-5 h-5 text-yellow-600 dark:text-yellow-400 flex-shrink-0 mt-0.5" />
                                    <p class="text-sm text-yellow-700 dark:text-yellow-300">As credenciais sao armazenadas de forma criptografada.</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label for="client_id" class="form-label">Client ID / App ID</label>
                                    <input type="text" id="client_id" name="client_id"
                                           value="{{ old('client_id') ?: ($creds['client_id'] ?? '') }}"
                                           class="form-input font-mono text-sm">
                                </div>
                                <div>
                                    <label for="client_secret" class="form-label">Client Secret</label>
                                    <input type="password" id="client_secret" name="client_secret"
                                           value="{{ old('client_secret') ?: ($creds['client_secret'] ?? '') }}"
                                           class="form-input font-mono text-sm">
                                </div>
                            </div>

                            <div>
                                <label for="access_token" class="form-label">Access Token</label>
                                <input type="password" id="access_token" name="access_token"
                                       value="{{ old('access_token') ?: ($creds['access_token'] ?? '') }}"
                                       class="form-input font-mono text-sm">
                            </div>

                            <div>
                                <label for="refresh_token" class="form-label">Refresh Token</label>
                                <input type="password" id="refresh_token" name="refresh_token"
                                       value="{{ old('refresh_token') ?: ($creds['refresh_token'] ?? '') }}"
                                       class="form-input font-mono text-sm">
                            </div>

                            <div>
                                <label for="api_url" class="form-label">URL da API (Opcional)</label>
                                <input type="url" id="api_url" name="api_url"
                                       value="{{ old('api_url') ?: ($creds['api_url'] ?? '') }}"
                                       class="form-input text-sm">
                                @error('api_url') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                        @endif
                    </div>
                </x-ui.card>

                {{-- Sync settings --}}
                @php $settings = $marketplace->settings ?? []; @endphp
                <x-ui.card title="Configuracoes de Sincronizacao">
                    <div class="space-y-4">
                        @foreach([
                            ['auto_sync_products', 'Sincronizar Produtos', 'Manter catalogo sincronizado automaticamente'],
                            ['auto_sync_orders',   'Sincronizar Pedidos',  'Importar pedidos do marketplace automaticamente'],
                            ['auto_update_stock',  'Atualizar Estoque',    'Refletir alteracoes de estoque no marketplace'],
                        ] as [$key, $label, $desc])
                        <div class="flex items-center justify-between py-2">
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $label }}</p>
                                <p class="text-xs text-gray-500 dark:text-zinc-400">{{ $desc }}</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="hidden" name="{{ $key }}" value="0">
                                <input type="checkbox" name="{{ $key }}" value="1" class="sr-only peer"
                                       {{ old($key, $settings[$key] ?? true) ? 'checked' : '' }}>
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-zinc-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-zinc-600 peer-checked:bg-primary-600"></div>
                            </label>
                        </div>
                        @endforeach

                        <div class="pt-2 border-t border-gray-200 dark:border-zinc-700">
                            <label for="sync_interval" class="form-label">Intervalo de Sincronizacao (minutos)</label>
                            <input type="number" id="sync_interval" name="sync_interval"
                                   value="{{ old('sync_interval') ?: ($settings['sync_interval'] ?? 30) }}"
                                   class="form-input w-32" min="5" max="1440">
                            @error('sync_interval') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </x-ui.card>
            </div>

            {{-- Sidebar --}}
            <div class="space-y-6">
                {{-- Marketplace (read-only) --}}
                <x-ui.card title="Marketplace">
                    <div class="p-4 rounded-lg border border-gray-200 dark:border-zinc-700 text-center">
                        <div class="w-12 h-12 rounded-xl mx-auto flex items-center justify-center mb-2"
                             style="background-color: {{ $marketplace->marketplace_type->color() }}20; border: 2px solid {{ $marketplace->marketplace_type->color() }}">
                            <x-heroicon-o-globe-alt class="w-6 h-6" style="color: {{ $marketplace->marketplace_type->color() }}" />
                        </div>
                        <p class="font-medium text-gray-900 dark:text-white">{{ $marketplace->marketplace_type->label() }}</p>
                        <p class="text-xs text-gray-400 dark:text-zinc-500 mt-1">O tipo nao pode ser alterado</p>
                    </div>
                </x-ui.card>

                {{-- Status --}}
                <x-ui.card title="Status">
                    <div>
                        <label for="status" class="form-label">Status da Conta</label>
                        <select id="status" name="status" class="form-input">
                            @foreach($statuses as $s)
                                <option value="{{ $s->value }}" {{ old('status', $marketplace->status->value) === $s->value ? 'selected' : '' }}>
                                    {{ $s->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </x-ui.card>

                {{-- Company --}}
                <x-ui.card title="Empresa">
                    <div>
                        <label for="company_id" class="form-label">Empresa *</label>
                        <select id="company_id" name="company_id" class="form-input" required>
                            @foreach($companies as $company)
                                <option value="{{ $company->id }}" {{ old('company_id', $marketplace->company_id) == $company->id ? 'selected' : '' }}>
                                    {{ $company->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('company_id') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                </x-ui.card>

                {{-- Info --}}
                <x-ui.card title="Informacoes">
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-zinc-400">Criado em</span>
                            <span>{{ $marketplace->created_at->format('d/m/Y H:i') }}</span>
                        </div>
                        @if($marketplace->token_expires_at)
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-zinc-400">Token expira</span>
                            <span class="{{ $marketplace->isTokenExpired() ? 'text-red-500 dark:text-red-400' : '' }}">
                                {{ $marketplace->token_expires_at->format('d/m/Y H:i') }}
                            </span>
                        </div>
                        @endif
                    </div>
                </x-ui.card>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 mt-6">
            <a href="{{ route('marketplaces.index') }}" class="btn-secondary">Cancelar</a>
            <button type="submit" class="btn-primary">
                <x-heroicon-s-check class="w-4 h-4" />
                Salvar Alteracoes
            </button>
        </div>
    </form>
</x-app-layout>
