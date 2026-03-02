<x-app-layout>
    <x-slot name="header">Empresas</x-slot>
    <x-slot name="subtitle">Gerencie suas empresas e CNPJs</x-slot>
    <x-slot name="actions">
        <a href="{{ route('companies.create') }}" class="btn-primary">
            <x-heroicon-s-plus class="w-4 h-4" />
            Nova Empresa
        </a>
    </x-slot>

    <x-slot name="breadcrumbs">
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <span class="text-gray-700 dark:text-zinc-200">Empresas</span>
        </li>
    </x-slot>

    @php $companies = \App\Models\Company::orderBy('name')->get(); @endphp

    @if($companies->isEmpty())
        <x-ui.card>
            <x-ui.empty-state
                title="Nenhuma empresa cadastrada"
                description="Cadastre sua primeira empresa para comecar a usar o sistema.">
                <x-slot name="icon">
                    <x-heroicon-o-building-office class="w-8 h-8 text-gray-400 dark:text-zinc-500" />
                </x-slot>
                <x-slot name="action">
                    <a href="{{ route('companies.create') }}" class="btn-primary">
                        <x-heroicon-s-plus class="w-4 h-4" />
                        Cadastrar Empresa
                    </a>
                </x-slot>
            </x-ui.empty-state>
        </x-ui.card>
    @else
        <x-ui.card :padding="false">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>CNPJ/CPF</th>
                        <th>Inscricao Estadual</th>
                        <th>Cidade/UF</th>
                        <th>Status</th>
                        <th class="text-right">Acoes</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($companies as $company)
                    <tr>
                        <td>
                            <div>
                                <p class="font-medium text-gray-900 dark:text-white">{{ $company->name }}</p>
                                @if($company->trade_name)
                                <p class="text-xs text-gray-500 dark:text-zinc-400">{{ $company->trade_name }}</p>
                                @endif
                            </div>
                        </td>
                        <td class="font-mono text-sm">{{ $company->formatted_document }}</td>
                        <td>{{ $company->state_registration ?? '-' }}</td>
                        <td>
                            @if($company->address)
                                {{ $company->address['city'] ?? '' }}{{ isset($company->address['state']) ? '/' . $company->address['state'] : '' }}
                            @else
                                -
                            @endif
                        </td>
                        <td>
                            @if($company->is_active)
                                <x-ui.badge color="success">Ativa</x-ui.badge>
                            @else
                                <x-ui.badge color="neutral">Inativa</x-ui.badge>
                            @endif
                        </td>
                        <td class="text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('companies.edit', $company) }}" class="btn-ghost btn-sm">
                                    <x-heroicon-o-pencil-square class="w-4 h-4" />
                                </a>
                                <form method="POST" action="{{ route('companies.destroy', $company) }}" onsubmit="return confirm('Tem certeza que deseja remover esta empresa?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-ghost btn-sm text-red-600 dark:text-red-400 hover:text-red-700">
                                        <x-heroicon-o-trash class="w-4 h-4" />
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </x-ui.card>
    @endif
</x-app-layout>
