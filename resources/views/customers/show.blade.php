<x-app-layout>
    <x-slot name="header">{{ $customer->name }}</x-slot>
    <x-slot name="breadcrumbs">
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <a href="{{ route('customers.index') }}" class="hover:text-gray-700 dark:hover:text-zinc-200">Clientes</a>
        </li>
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <span class="text-gray-700 dark:text-zinc-200">{{ $customer->name }}</span>
        </li>
    </x-slot>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Info --}}
        <div class="space-y-6">
            <x-ui.card title="Dados do Cliente">
                <div class="space-y-3 text-sm">
                    <div>
                        <span class="text-gray-500 dark:text-zinc-400">Nome</span>
                        <p class="font-medium mt-0.5">{{ $customer->name }}</p>
                    </div>
                    @if($customer->email)
                    <div>
                        <span class="text-gray-500 dark:text-zinc-400">Email</span>
                        <p class="mt-0.5">{{ $customer->email }}</p>
                    </div>
                    @endif
                    @if($customer->phone)
                    <div>
                        <span class="text-gray-500 dark:text-zinc-400">Telefone</span>
                        <p class="mt-0.5">{{ $customer->phone }}</p>
                    </div>
                    @endif
                    @if($customer->document)
                    <div>
                        <span class="text-gray-500 dark:text-zinc-400">CPF/CNPJ</span>
                        <p class="font-mono mt-0.5">{{ $customer->document }}</p>
                    </div>
                    @endif
                    @if($customer->address)
                    <div>
                        <span class="text-gray-500 dark:text-zinc-400">Endereco</span>
                        <p class="mt-0.5">{{ $customer->address['street'] ?? '' }}</p>
                        <p class="text-gray-400 dark:text-zinc-500">
                            {{ $customer->address['city'] ?? '' }}
                            @if($customer->address['state'] ?? null) — {{ $customer->address['state'] }} @endif
                            @if($customer->address['zip'] ?? null) / {{ $customer->address['zip'] }} @endif
                        </p>
                    </div>
                    @endif
                </div>
            </x-ui.card>

            <x-ui.card title="Resumo">
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-zinc-400">Total de Pedidos</span>
                        <span class="font-medium">{{ $orders->total() }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-zinc-400">Total Gasto</span>
                        <span class="font-mono font-medium">R$ {{ number_format($customer->total_spent, 2, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-zinc-400">Cliente desde</span>
                        <span>{{ $customer->created_at->format('d/m/Y') }}</span>
                    </div>
                </div>
            </x-ui.card>

            @if(!empty($customer->meta['ml_user_id']))
            <x-ui.card title="Mercado Livre">
                <div class="text-sm">
                    <span class="text-gray-500 dark:text-zinc-400">User ID</span>
                    <p class="font-mono mt-0.5">{{ $customer->meta['ml_user_id'] }}</p>
                </div>
            </x-ui.card>
            @endif
        </div>

        {{-- Orders --}}
        <div class="lg:col-span-2">
            <x-ui.card title="Historico de Pedidos">
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Pedido</th>
                                <th>Marketplace</th>
                                <th>Status</th>
                                <th>Total</th>
                                <th>Data</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($orders as $order)
                            <tr>
                                <td class="font-mono text-xs">{{ $order->order_number }}</td>
                                <td class="text-sm">{{ $order->marketplaceAccount?->account_name ?? '—' }}</td>
                                <td>
                                    <x-ui.badge :color="$order->status->color()">{{ $order->status->label() }}</x-ui.badge>
                                </td>
                                <td class="font-mono">R$ {{ number_format($order->total, 2, ',', '.') }}</td>
                                <td class="text-sm text-gray-500 dark:text-zinc-400">
                                    {{ $order->created_at->format('d/m/Y H:i') }}
                                </td>
                                <td>
                                    <a href="{{ route('orders.show', $order) }}" class="btn-ghost btn-sm">
                                        <x-heroicon-o-eye class="w-4 h-4" />
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center py-8 text-gray-400 dark:text-zinc-500">
                                    Nenhum pedido encontrado.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($orders->hasPages())
                <div class="p-4 border-t border-gray-100 dark:border-zinc-800">
                    {{ $orders->links() }}
                </div>
                @endif
            </x-ui.card>
        </div>
    </div>
</x-app-layout>
