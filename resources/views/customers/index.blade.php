<x-app-layout>
    <x-slot name="header">Clientes</x-slot>
    <x-slot name="subtitle">Compradores importados dos marketplaces</x-slot>
    <x-slot name="breadcrumbs">
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <span class="text-gray-700 dark:text-zinc-200">Clientes</span>
        </li>
    </x-slot>

    <div class="space-y-4">
        {{-- Search --}}
        <div class="card p-4">
            <form method="GET" class="flex gap-3">
                <input type="text" name="search" value="{{ request('search') }}"
                    placeholder="Buscar por nome, email, documento..."
                    class="form-input flex-1">
                <button type="submit" class="btn-primary">
                    <x-heroicon-o-magnifying-glass class="w-4 h-4" />
                    Buscar
                </button>
                @if(request('search'))
                <a href="{{ route('customers.index') }}" class="btn-secondary">Limpar</a>
                @endif
            </form>
        </div>

        {{-- Table --}}
        <div class="card overflow-hidden">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Email</th>
                        <th>Telefone</th>
                        <th>Pedidos</th>
                        <th>Total Gasto</th>
                        <th>Último Pedido</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($customers as $customer)
                    <tr>
                        <td>
                            <div class="font-medium text-gray-900 dark:text-white">{{ $customer->name }}</div>
                            @if($customer->document)
                                <div class="text-xs text-gray-400 dark:text-zinc-500 font-mono">{{ $customer->document }}</div>
                            @endif
                        </td>
                        <td>{{ $customer->email ?? '—' }}</td>
                        <td>{{ $customer->phone ?? '—' }}</td>
                        <td>
                            <span class="badge-neutral">{{ $customer->orders_count }}</span>
                        </td>
                        <td class="font-mono">
                            R$ {{ number_format($customer->orders_sum_total ?? 0, 2, ',', '.') }}
                        </td>
                        <td class="text-sm text-gray-500 dark:text-zinc-400">
                            @if($customer->orders->first())
                                {{ $customer->orders->first()->created_at->format('d/m/Y') }}
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('customers.show', $customer) }}" class="btn-ghost btn-sm">
                                <x-heroicon-o-eye class="w-4 h-4" />
                                Ver
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-12 text-gray-500 dark:text-zinc-400">
                            Nenhum cliente encontrado.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $customers->links() }}
    </div>
</x-app-layout>
