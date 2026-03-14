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

                <div class="h-px bg-gray-100 dark:bg-zinc-700 mx-1 my-1"></div>

                {{-- Links externos (páginas separadas) --}}
                <a href="{{ route('settings.users.index') }}"
                   class="{{ $navBtn }} {{ request()->routeIs('settings.users.*') ? $navActive : $navInactive }}">
                    <x-heroicon-o-users class="w-5 h-5" />
                    Usuarios
                </a>
                <a href="{{ route('settings.designers.index') }}"
                   class="{{ $navBtn }} {{ request()->routeIs('settings.designers.*') ? $navActive : $navInactive }}">
                    <x-heroicon-o-paint-brush class="w-5 h-5" />
                    Designers
                </a>
                <a href="{{ route('settings.me.index') }}"
                   class="{{ $navBtn }} {{ request()->routeIs('settings.me.*') ? $navActive : $navInactive }}">
                    <x-heroicon-o-truck class="w-5 h-5" />
                    Melhor Envios
                </a>
                <a href="{{ route('settings.webmania.index') }}"
                   class="{{ $navBtn }} {{ request()->routeIs('settings.webmania.*') ? $navActive : $navInactive }}">
                    <x-heroicon-o-document-text class="w-5 h-5" />
                    Webmaniabr (NF-e)
                </a>
                <a href="{{ route('settings.operators.index') }}"
                   class="{{ $navBtn }} {{ request()->routeIs('settings.operators.*') ? $navActive : $navInactive }}">
                    <x-heroicon-o-user-group class="w-5 h-5" />
                    Operadores Expedição
                </a>
                <a href="{{ route('settings.bonus.index') }}"
                   class="{{ $navBtn }} {{ request()->routeIs('settings.bonus.*') ? $navActive : $navInactive }}">
                    <x-heroicon-o-trophy class="w-5 h-5" />
                    Bonificação Expedição
                </a>
            </nav>
        </div>

        {{-- Settings content --}}
        <div class="flex-1 min-w-0">
            {{-- General settings --}}
            <div x-show="tab === 'general'" x-transition>
                {{-- Logomarca --}}
                <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data" class="mb-6">
                    @csrf
                    <input type="hidden" name="section" value="logo">
                    <x-ui.card title="Logomarca">
                        <div class="flex flex-col sm:flex-row items-start gap-6 max-w-lg">
                            {{-- Preview --}}
                            <div class="flex-shrink-0">
                                @php $currentLogo = \App\Models\SystemSetting::get('general', 'logo_url'); @endphp
                                <div class="w-28 h-28 rounded-xl border-2 border-dashed border-gray-300 dark:border-zinc-600 flex items-center justify-center bg-gray-50 dark:bg-zinc-800 overflow-hidden">
                                    @if($currentLogo)
                                        <img src="{{ $currentLogo }}" alt="Logo" class="max-w-full max-h-full object-contain">
                                    @else
                                        <div class="text-center">
                                            <x-heroicon-o-photo class="w-8 h-8 text-gray-300 dark:text-zinc-600 mx-auto" />
                                            <p class="text-xs text-gray-400 dark:text-zinc-500 mt-1">Sem logo</p>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            {{-- Upload --}}
                            <div class="flex-1 space-y-3">
                                <div>
                                    <label class="form-label">Enviar Logomarca</label>
                                    <input type="file" name="logo" accept="image/png,image/jpeg,image/svg+xml,image/webp"
                                           class="form-input text-sm file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-primary-50 file:text-primary-700 dark:file:bg-primary-900/20 dark:file:text-primary-400 hover:file:bg-primary-100">
                                    <p class="text-xs text-gray-400 dark:text-zinc-500 mt-1">PNG, JPG, SVG ou WebP. Recomendado: 200x200px ou maior.</p>
                                </div>

                                @if($currentLogo)
                                <div class="flex items-center gap-2">
                                    <button type="submit" class="btn-primary btn-sm">
                                        <x-heroicon-o-arrow-up-tray class="w-4 h-4" />
                                        Atualizar Logo
                                    </button>
                                    <a href="{{ route('settings.logo.remove') }}"
                                       onclick="event.preventDefault(); document.getElementById('remove-logo-form').submit();"
                                       class="btn-secondary btn-sm text-red-600 dark:text-red-400">
                                        <x-heroicon-o-trash class="w-4 h-4" />
                                        Remover
                                    </a>
                                </div>
                                @else
                                <button type="submit" class="btn-primary btn-sm">
                                    <x-heroicon-o-arrow-up-tray class="w-4 h-4" />
                                    Enviar Logo
                                </button>
                                @endif
                            </div>
                        </div>
                    </x-ui.card>
                </form>

                @if($currentLogo ?? false)
                <form id="remove-logo-form" method="POST" action="{{ route('settings.logo.remove') }}" class="hidden">
                    @csrf @method('DELETE')
                </form>
                @endif

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

                {{-- URLs de Integração --}}
                <x-ui.card class="mb-6">
                    <x-slot name="title">
                        <div class="flex items-center gap-2">
                            <x-heroicon-o-link class="w-5 h-5 text-primary-500" />
                            URLs de Integracao
                        </div>
                    </x-slot>
                    <p class="text-sm text-gray-500 dark:text-zinc-400 mb-4">
                        Configure estas URLs no painel de desenvolvedor de cada marketplace ao registrar seu app.
                    </p>

                    @php
                        $integrationUrls = [
                            [
                                'label'       => 'Mercado Livre — OAuth Redirect URI',
                                'description' => 'Configurar em: developers.mercadolivre.com.br → seu app → "URI de redirecionamento"',
                                'url'         => route('marketplaces.oauth.callback', 'mercado_livre'),
                                'color'       => '#FFE600',
                            ],
                            [
                                'label'       => 'Mercado Livre — Webhook / Notificações',
                                'description' => 'Configurar em: developers.mercadolivre.com.br → seu app → "URL de notificações"',
                                'url'         => route('webhooks.marketplace', 'mercado_livre'),
                                'color'       => '#FFE600',
                            ],
                            [
                                'label'       => 'Amazon — OAuth Redirect URI',
                                'description' => 'Configurar em: Seller Central → Apps & Services → Develop Apps → seu app → OAuth Login URI',
                                'url'         => route('marketplaces.oauth.callback', 'amazon'),
                                'color'       => '#FF9900',
                            ],
                            [
                                'label'       => 'Amazon — Webhook',
                                'description' => 'Configurar em: Seller Central → Notifications → Destination',
                                'url'         => route('webhooks.marketplace', 'amazon'),
                                'color'       => '#FF9900',
                            ],
                            [
                                'label'       => 'Shopee — Webhook / Push URL',
                                'description' => 'Configurar em: Open Platform → App Config → Push URL',
                                'url'         => route('webhooks.marketplace', 'shopee'),
                                'color'       => '#EE4D2D',
                            ],
                            [
                                'label'       => 'TikTok Shop — Webhook',
                                'description' => 'Configurar em: Partner Center → App → Webhook URL',
                                'url'         => route('webhooks.marketplace', 'tiktok'),
                                'color'       => '#000000',
                            ],
                            [
                                'label'       => 'WooCommerce — Webhook',
                                'description' => 'Configurar em: painel WooCommerce do cliente → WooCommerce → Configurações → Avancado → Webhooks',
                                'url'         => route('webhooks.marketplace', 'woocommerce'),
                                'color'       => '#96588A',
                            ],
                        ];
                    @endphp

                    <div class="space-y-2" x-data="{ copied: null }">
                        @foreach($integrationUrls as $i => $item)
                        <div class="flex items-start gap-3 p-3 rounded-lg bg-gray-50 dark:bg-zinc-800/50 border border-gray-200 dark:border-zinc-700">
                            <span class="w-2.5 h-2.5 rounded-full flex-shrink-0 mt-1.5" style="background-color: {{ $item['color'] }}"></span>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-medium text-gray-700 dark:text-zinc-300">{{ $item['label'] }}</p>
                                <p class="text-xs text-gray-400 dark:text-zinc-500 mt-0.5">{{ $item['description'] }}</p>
                                <div class="flex items-center gap-2 mt-1.5">
                                    <code class="text-xs font-mono text-primary-700 dark:text-primary-400 truncate flex-1">{{ $item['url'] }}</code>
                                    <button type="button"
                                        @click="navigator.clipboard.writeText('{{ $item['url'] }}'); copied = {{ $i }}; setTimeout(() => copied = null, 2000)"
                                        class="flex-shrink-0 flex items-center gap-1 text-xs px-2 py-1 rounded border border-gray-300 dark:border-zinc-600 hover:bg-gray-100 dark:hover:bg-zinc-700 transition-colors">
                                        <template x-if="copied !== {{ $i }}">
                                            <span class="flex items-center gap-1">
                                                <x-heroicon-o-clipboard class="w-3.5 h-3.5" />
                                                Copiar
                                            </span>
                                        </template>
                                        <template x-if="copied === {{ $i }}">
                                            <span class="flex items-center gap-1 text-green-600 dark:text-green-400">
                                                <x-heroicon-s-check class="w-3.5 h-3.5" />
                                                Copiado
                                            </span>
                                        </template>
                                    </button>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </x-ui.card>

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
            <div x-show="tab === 'ai'" x-transition
                 x-data="{
                     aiModels: [],
                     aiModelsLoading: false,
                     aiModelSearch: '',
                     aiTestLoading: false,
                     aiTestResult: null,
                     aiTestSuccess: false,
                     currentModel: '{{ $aiProviders->firstWhere('is_active', true)?->default_model ?? '' }}',
                     async loadModels() {
                         this.aiModelsLoading = true;
                         try {
                             const res = await fetch('{{ route('settings.ai.models') }}');
                             this.aiModels = await res.json();
                         } catch (e) {
                             this.aiModels = [];
                         }
                         this.aiModelsLoading = false;
                     },
                     get filteredModels() {
                         if (!this.aiModelSearch) return this.aiModels.slice(0, 50);
                         const s = this.aiModelSearch.toLowerCase();
                         return this.aiModels.filter(m => m.name.toLowerCase().includes(s) || m.id.toLowerCase().includes(s)).slice(0, 50);
                     },
                     async testConnection() {
                         this.aiTestLoading = true;
                         this.aiTestResult = null;
                         try {
                             const res = await fetch('{{ route('settings.ai.test') }}', {
                                 method: 'POST',
                                 headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                             });
                             const data = await res.json();
                             this.aiTestSuccess = data.success;
                             this.aiTestResult = data.message;
                         } catch (e) {
                             this.aiTestSuccess = false;
                             this.aiTestResult = 'Erro de rede: ' + e.message;
                         }
                         this.aiTestLoading = false;
                     }
                 }"
                 x-init="loadModels()">
                <form method="POST" action="{{ route('settings.update') }}">
                    @csrf
                    <input type="hidden" name="section" value="ai">
                    <x-ui.card title="Configuracoes de IA">
                        <div class="space-y-4 max-w-lg">
                            <div>
                                <label for="ai_provider" class="form-label">Provedor de IA</label>
                                <select id="ai_provider" name="provider" class="form-input"
                                        @change="$nextTick(() => loadModels())">
                                    <option value="openrouter" {{ ($aiProviders->firstWhere('is_active', true)?->provider ?? 'openrouter') === 'openrouter' ? 'selected' : '' }}>OpenRouter (Recomendado)</option>
                                    <option value="openai" {{ ($aiProviders->firstWhere('is_active', true)?->provider) === 'openai' ? 'selected' : '' }}>OpenAI</option>
                                    <option value="anthropic" {{ ($aiProviders->firstWhere('is_active', true)?->provider) === 'anthropic' ? 'selected' : '' }}>Anthropic</option>
                                </select>
                                <p class="mt-1 text-xs text-gray-500 dark:text-zinc-400">
                                    <span x-show="document.getElementById('ai_provider')?.value === 'openrouter' || !document.getElementById('ai_provider')">OpenRouter permite acesso a Claude, GPT e outros modelos com uma unica API key.</span>
                                </p>
                            </div>

                            <div>
                                <label for="ai_api_key" class="form-label">API Key</label>
                                <input type="password" id="ai_api_key" name="api_key" value="{{ $aiProviders->firstWhere('is_active', true)?->api_key ? '••••••••' : '' }}" class="form-input"
                                       :placeholder="document.getElementById('ai_provider')?.value === 'openai' ? 'sk-...' : (document.getElementById('ai_provider')?.value === 'anthropic' ? 'sk-ant-...' : 'sk-or-...')">
                            </div>

                            <div>
                                <label for="ai_model" class="form-label">Modelo Padrao</label>
                                <div class="relative" x-data="{ open: false }">
                                    <input type="text"
                                           name="default_model"
                                           x-model="currentModel"
                                           @focus="open = true"
                                           @click="open = true"
                                           @input="aiModelSearch = $event.target.value; open = true"
                                           @click.away="open = false"
                                           class="form-input font-mono text-sm"
                                           placeholder="Buscar modelo..."
                                           autocomplete="off">

                                    {{-- Dropdown de modelos --}}
                                    <div x-show="open && aiModels.length > 0"
                                         x-transition
                                         class="absolute z-50 w-full mt-1 max-h-64 overflow-y-auto rounded-lg border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-lg">

                                        <template x-if="aiModelsLoading">
                                            <div class="p-3 text-center text-sm text-gray-500 dark:text-zinc-400">
                                                Carregando modelos...
                                            </div>
                                        </template>

                                        <template x-for="model in filteredModels" :key="model.id">
                                            <button type="button"
                                                    @click="currentModel = model.id; aiModelSearch = ''; open = false"
                                                    class="w-full text-left px-3 py-2 hover:bg-gray-100 dark:hover:bg-zinc-700 transition-colors border-b border-gray-100 dark:border-zinc-700/50 last:border-0">
                                                <div class="flex items-center justify-between gap-2">
                                                    <span class="text-sm text-gray-900 dark:text-white truncate" x-text="model.name"></span>
                                                    <span x-show="model.free"
                                                          class="shrink-0 text-[10px] font-semibold px-1.5 py-0.5 rounded bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400">
                                                        GRATIS
                                                    </span>
                                                    <span x-show="!model.free"
                                                          class="shrink-0 text-[10px] font-semibold px-1.5 py-0.5 rounded bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400">
                                                        PAGO
                                                    </span>
                                                </div>
                                                <p class="text-[10px] font-mono text-gray-400 dark:text-zinc-500 truncate" x-text="model.id"></p>
                                            </button>
                                        </template>

                                        <template x-if="!aiModelsLoading && filteredModels.length === 0">
                                            <div class="p-3 text-center text-sm text-gray-500 dark:text-zinc-400">
                                                Nenhum modelo encontrado.
                                            </div>
                                        </template>
                                    </div>
                                </div>
                                <p class="mt-1 text-xs text-gray-500 dark:text-zinc-400">
                                    <template x-if="aiModels.length > 0">
                                        <span x-text="aiModels.length + ' modelos disponiveis'"></span>
                                    </template>
                                    <template x-if="aiModelsLoading">
                                        <span>Carregando lista de modelos...</span>
                                    </template>
                                </p>
                            </div>

                            <div>
                                <label for="ai_budget" class="form-label">Limite Mensal (USD)</label>
                                <input type="number" step="0.01" id="ai_budget" name="monthly_budget_limit" value="{{ $aiProviders->firstWhere('is_active', true)?->monthly_budget_limit ?? '' }}" class="form-input" placeholder="50.00">
                                <p class="mt-1 text-xs text-gray-500 dark:text-zinc-400">O sistema pausara chamadas de IA ao atingir este limite.</p>
                            </div>

                            {{-- Testar Conexao --}}
                            <div class="pt-2 border-t border-gray-200 dark:border-zinc-700">
                                <button type="button"
                                        @click="testConnection()"
                                        :disabled="aiTestLoading"
                                        class="btn-secondary w-full justify-center">
                                    <template x-if="!aiTestLoading">
                                        <span class="flex items-center gap-2">
                                            <x-heroicon-o-signal class="w-4 h-4" />
                                            Testar Conexao
                                        </span>
                                    </template>
                                    <template x-if="aiTestLoading">
                                        <span class="flex items-center gap-2">
                                            <svg class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none">
                                                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25"/>
                                                <path d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" fill="currentColor" class="opacity-75"/>
                                            </svg>
                                            Testando...
                                        </span>
                                    </template>
                                </button>

                                <div x-show="aiTestResult" x-transition class="mt-2 p-3 rounded-lg text-sm"
                                     :class="aiTestSuccess ? 'bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-300' : 'bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300'">
                                    <span x-text="aiTestResult"></span>
                                </div>
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
