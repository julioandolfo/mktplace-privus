<x-app-layout>
    <x-slot name="header">{{ $marketplace->account_name }}</x-slot>
    <x-slot name="actions">
        <form method="POST" action="{{ route('marketplaces.diagnose', $marketplace) }}">
            @csrf
            <button type="submit" class="btn-secondary">
                <x-heroicon-o-beaker class="w-4 h-4" />
                Testar Conexão
            </button>
        </form>
        <a href="{{ route('marketplaces.edit', $marketplace) }}" class="btn-secondary">
            <x-heroicon-o-pencil-square class="w-4 h-4" />
            Editar
        </a>
    </x-slot>
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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Main content --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Account info --}}
            <x-ui.card title="Informacoes da Conta">
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="text-gray-500 dark:text-zinc-400">Nome da Conta</span>
                        <p class="font-medium mt-1">{{ $marketplace->account_name }}</p>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-zinc-400">Marketplace</span>
                        <div class="mt-1 flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full" style="background-color: {{ $marketplace->marketplace_type->color() }}"></span>
                            <span class="font-medium">{{ $marketplace->marketplace_type->label() }}</span>
                        </div>
                    </div>
                    @if($marketplace->shop_id)
                    <div>
                        <span class="text-gray-500 dark:text-zinc-400">Shop ID</span>
                        <p class="font-mono mt-1">{{ $marketplace->shop_id }}</p>
                    </div>
                    @endif
                    @if($marketplace->company)
                    <div>
                        <span class="text-gray-500 dark:text-zinc-400">Empresa</span>
                        <p class="font-medium mt-1">{{ $marketplace->company->name }}</p>
                    </div>
                    @endif
                </div>
            </x-ui.card>

            {{-- Credentials status --}}
            <x-ui.card title="Credenciais">
                <div class="space-y-3 text-sm">
                    @php $creds = $marketplace->credentials ?? []; @endphp
                    <div class="flex items-center justify-between">
                        <span class="text-gray-600 dark:text-zinc-400">Client ID</span>
                        @if(!empty($creds['client_id']) || $sysClientId)
                            <span class="flex items-center gap-1 text-green-600 dark:text-green-400">
                                <x-heroicon-s-check-circle class="w-4 h-4" />
                                Configurado
                            </span>
                        @else
                            <span class="flex items-center gap-1 text-gray-400 dark:text-zinc-500">
                                <x-heroicon-o-x-circle class="w-4 h-4" />
                                Nao configurado
                            </span>
                        @endif
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-600 dark:text-zinc-400">Client Secret</span>
                        @if(!empty($creds['client_secret']) || $sysClientSecret)
                            <span class="flex items-center gap-1 text-green-600 dark:text-green-400">
                                <x-heroicon-s-check-circle class="w-4 h-4" />
                                Configurado
                            </span>
                        @else
                            <span class="flex items-center gap-1 text-gray-400 dark:text-zinc-500">
                                <x-heroicon-o-x-circle class="w-4 h-4" />
                                Nao configurado
                            </span>
                        @endif
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-600 dark:text-zinc-400">Access Token</span>
                        @if(!empty($creds['access_token']))
                            <span class="flex items-center gap-1 text-green-600 dark:text-green-400">
                                <x-heroicon-s-check-circle class="w-4 h-4" />
                                Configurado
                            </span>
                        @else
                            <span class="flex items-center gap-1 text-gray-400 dark:text-zinc-500">
                                <x-heroicon-o-x-circle class="w-4 h-4" />
                                Nao configurado
                            </span>
                        @endif
                    </div>
                    @if($marketplace->token_expires_at)
                    <div class="flex items-center justify-between pt-2 border-t border-gray-200 dark:border-zinc-700">
                        <span class="text-gray-600 dark:text-zinc-400">Token Expira em</span>
                        <span class="{{ $marketplace->isTokenExpired() ? 'text-red-600 dark:text-red-400' : '' }}">
                            {{ $marketplace->token_expires_at->format('d/m/Y H:i') }}
                            @if($marketplace->isTokenExpired())
                                (Expirado)
                            @endif
                        </span>
                    </div>
                    @endif
                </div>
            </x-ui.card>

            {{-- Sync settings --}}
            <x-ui.card title="Configuracoes de Sincronizacao">
                @php $settings = $marketplace->settings ?? []; @endphp
                <div class="space-y-3 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-gray-600 dark:text-zinc-400">Sincronizar Produtos</span>
                        @if($settings['auto_sync_products'] ?? false)
                            <x-ui.badge color="success">Ativo</x-ui.badge>
                        @else
                            <x-ui.badge color="neutral">Inativo</x-ui.badge>
                        @endif
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-600 dark:text-zinc-400">Sincronizar Pedidos</span>
                        @if($settings['auto_sync_orders'] ?? false)
                            <x-ui.badge color="success">Ativo</x-ui.badge>
                        @else
                            <x-ui.badge color="neutral">Inativo</x-ui.badge>
                        @endif
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-600 dark:text-zinc-400">Atualizar Estoque</span>
                        @if($settings['auto_update_stock'] ?? false)
                            <x-ui.badge color="success">Ativo</x-ui.badge>
                        @else
                            <x-ui.badge color="neutral">Inativo</x-ui.badge>
                        @endif
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-600 dark:text-zinc-400">Intervalo de Sync</span>
                        <span>{{ $settings['sync_interval'] ?? 30 }} min</span>
                    </div>
                </div>
            </x-ui.card>

            {{-- Last error --}}
            @if($marketplace->last_error)
            <x-ui.card title="Ultimo Erro">
                <div class="p-3 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
                    <p class="text-sm text-red-700 dark:text-red-300">{{ $marketplace->last_error }}</p>
                </div>
            </x-ui.card>
            @endif
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">
            {{-- Status --}}
            <x-ui.card title="Status">
                <div class="space-y-3">
                    <div>
                        <x-ui.badge :color="$marketplace->status->color()">{{ $marketplace->status->label() }}</x-ui.badge>
                    </div>
                    <div>
                        <span class="text-xs text-gray-500 dark:text-zinc-400">Saude da Conta</span>
                        <div class="mt-1">
                            @if($marketplace->is_healthy)
                                <span class="flex items-center gap-1 text-sm text-green-600 dark:text-green-400">
                                    <x-heroicon-s-check-circle class="w-4 h-4" />
                                    Saudavel
                                </span>
                            @else
                                <span class="flex items-center gap-1 text-sm text-yellow-600 dark:text-yellow-400">
                                    <x-heroicon-s-exclamation-triangle class="w-4 h-4" />
                                    Requer atencao
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            </x-ui.card>

            {{-- Sync info --}}
            <x-ui.card title="Sincronizacao" x-data="{ showSyncForm: false }">
                <div class="space-y-3 text-sm">
                    <div>
                        <span class="text-gray-500 dark:text-zinc-400">Ultima Sincronizacao</span>
                        <p class="mt-1">
                            @if($marketplace->last_synced_at)
                                {{ $marketplace->last_synced_at->format('d/m/Y H:i') }}
                                <span class="text-xs text-gray-400 dark:text-zinc-500">({{ $marketplace->last_synced_at->diffForHumans() }})</span>
                            @else
                                <span class="text-amber-600 dark:text-amber-400 font-medium">Nunca sincronizado</span>
                            @endif
                        </p>
                    </div>

                    {{-- Diagnóstico rápido --}}
                    @php
                        $diagProblems = [];
                        if (!$marketplace->shop_id) {
                            $diagProblems[] = ['type' => 'error', 'msg' => 'Shop ID não configurado — necessário para buscar pedidos'];
                        }
                        if ($marketplace->isTokenExpired()) {
                            $expiredAt = $marketplace->token_expires_at?->format('d/m/Y H:i') ?? 'desconhecido';
                            $diagProblems[] = ['type' => 'error', 'msg' => "Token OAuth expirado em {$expiredAt}"];
                        }
                        try {
                            $creds = $marketplace->credentials ?? [];
                        } catch (\Exception $e) {
                            $creds = [];
                        }
                        if (empty($creds['refresh_token'])) {
                            $diagProblems[] = ['type' => 'warning', 'msg' => 'Refresh token não configurado — token não será renovado automaticamente'];
                        }
                        if ($marketplace->status->value !== 'active') {
                            $diagProblems[] = ['type' => 'error', 'msg' => 'Conta não está ativa (status: ' . $marketplace->status->label() . ') — sync automático bloqueado'];
                        }
                    @endphp

                    @if(count($diagProblems) > 0)
                    <div class="pt-2 border-t border-gray-200 dark:border-zinc-700 space-y-1.5">
                        <span class="text-xs font-medium text-gray-500 dark:text-zinc-400">Problemas detectados:</span>
                        @foreach($diagProblems as $prob)
                            <div class="flex items-start gap-1.5 text-xs {{ $prob['type'] === 'error' ? 'text-red-600 dark:text-red-400' : 'text-amber-600 dark:text-amber-400' }}">
                                @if($prob['type'] === 'error')
                                    <x-heroicon-s-x-circle class="w-3.5 h-3.5 flex-shrink-0 mt-0.5" />
                                @else
                                    <x-heroicon-s-exclamation-triangle class="w-3.5 h-3.5 flex-shrink-0 mt-0.5" />
                                @endif
                                <span>{{ $prob['msg'] }}</span>
                            </div>
                        @endforeach
                    </div>
                    @else
                    <div class="flex items-center gap-1.5 text-xs text-green-600 dark:text-green-400 pt-1 border-t border-gray-200 dark:border-zinc-700">
                        <x-heroicon-s-check-circle class="w-3.5 h-3.5" />
                        <span>Conta saudável para sincronização</span>
                    </div>
                    @endif

                    <div class="pt-2 border-t border-gray-200 dark:border-zinc-700 space-y-2">
                        {{-- Sync Pedidos com opção de dias --}}
                        <form method="POST" action="{{ route('marketplaces.sync', $marketplace) }}" x-data="{ days: 7 }">
                            @csrf
                            <input type="hidden" name="type" value="orders">
                            <div class="flex gap-1 mb-1.5">
                                <label class="text-xs text-gray-500 dark:text-zinc-400 self-center">Últimos</label>
                                <input type="number" name="days" x-model="days" min="1" max="365"
                                    class="w-16 text-xs border border-gray-300 dark:border-zinc-600 rounded px-1.5 py-1 bg-white dark:bg-zinc-800 text-gray-900 dark:text-zinc-100" />
                                <label class="text-xs text-gray-500 dark:text-zinc-400 self-center">dias</label>
                            </div>
                            <button type="submit" class="btn-primary btn-sm w-full">
                                <x-heroicon-o-arrow-path class="w-4 h-4" />
                                Sincronizar Pedidos
                            </button>
                        </form>

                        <form method="POST" action="{{ route('marketplaces.sync', $marketplace) }}">
                            @csrf
                            <input type="hidden" name="type" value="listings">
                            <button type="submit" class="btn-secondary btn-sm w-full">
                                <x-heroicon-o-arrow-path class="w-4 h-4" />
                                Sincronizar Anuncios
                            </button>
                        </form>
                    </div>
                </div>
            </x-ui.card>

            {{-- Timeline --}}
            <x-ui.card title="Historico">
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-zinc-400">Criado em</span>
                        <span>{{ $marketplace->created_at->format('d/m/Y H:i') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-zinc-400">Atualizado em</span>
                        <span>{{ $marketplace->updated_at->format('d/m/Y H:i') }}</span>
                    </div>
                </div>
            </x-ui.card>
        </div>
    </div>
</x-app-layout>
