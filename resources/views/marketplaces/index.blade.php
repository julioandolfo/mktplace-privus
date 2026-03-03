<x-app-layout>
    <x-slot name="header">Marketplaces</x-slot>
    <x-slot name="subtitle">Conecte suas contas de marketplace para sincronizar produtos e pedidos</x-slot>
    <x-slot name="breadcrumbs">
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <span class="text-gray-700 dark:text-zinc-200">Marketplaces</span>
        </li>
    </x-slot>

    {{-- Flash messages --}}
    @if(session('success'))
        <div class="mb-6 flex items-center gap-3 p-4 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-800 dark:text-green-300">
            <x-heroicon-s-check-circle class="w-5 h-5 flex-shrink-0" />
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-6 flex items-center gap-3 p-4 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-800 dark:text-red-300">
            <x-heroicon-s-exclamation-circle class="w-5 h-5 flex-shrink-0" />
            {{ session('error') }}
        </div>
    @endif

    {{-- Marketplace type cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
        @foreach($types as $type)
            @php $typeAccounts = $accounts->get($type->value, collect()); @endphp
            <div class="card p-5 flex flex-col gap-4">
                {{-- Header --}}
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <span class="w-4 h-4 rounded-full flex-shrink-0" style="background-color: {{ $type->color() }}"></span>
                        <span class="font-semibold text-gray-900 dark:text-white">{{ $type->label() }}</span>
                    </div>
                    @if($typeAccounts->isNotEmpty())
                        <span class="inline-flex items-center gap-1 text-xs font-medium text-green-700 dark:text-green-400 bg-green-100 dark:bg-green-900/30 px-2 py-0.5 rounded-full">
                            <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>
                            {{ $typeAccounts->count() }} {{ $typeAccounts->count() === 1 ? 'conta' : 'contas' }}
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1 text-xs font-medium text-gray-400 dark:text-zinc-500 bg-gray-100 dark:bg-zinc-800 px-2 py-0.5 rounded-full">
                            Nao conectado
                        </span>
                    @endif
                </div>

                {{-- Connected accounts list --}}
                @if($typeAccounts->isNotEmpty())
                    <div class="space-y-2 flex-1">
                        @foreach($typeAccounts as $account)
                            <div class="flex items-center justify-between text-sm gap-2">
                                <div class="min-w-0">
                                    <p class="font-medium text-gray-800 dark:text-zinc-200 truncate">{{ $account->account_name }}</p>
                                    @if($account->shop_id)
                                        <p class="text-xs font-mono text-gray-400 dark:text-zinc-500">ID: {{ $account->shop_id }}</p>
                                    @endif
                                </div>
                                <x-ui.badge :color="$account->status->color()" class="flex-shrink-0">{{ $account->status->label() }}</x-ui.badge>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-sm text-gray-400 dark:text-zinc-500 flex-1">
                        @if($type->supportsOAuth())
                            Clique em Conectar para vincular sua conta via OAuth.
                        @elseif($type->value === 'shopee')
                            Usa autenticacao HMAC com Partner ID e Partner Key.
                        @elseif($type->value === 'woocommerce')
                            Insira a URL da loja e as chaves de API REST do WooCommerce.
                        @elseif($type->value === 'tiktok')
                            Requer aprovacao do app no TikTok Shop Partner Center.
                        @endif
                    </div>
                @endif

                {{-- Actions --}}
                <div class="mt-auto pt-2 border-t border-gray-100 dark:border-zinc-700/50">
                    @if($type->supportsOAuth())
                        <a href="{{ route('marketplaces.oauth.redirect', $type->value) }}"
                           class="btn-primary btn-sm w-full justify-center">
                            <x-heroicon-o-plus-circle class="w-4 h-4" />
                            {{ $typeAccounts->isNotEmpty() ? 'Adicionar conta' : 'Conectar' }}
                        </a>
                    @else
                        <a href="{{ route('marketplaces.create') }}?type={{ $type->value }}"
                           class="btn-secondary btn-sm w-full justify-center">
                            <x-heroicon-o-key class="w-4 h-4" />
                            Configurar
                        </a>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    {{-- Connected accounts table --}}
    @if($allAccounts->isNotEmpty())
        <x-ui.card title="Contas Conectadas" :padding="false">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Marketplace</th>
                        <th>Conta</th>
                        <th>Status</th>
                        <th>Ultima Sync</th>
                        <th>Token</th>
                        <th class="text-right">Acoes</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($allAccounts as $account)
                    <tr>
                        <td>
                            <div class="flex items-center gap-2">
                                <span class="w-3 h-3 rounded-full flex-shrink-0" style="background-color: {{ $account->marketplace_type->color() }}"></span>
                                <span class="font-medium">{{ $account->marketplace_type->label() }}</span>
                            </div>
                        </td>
                        <td>
                            <p class="font-medium text-gray-900 dark:text-white">{{ $account->account_name }}</p>
                            @if($account->shop_id)
                                <p class="text-xs font-mono text-gray-500 dark:text-zinc-400">{{ $account->shop_id }}</p>
                            @endif
                        </td>
                        <td>
                            <x-ui.badge :color="$account->status->color()">{{ $account->status->label() }}</x-ui.badge>
                        </td>
                        <td class="text-sm text-gray-500 dark:text-zinc-400">
                            {{ $account->last_synced_at?->diffForHumans() ?? 'Nunca' }}
                        </td>
                        <td class="text-sm">
                            @if($account->token_expires_at)
                                @if($account->isTokenExpired())
                                    <span class="text-red-600 dark:text-red-400">Expirado</span>
                                @else
                                    <span class="text-gray-500 dark:text-zinc-400">{{ $account->token_expires_at->format('d/m/Y H:i') }}</span>
                                @endif
                            @else
                                <span class="text-gray-400 dark:text-zinc-500">—</span>
                            @endif
                        </td>
                        <td class="text-right">
                            <div class="flex items-center justify-end gap-1">
                                @if($account->marketplace_type->supportsOAuth())
                                    <a href="{{ route('marketplaces.oauth.redirect', $account->marketplace_type->value) }}"
                                       class="btn-ghost btn-xs" title="Reconectar esta conta">
                                        <x-heroicon-o-arrow-path class="w-4 h-4" />
                                    </a>
                                @endif
                                <a href="{{ route('marketplaces.show', $account) }}" class="btn-ghost btn-xs" title="Ver">
                                    <x-heroicon-o-eye class="w-4 h-4" />
                                </a>
                                <a href="{{ route('marketplaces.edit', $account) }}" class="btn-ghost btn-xs" title="Editar">
                                    <x-heroicon-o-pencil-square class="w-4 h-4" />
                                </a>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </x-ui.card>
    @endif
</x-app-layout>
