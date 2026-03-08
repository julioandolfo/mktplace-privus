<x-app-layout>
    <x-slot name="header">Contas Webmaniabr</x-slot>
    <x-slot name="actions">
        <a href="{{ route('settings.webmania.create') }}" class="btn-primary">
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
            <span class="text-gray-700 dark:text-zinc-200">Webmaniabr</span>
        </li>
    </x-slot>

    <x-ui.card>
        @if($accounts->isEmpty())
        <div class="text-center py-10">
            <x-heroicon-o-document-text class="w-12 h-12 text-gray-300 dark:text-zinc-600 mx-auto mb-3" />
            <p class="text-gray-500 dark:text-zinc-400 mb-4">Nenhuma conta Webmaniabr configurada.</p>
            <a href="{{ route('settings.webmania.create') }}" class="btn-primary">
                Configurar Conta
            </a>
        </div>
        @else
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Ambiente</th>
                    <th>CFOP padrão</th>
                    <th>Canais vinculados</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($accounts as $account)
                <tr>
                    <td class="font-medium">{{ $account->name }}</td>
                    <td>
                        <x-ui.badge :color="$account->isSandbox() ? 'warning' : 'success'">
                            {{ $account->isSandbox() ? 'Homologação' : 'Produção' }}
                        </x-ui.badge>
                    </td>
                    <td class="font-mono text-sm">{{ $account->default_cfop ?? '—' }}</td>
                    <td>
                        <span class="text-sm text-gray-600 dark:text-zinc-400">
                            {{ $account->marketplaceAccounts->count() }} canal(is)
                        </span>
                    </td>
                    <td>
                        @if($account->is_active)
                            <x-ui.badge color="success">Ativa</x-ui.badge>
                        @else
                            <x-ui.badge color="default">Inativa</x-ui.badge>
                        @endif
                    </td>
                    <td>
                        <div class="flex items-center gap-2 justify-end">
                            <a href="{{ route('settings.webmania.edit', $account) }}" class="btn-secondary btn-xs">
                                <x-heroicon-o-pencil class="w-3.5 h-3.5" />
                                Editar
                            </a>
                            <form method="POST" action="{{ route('settings.webmania.destroy', $account) }}"
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
