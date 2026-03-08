<x-app-layout>
    <x-slot name="header">Contas Melhor Envios</x-slot>
    <x-slot name="actions">
        <a href="{{ route('settings.me.create') }}" class="btn-primary">
            <x-heroicon-o-plus class="w-4 h-4" />
            Nova Conta
        </a>
    </x-slot>
    <x-slot name="breadcrumbs">
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <a href="{{ route('settings.index') }}" class="hover:text-gray-700 dark:hover:text-zinc-200">Configurações</a>
        </li>
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <span class="text-gray-700 dark:text-zinc-200">Melhor Envios</span>
        </li>
    </x-slot>

    <x-ui.card>
        @if($accounts->isEmpty())
        <div class="text-center py-10">
            <x-heroicon-o-truck class="w-12 h-12 text-gray-300 dark:text-zinc-600 mx-auto mb-3" />
            <p class="text-gray-500 dark:text-zinc-400 mb-4">Nenhuma conta Melhor Envios configurada.</p>
            <a href="{{ route('settings.me.create') }}" class="btn-primary">
                Adicionar Conta
            </a>
        </div>
        @else
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Ambiente</th>
                    <th>Remetente</th>
                    <th>CEP de coleta</th>
                    <th>Canais vinculados</th>
                    <th>OAuth</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($accounts as $account)
                <tr>
                    <td class="font-medium">{{ $account->name }}</td>
                    <td>
                        <x-ui.badge :color="$account->isSandbox() ? 'warning' : 'success'">
                            {{ $account->isSandbox() ? 'Sandbox' : 'Produção' }}
                        </x-ui.badge>
                    </td>
                    <td>{{ $account->from_name ?? '—' }}</td>
                    <td class="font-mono text-sm">{{ $account->from_cep ?? '—' }}</td>
                    <td>
                        <span class="text-sm text-gray-600 dark:text-zinc-400">
                            {{ $account->marketplaceAccounts->count() }} canal(is)
                        </span>
                    </td>
                    <td>
                        @if($account->is_active && $account->access_token)
                            <div class="flex items-center gap-1.5">
                                <x-heroicon-s-check-circle class="w-4 h-4 text-green-500" />
                                <span class="text-xs text-green-600 dark:text-green-400">Conectado</span>
                            </div>
                            @if($account->token_expires_at)
                            <p class="text-xs text-gray-400 mt-0.5">
                                Expira: {{ $account->token_expires_at->format('d/m/Y H:i') }}
                            </p>
                            @endif
                        @else
                            <a href="{{ route('me.connect', $account) }}"
                               class="inline-flex items-center gap-1.5 text-xs font-medium text-primary-600 dark:text-primary-400 hover:underline">
                                <x-heroicon-o-link class="w-3.5 h-3.5" />
                                Conectar OAuth
                            </a>
                        @endif
                    </td>
                    <td>
                        <div class="flex items-center gap-2 justify-end">
                            @if($account->is_active && $account->access_token)
                            <a href="{{ route('me.connect', $account) }}" class="btn-secondary btn-xs" title="Reconectar">
                                <x-heroicon-o-arrow-path class="w-3.5 h-3.5" />
                                Reconectar
                            </a>
                            @endif
                            <a href="{{ route('settings.me.edit', $account) }}" class="btn-secondary btn-xs">
                                <x-heroicon-o-pencil class="w-3.5 h-3.5" />
                                Editar
                            </a>
                            <form method="POST" action="{{ route('settings.me.destroy', $account) }}"
                                  onsubmit="return confirm('Remover conta {{ $account->name }}?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-danger btn-xs">
                                    <x-heroicon-o-trash class="w-3.5 h-3.5" />
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </x-ui.card>
</x-app-layout>
