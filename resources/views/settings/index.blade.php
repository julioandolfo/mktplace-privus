<x-app-layout>
    <x-slot name="header">Configuracoes</x-slot>
    <x-slot name="subtitle">Configuracoes gerais do sistema</x-slot>
    <x-slot name="breadcrumbs">
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <span class="text-gray-700 dark:text-zinc-200">Configuracoes</span>
        </li>
    </x-slot>

    <div x-data="{ tab: '{{ request('tab', 'general') }}' }" class="flex flex-col lg:flex-row gap-6">
        {{-- Settings sidebar --}}
        <div class="lg:w-64 flex-shrink-0">
            <nav class="card p-2 space-y-1">
                @php
                    $navBtn = "w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors";
                    $navActive = "bg-primary-50 dark:bg-primary-500/10 text-primary-700 dark:text-primary-400";
                    $navInactive = "text-gray-600 dark:text-zinc-400 hover:bg-gray-50 dark:hover:bg-zinc-700";
                @endphp
                <button @click="tab = 'general'" :class="tab === 'general' ? '{{ $navActive }}' : '{{ $navInactive }}'" class="{{ $navBtn }}">
                    <x-heroicon-o-cog-6-tooth class="w-5 h-5" />
                    Geral
                </button>
                <button @click="tab = 'marketplaces'" :class="tab === 'marketplaces' ? '{{ $navActive }}' : '{{ $navInactive }}'" class="{{ $navBtn }}">
                    <x-heroicon-o-globe-alt class="w-5 h-5" />
                    Marketplaces
                </button>
                <button @click="tab = 'ai'" :class="tab === 'ai' ? '{{ $navActive }}' : '{{ $navInactive }}'" class="{{ $navBtn }}">
                    <x-heroicon-o-sparkles class="w-5 h-5" />
                    Inteligencia Artificial
                </button>
                <button @click="tab = 'sync'" :class="tab === 'sync' ? '{{ $navActive }}' : '{{ $navInactive }}'" class="{{ $navBtn }}">
                    <x-heroicon-o-arrow-path class="w-5 h-5" />
                    Sincronizacao
                </button>
                <button @click="tab = 'notifications'" :class="tab === 'notifications' ? '{{ $navActive }}' : '{{ $navInactive }}'" class="{{ $navBtn }}">
                    <x-heroicon-o-bell class="w-5 h-5" />
                    Notificacoes
                </button>
            </nav>
        </div>

        {{-- Settings content --}}
        <div class="flex-1 min-w-0">
            {{-- General settings --}}
            <div x-show="tab === 'general'" x-transition>
                <form method="POST" action="{{ route('settings.update') }}">
                    @csrf
                    <input type="hidden" name="section" value="general">
                    <x-ui.card title="Configuracoes Gerais">
                        <div class="space-y-4 max-w-lg">
                            <div>
                                <label for="system_name" class="form-label">Nome do Sistema</label>
                                <input type="text" id="system_name" name="system_name" value="{{ $generalSettings['system_name'] ?? 'MktPlace Privus' }}" class="form-input">
                            </div>

                            <div>
                                <label for="currency" class="form-label">Moeda</label>
                                <select id="currency" name="currency" class="form-input">
                                    <option value="BRL" {{ ($generalSettings['currency'] ?? 'BRL') === 'BRL' ? 'selected' : '' }}>Real (BRL)</option>
                                    <option value="USD" {{ ($generalSettings['currency'] ?? '') === 'USD' ? 'selected' : '' }}>Dollar (USD)</option>
                                </select>
                            </div>

                            <div>
                                <label for="timezone" class="form-label">Fuso Horario</label>
                                <select id="timezone" name="timezone" class="form-input">
                                    <option value="America/Sao_Paulo" {{ ($generalSettings['timezone'] ?? 'America/Sao_Paulo') === 'America/Sao_Paulo' ? 'selected' : '' }}>Brasilia (GMT-3)</option>
                                    <option value="America/Manaus" {{ ($generalSettings['timezone'] ?? '') === 'America/Manaus' ? 'selected' : '' }}>Manaus (GMT-4)</option>
                                    <option value="America/Belem" {{ ($generalSettings['timezone'] ?? '') === 'America/Belem' ? 'selected' : '' }}>Belem (GMT-3)</option>
                                </select>
                            </div>

                            <div>
                                <label for="date_format" class="form-label">Formato de Data</label>
                                <select id="date_format" name="date_format" class="form-input">
                                    <option value="d/m/Y" {{ ($generalSettings['date_format'] ?? 'd/m/Y') === 'd/m/Y' ? 'selected' : '' }}>DD/MM/AAAA</option>
                                    <option value="Y-m-d" {{ ($generalSettings['date_format'] ?? '') === 'Y-m-d' ? 'selected' : '' }}>AAAA-MM-DD</option>
                                </select>
                            </div>
                        </div>

                        <x-slot name="footer">
                            <div class="flex justify-end">
                                <button type="submit" class="btn-primary">
                                    <x-heroicon-s-check class="w-4 h-4" />
                                    Salvar
                                </button>
                            </div>
                        </x-slot>
                    </x-ui.card>
                </form>
            </div>

            {{-- Marketplace settings --}}
            <div x-show="tab === 'marketplaces'" x-transition>
                <form method="POST" action="{{ route('settings.update') }}">
                    @csrf
                    <input type="hidden" name="section" value="marketplaces">

                    <div class="space-y-6">
                        {{-- Mercado Livre --}}
                        <x-ui.card>
                            <x-slot name="title">
                                <div class="flex items-center gap-2">
                                    <span class="w-3 h-3 rounded-full" style="background-color:#FFE600;"></span>
                                    Mercado Livre
                                    <span class="text-xs font-normal text-gray-500 dark:text-zinc-400">(OAuth)</span>
                                </div>
                            </x-slot>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 max-w-2xl">
                                <div>
                                    <label class="form-label">Client ID</label>
                                    <input type="text" name="mercado_livre_client_id"
                                           value="{{ $marketplaceSettings['mercado_livre_client_id'] ?? '' }}"
                                           class="form-input font-mono" placeholder="APP_ID">
                                    <p class="mt-1 text-xs text-gray-400 dark:text-zinc-500">Disponivel em developers.mercadolivre.com.br</p>
                                </div>
                                <div>
                                    <label class="form-label">Client Secret</label>
                                    <input type="password" name="mercado_livre_client_secret"
                                           value="{{ !empty($marketplaceSettings['mercado_livre_client_secret']) ? '••••••••' : '' }}"
                                           class="form-input font-mono" placeholder="Secret Key"
                                           autocomplete="new-password">
                                </div>
                            </div>
                        </x-ui.card>

                        {{-- Amazon --}}
                        <x-ui.card>
                            <x-slot name="title">
                                <div class="flex items-center gap-2">
                                    <span class="w-3 h-3 rounded-full" style="background-color:#FF9900;"></span>
                                    Amazon
                                    <span class="text-xs font-normal text-gray-500 dark:text-zinc-400">(OAuth — Selling Partner API)</span>
                                </div>
                            </x-slot>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 max-w-2xl">
                                <div>
                                    <label class="form-label">Client ID</label>
                                    <input type="text" name="amazon_client_id"
                                           value="{{ $marketplaceSettings['amazon_client_id'] ?? '' }}"
                                           class="form-input font-mono" placeholder="amzn1.application-oa2-client...">
                                    <p class="mt-1 text-xs text-gray-400 dark:text-zinc-500">Gerado no Seller Central > Apps & Services</p>
                                </div>
                                <div>
                                    <label class="form-label">Client Secret</label>
                                    <input type="password" name="amazon_client_secret"
                                           value="{{ !empty($marketplaceSettings['amazon_client_secret']) ? '••••••••' : '' }}"
                                           class="form-input font-mono" placeholder="Secret"
                                           autocomplete="new-password">
                                </div>
                            </div>
                        </x-ui.card>

                        {{-- Shopee --}}
                        <x-ui.card>
                            <x-slot name="title">
                                <div class="flex items-center gap-2">
                                    <span class="w-3 h-3 rounded-full" style="background-color:#EE4D2D;"></span>
                                    Shopee
                                    <span class="text-xs font-normal text-gray-500 dark:text-zinc-400">(Autenticacao HMAC)</span>
                                </div>
                            </x-slot>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 max-w-2xl">
                                <div>
                                    <label class="form-label">Partner ID</label>
                                    <input type="text" name="shopee_partner_id"
                                           value="{{ $marketplaceSettings['shopee_partner_id'] ?? '' }}"
                                           class="form-input font-mono" placeholder="123456">
                                    <p class="mt-1 text-xs text-gray-400 dark:text-zinc-500">Disponivel no Shopee Open Platform</p>
                                </div>
                                <div>
                                    <label class="form-label">Partner Key</label>
                                    <input type="password" name="shopee_partner_key"
                                           value="{{ !empty($marketplaceSettings['shopee_partner_key']) ? '••••••••' : '' }}"
                                           class="form-input font-mono" placeholder="Partner Key"
                                           autocomplete="new-password">
                                </div>
                            </div>
                        </x-ui.card>

                        {{-- TikTok Shop --}}
                        <x-ui.card>
                            <x-slot name="title">
                                <div class="flex items-center gap-2">
                                    <span class="w-3 h-3 rounded-full bg-black dark:bg-white"></span>
                                    TikTok Shop
                                    <span class="text-xs font-normal text-gray-500 dark:text-zinc-400">(Requer aprovacao no Partner Center)</span>
                                </div>
                            </x-slot>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 max-w-2xl">
                                <div>
                                    <label class="form-label">App ID</label>
                                    <input type="text" name="tiktok_app_id"
                                           value="{{ $marketplaceSettings['tiktok_app_id'] ?? '' }}"
                                           class="form-input font-mono" placeholder="App ID">
                                    <p class="mt-1 text-xs text-gray-400 dark:text-zinc-500">Disponivel no TikTok Shop Partner Center</p>
                                </div>
                                <div>
                                    <label class="form-label">App Secret</label>
                                    <input type="password" name="tiktok_app_secret"
                                           value="{{ !empty($marketplaceSettings['tiktok_app_secret']) ? '••••••••' : '' }}"
                                           class="form-input font-mono" placeholder="App Secret"
                                           autocomplete="new-password">
                                </div>
                            </div>
                        </x-ui.card>

                        {{-- WooCommerce info --}}
                        <x-ui.card>
                            <x-slot name="title">
                                <div class="flex items-center gap-2">
                                    <span class="w-3 h-3 rounded-full" style="background-color:#96588A;"></span>
                                    WooCommerce
                                </div>
                            </x-slot>
                            <div class="flex items-start gap-3 text-sm text-gray-600 dark:text-zinc-400 max-w-2xl">
                                <x-heroicon-o-information-circle class="w-5 h-5 flex-shrink-0 text-blue-500 mt-0.5" />
                                <p>O WooCommerce usa chaves de API REST geradas individualmente em cada loja do cliente. Essas credenciais sao configuradas diretamente no cadastro de cada conta de marketplace, nao aqui.</p>
                            </div>
                        </x-ui.card>
                    </div>

                    <div class="flex justify-end mt-6">
                        <button type="submit" class="btn-primary">
                            <x-heroicon-s-check class="w-4 h-4" />
                            Salvar Credenciais
                        </button>
                    </div>
                </form>
            </div>

            {{-- AI settings --}}
            <div x-show="tab === 'ai'" x-transition>
                <form method="POST" action="{{ route('settings.update') }}">
                    @csrf
                    <input type="hidden" name="section" value="ai">
                    <x-ui.card title="Configuracoes de IA">
                        <div class="space-y-4 max-w-lg">
                            <div>
                                <label for="ai_provider" class="form-label">Provedor de IA</label>
                                <select id="ai_provider" name="provider" class="form-input">
                                    <option value="openrouter">OpenRouter (Recomendado)</option>
                                    <option value="openai">OpenAI</option>
                                    <option value="anthropic">Anthropic</option>
                                </select>
                                <p class="mt-1 text-xs text-gray-500 dark:text-zinc-400">OpenRouter permite acesso a Claude, GPT e outros modelos com uma unica API key.</p>
                            </div>

                            <div>
                                <label for="ai_api_key" class="form-label">API Key</label>
                                <input type="password" id="ai_api_key" name="api_key" value="{{ $aiProviders->firstWhere('is_active', true)?->api_key ? '••••••••' : '' }}" class="form-input" placeholder="sk-or-...">
                            </div>

                            <div>
                                <label for="ai_model" class="form-label">Modelo Padrao</label>
                                <input type="text" id="ai_model" name="default_model" value="{{ $aiProviders->firstWhere('is_active', true)?->default_model ?? 'anthropic/claude-sonnet-4-20250514' }}" class="form-input" placeholder="anthropic/claude-sonnet-4-20250514">
                            </div>

                            <div>
                                <label for="ai_budget" class="form-label">Limite Mensal (USD)</label>
                                <input type="number" step="0.01" id="ai_budget" name="monthly_budget_limit" value="{{ $aiProviders->firstWhere('is_active', true)?->monthly_budget_limit ?? '' }}" class="form-input" placeholder="50.00">
                                <p class="mt-1 text-xs text-gray-500 dark:text-zinc-400">O sistema pausara chamadas de IA ao atingir este limite.</p>
                            </div>
                        </div>

                        <x-slot name="footer">
                            <div class="flex justify-end">
                                <button type="submit" class="btn-primary">
                                    <x-heroicon-s-check class="w-4 h-4" />
                                    Salvar
                                </button>
                            </div>
                        </x-slot>
                    </x-ui.card>
                </form>
            </div>

            {{-- Sync settings --}}
            <div x-show="tab === 'sync'" x-transition>
                <x-ui.card title="Configuracoes de Sincronizacao">
                    <x-ui.empty-state
                        title="Em breve"
                        description="As configuracoes de intervalo de sincronizacao por marketplace estarao disponiveis quando as integracoes forem implementadas.">
                        <x-slot name="icon">
                            <x-heroicon-o-arrow-path class="w-8 h-8 text-gray-400 dark:text-zinc-500" />
                        </x-slot>
                    </x-ui.empty-state>
                </x-ui.card>
            </div>

            {{-- Notification settings --}}
            <div x-show="tab === 'notifications'" x-transition>
                <x-ui.card title="Configuracoes de Notificacoes">
                    <x-ui.empty-state
                        title="Em breve"
                        description="As configuracoes de notificacoes estarao disponiveis em breve.">
                        <x-slot name="icon">
                            <x-heroicon-o-bell class="w-8 h-8 text-gray-400 dark:text-zinc-500" />
                        </x-slot>
                    </x-ui.empty-state>
                </x-ui.card>
            </div>
        </div>
    </div>
</x-app-layout>
