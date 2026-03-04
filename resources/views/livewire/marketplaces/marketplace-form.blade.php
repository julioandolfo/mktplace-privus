<div>
    <form wire:submit="save">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Main content (2/3) --}}
            <div class="lg:col-span-2 space-y-6">
                {{-- Account info --}}
                <x-ui.card title="Informacoes da Conta">
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="account_name" class="form-label">Nome da Conta *</label>
                                <input type="text" id="account_name" wire:model="account_name" class="form-input" placeholder="Ex: Minha Loja ML" required>
                                @error('account_name') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="shop_id" class="form-label">Shop ID</label>
                                <input type="text" id="shop_id" wire:model="shop_id" class="form-input font-mono" placeholder="ID da loja no marketplace">
                            </div>
                        </div>
                    </div>
                </x-ui.card>

                {{-- Credentials --}}
                <x-ui.card title="Credenciais de API">
                    <div class="space-y-4">

                        @if($isOAuth)
                            {{-- OAuth: client_id/secret come from SystemSetting, tokens from OAuth flow --}}
                            <div class="flex items-start gap-3 p-3 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800">
                                <x-heroicon-o-information-circle class="w-5 h-5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" />
                                <div class="text-sm text-blue-700 dark:text-blue-300">
                                    <p class="font-medium">Credenciais gerenciadas automaticamente</p>
                                    <p class="mt-0.5">Client ID e Secret sao configurados em
                                        <a href="{{ route('settings.index') }}#marketplaces" class="underline font-medium hover:text-blue-900 dark:hover:text-blue-100">Configuracoes &rsaquo; Marketplaces</a>.
                                        Os tokens OAuth sao renovados pelo fluxo de conexao.
                                    </p>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="form-label">Client ID / App ID</label>
                                    <input type="text" value="{{ $client_id ?: 'Nao configurado' }}" class="form-input font-mono text-sm bg-gray-50 dark:bg-zinc-800/50 text-gray-500 dark:text-zinc-400 cursor-not-allowed" disabled readonly>
                                </div>
                                <div>
                                    <label class="form-label">Client Secret / App Secret</label>
                                    <input type="text" value="{{ $client_secret ? '••••••••' : 'Nao configurado' }}" class="form-input font-mono text-sm bg-gray-50 dark:bg-zinc-800/50 text-gray-500 dark:text-zinc-400 cursor-not-allowed" disabled readonly>
                                </div>
                            </div>

                            <div>
                                <label class="form-label">Access Token</label>
                                <div class="flex items-center gap-2">
                                    <input type="text" value="{{ $access_token ? '••••••••••••••••' : 'Nao configurado' }}" class="form-input font-mono text-sm bg-gray-50 dark:bg-zinc-800/50 text-gray-500 dark:text-zinc-400 cursor-not-allowed flex-1" disabled readonly>
                                    @if($access_token)
                                        <span class="flex items-center gap-1 text-xs text-green-600 dark:text-green-400 whitespace-nowrap">
                                            <x-heroicon-s-check-circle class="w-4 h-4" />
                                            Configurado
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <div>
                                <label class="form-label">Refresh Token</label>
                                <div class="flex items-center gap-2">
                                    <input type="text" value="{{ $refresh_token ? '••••••••••••••••' : 'Nao configurado' }}" class="form-input font-mono text-sm bg-gray-50 dark:bg-zinc-800/50 text-gray-500 dark:text-zinc-400 cursor-not-allowed flex-1" disabled readonly>
                                    @if($refresh_token)
                                        <span class="flex items-center gap-1 text-xs text-green-600 dark:text-green-400 whitespace-nowrap">
                                            <x-heroicon-s-check-circle class="w-4 h-4" />
                                            Configurado
                                        </span>
                                    @endif
                                </div>
                            </div>

                            @if($marketplace)
                            <div class="pt-2 border-t border-gray-200 dark:border-zinc-700">
                                <a href="{{ route('marketplaces.oauth.redirect', $marketplace->marketplace_type->value) }}"
                                   class="btn-secondary btn-sm">
                                    <x-heroicon-o-arrow-path class="w-4 h-4" />
                                    Reconectar via OAuth
                                </a>
                            </div>
                            @endif

                        @else
                            {{-- Non-OAuth: all credential fields are editable --}}
                            <div class="p-3 rounded-lg bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 mb-4">
                                <div class="flex gap-2">
                                    <x-heroicon-o-shield-check class="w-5 h-5 text-yellow-600 dark:text-yellow-400 flex-shrink-0 mt-0.5" />
                                    <p class="text-sm text-yellow-700 dark:text-yellow-300">As credenciais sao armazenadas de forma criptografada. Nunca compartilhe suas chaves de API.</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label for="client_id" class="form-label">Client ID / App ID</label>
                                    <input type="text" id="client_id" wire:model="client_id" class="form-input font-mono text-sm">
                                </div>
                                <div>
                                    <label for="client_secret" class="form-label">Client Secret / App Secret</label>
                                    <input type="password" id="client_secret" wire:model="client_secret" class="form-input font-mono text-sm">
                                </div>
                            </div>

                            <div>
                                <label for="access_token" class="form-label">Access Token</label>
                                <input type="password" id="access_token" wire:model="access_token" class="form-input font-mono text-sm">
                            </div>

                            <div>
                                <label for="refresh_token" class="form-label">Refresh Token</label>
                                <input type="password" id="refresh_token" wire:model="refresh_token" class="form-input font-mono text-sm">
                            </div>

                            <div>
                                <label for="api_url" class="form-label">URL da API (Opcional)</label>
                                <input type="url" id="api_url" wire:model="api_url" class="form-input text-sm" placeholder="https://api.exemplo.com">
                                @error('api_url') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                        @endif
                    </div>
                </x-ui.card>

                {{-- Sync settings --}}
                <x-ui.card title="Configuracoes de Sincronizacao">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between py-2">
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">Sincronizar Produtos</p>
                                <p class="text-xs text-gray-500 dark:text-zinc-400">Manter catalogo sincronizado automaticamente</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" wire:model="auto_sync_products" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-zinc-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-zinc-600 peer-checked:bg-primary-600"></div>
                            </label>
                        </div>

                        <div class="flex items-center justify-between py-2">
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">Sincronizar Pedidos</p>
                                <p class="text-xs text-gray-500 dark:text-zinc-400">Importar pedidos do marketplace automaticamente</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" wire:model="auto_sync_orders" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-zinc-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-zinc-600 peer-checked:bg-primary-600"></div>
                            </label>
                        </div>

                        <div class="flex items-center justify-between py-2">
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">Atualizar Estoque</p>
                                <p class="text-xs text-gray-500 dark:text-zinc-400">Refletir alteracoes de estoque no marketplace</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" wire:model="auto_update_stock" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-zinc-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-zinc-600 peer-checked:bg-primary-600"></div>
                            </label>
                        </div>

                        <div class="pt-2 border-t border-gray-200 dark:border-zinc-700">
                            <label for="sync_interval" class="form-label">Intervalo de Sincronizacao (minutos)</label>
                            <input type="number" id="sync_interval" wire:model="sync_interval" class="form-input w-32" min="5" max="1440">
                            @error('sync_interval') <p class="form-error">{{ $message }}</p> @enderror
                            <p class="text-xs text-gray-500 dark:text-zinc-400 mt-1">Minimo 5 minutos, maximo 24 horas (1440 min)</p>
                        </div>
                    </div>
                </x-ui.card>
            </div>

            {{-- Sidebar (1/3) --}}
            <div class="space-y-6">
                {{-- Marketplace type --}}
                <x-ui.card title="Marketplace">
                    <div class="space-y-4">
                        <div>
                            <label for="marketplace_type" class="form-label">Tipo *</label>
                            <select id="marketplace_type" wire:model.live="marketplace_type" class="form-input" {{ $marketplaceId ? 'disabled' : '' }}>
                                @foreach($types as $t)
                                    <option value="{{ $t->value }}">{{ $t->label() }}</option>
                                @endforeach
                            </select>
                            @if($marketplaceId)
                                <input type="hidden" wire:model="marketplace_type">
                                <p class="text-xs text-gray-400 dark:text-zinc-500 mt-1">O tipo nao pode ser alterado apos a criacao.</p>
                            @endif
                        </div>

                        {{-- Visual indicator --}}
                        <div wire:key="type-card-{{ $marketplace_type }}" class="p-4 rounded-lg border border-gray-200 dark:border-zinc-700 text-center">
                            @php
                                $currentType = collect($types)->first(fn($t) => $t->value === $marketplace_type);
                            @endphp
                            @if($currentType)
                            <div class="w-12 h-12 rounded-xl mx-auto flex items-center justify-center mb-2" style="background-color: {{ $currentType->color() }}20; border: 2px solid {{ $currentType->color() }}">
                                <x-heroicon-o-globe-alt class="w-6 h-6" style="color: {{ $currentType->color() }}" />
                            </div>
                            <p class="font-medium text-gray-900 dark:text-white">{{ $currentType->label() }}</p>
                            @endif
                        </div>
                    </div>
                </x-ui.card>

                {{-- Status --}}
                <x-ui.card title="Status">
                    <div>
                        <label for="status" class="form-label">Status da Conta</label>
                        <select id="status" wire:model="status" class="form-input">
                            @foreach($statuses as $s)
                                <option value="{{ $s->value }}">{{ $s->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                </x-ui.card>

                {{-- Company --}}
                <x-ui.card title="Empresa">
                    <div>
                        <label for="company_id" class="form-label">Empresa *</label>
                        <select id="company_id" wire:model="company_id" class="form-input" required>
                            <option value="">Selecione</option>
                            @foreach($companies as $company)
                                <option value="{{ $company->id }}">{{ $company->name }}</option>
                            @endforeach
                        </select>
                        @error('company_id') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                </x-ui.card>

                {{-- Info (edit only) --}}
                @if($marketplace)
                <x-ui.card title="Informacoes">
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-zinc-400">Criado em</span>
                            <span>{{ $marketplace->created_at->format('d/m/Y H:i') }}</span>
                        </div>
                        @if($marketplace->last_synced_at)
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-zinc-400">Ultima Sync</span>
                            <span>{{ $marketplace->last_synced_at->format('d/m/Y H:i') }}</span>
                        </div>
                        @endif
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
                @endif
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex items-center justify-end gap-3 mt-6">
            <a href="{{ route('marketplaces.index') }}" class="btn-secondary">Cancelar</a>
            <button type="submit" class="btn-primary" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="save">
                    <x-heroicon-s-check class="w-4 h-4" />
                    {{ $marketplace ? 'Salvar Alteracoes' : 'Criar Conta' }}
                </span>
                <span wire:loading wire:target="save">Salvando...</span>
            </button>
        </div>
    </form>
</div>
