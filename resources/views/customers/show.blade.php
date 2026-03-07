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

    @php
        $addr     = $customer->address ?? [];
        $meta     = $customer->meta ?? [];
        $mlUserId = $meta['ml_user_id'] ?? null;
    @endphp

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- ─── Sidebar de informações ─────────────────────────────────── --}}
        <div class="space-y-6">

            {{-- Dados pessoais --}}
            <x-ui.card title="Dados do Cliente">
                <div class="space-y-3 text-sm">
                    {{-- Avatar / Nome --}}
                    <div class="flex items-center gap-3 pb-3 border-b border-gray-100 dark:border-zinc-800">
                        <div class="w-12 h-12 rounded-full bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center flex-shrink-0">
                            <span class="text-lg font-bold text-primary-600 dark:text-primary-400">{{ strtoupper(mb_substr($customer->name, 0, 1)) }}</span>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-900 dark:text-white">{{ $customer->name }}</p>
                            <p class="text-xs text-gray-400 dark:text-zinc-500">Cliente desde {{ $customer->created_at->format('d/m/Y') }}</p>
                        </div>
                    </div>

                    {{-- E-mail --}}
                    @if($customer->email)
                    <div class="flex items-start gap-2">
                        <x-heroicon-o-envelope class="w-4 h-4 text-gray-400 flex-shrink-0 mt-0.5" />
                        <div>
                            <span class="text-xs text-gray-400 dark:text-zinc-500 block">E-mail</span>
                            <a href="mailto:{{ $customer->email }}" class="hover:text-primary-500 transition-colors break-all">{{ $customer->email }}</a>
                        </div>
                    </div>
                    @endif

                    {{-- Telefone --}}
                    @if($customer->phone)
                    <div class="flex items-start gap-2">
                        <x-heroicon-o-phone class="w-4 h-4 text-gray-400 flex-shrink-0 mt-0.5" />
                        <div>
                            <span class="text-xs text-gray-400 dark:text-zinc-500 block">Telefone</span>
                            <a href="tel:{{ $customer->phone }}" class="hover:text-primary-500 transition-colors">{{ $customer->phone }}</a>
                        </div>
                    </div>
                    @endif

                    {{-- CPF/CNPJ --}}
                    @if($customer->document)
                    <div class="flex items-start gap-2">
                        <x-heroicon-o-identification class="w-4 h-4 text-gray-400 flex-shrink-0 mt-0.5" />
                        <div>
                            <span class="text-xs text-gray-400 dark:text-zinc-500 block">CPF / CNPJ</span>
                            <span class="font-mono">{{ $customer->document }}</span>
                        </div>
                    </div>
                    @endif

                    {{-- Endereço --}}
                    @if(!empty($addr))
                    <div class="flex items-start gap-2">
                        <x-heroicon-o-map-pin class="w-4 h-4 text-gray-400 flex-shrink-0 mt-0.5" />
                        <div>
                            <span class="text-xs text-gray-400 dark:text-zinc-500 block">Endereço</span>
                            @if(!empty($addr['street'])) <p>{{ $addr['street'] }}</p> @endif
                            @if(!empty($addr['complement'])) <p class="text-gray-400 dark:text-zinc-500 text-xs">{{ $addr['complement'] }}</p> @endif
                            @if(!empty($addr['neighborhood'])) <p class="text-gray-400 dark:text-zinc-500 text-xs">{{ $addr['neighborhood'] }}</p> @endif
                            @php
                                $cityState = implode(' - ', array_filter([$addr['city'] ?? null, $addr['state'] ?? null]));
                            @endphp
                            @if($cityState) <p>{{ $cityState }}</p> @endif
                            @if(!empty($addr['zip'])) <p class="text-xs text-gray-400 dark:text-zinc-500">CEP {{ $addr['zip'] }}</p> @endif
                        </div>
                    </div>
                    @endif
                </div>
            </x-ui.card>

            {{-- Resumo financeiro --}}
            <x-ui.card title="Resumo">
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-zinc-400">Total de Pedidos</span>
                        <span class="font-semibold">{{ $orders->total() }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-zinc-400">Total Gasto</span>
                        <span class="font-mono font-semibold text-emerald-600 dark:text-emerald-400">R$ {{ number_format($customer->total_spent, 2, ',', '.') }}</span>
                    </div>
                    @if($customer->last_order_at)
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-zinc-400">Último Pedido</span>
                        <span>{{ $customer->last_order_at->format('d/m/Y') }}</span>
                    </div>
                    @endif
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-zinc-400">Cliente desde</span>
                        <span>{{ $customer->created_at->format('d/m/Y') }}</span>
                    </div>
                </div>
            </x-ui.card>

            {{-- Mercado Livre --}}
            @if($mlUserId)
            <x-ui.card>
                <x-slot name="title">
                    <div class="flex items-center gap-2">
                        {!! \App\Enums\MarketplaceType::MercadoLivre->logoSvg('w-5 h-5') !!}
                        <span>Mercado Livre</span>
                    </div>
                </x-slot>
                <div class="space-y-2 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500 dark:text-zinc-400">User ID</span>
                        <span class="font-mono">{{ $mlUserId }}</span>
                    </div>
                    <a href="https://www.mercadolivre.com.br/perfil/{{ $mlUserId }}" target="_blank"
                       class="flex items-center gap-1.5 text-xs text-primary-500 hover:text-primary-400 transition-colors">
                        <x-heroicon-o-arrow-top-right-on-square class="w-3.5 h-3.5" />
                        Ver perfil no ML
                    </a>
                </div>
            </x-ui.card>
            @endif
        </div>

        {{-- ─── Histórico de pedidos ───────────────────────────────────── --}}
        <div class="lg:col-span-2">
            <x-ui.card title="Historico de Pedidos">
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Pedido</th>
                                <th>Marketplace</th>
                                <th>Itens</th>
                                <th>Status</th>
                                <th>Pagamento</th>
                                <th>Total</th>
                                <th>Data</th>
                                <th>Envio</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($orders as $order)
                            @php $mlAcc = $order->marketplaceAccount; @endphp
                            <tr>
                                <td class="font-mono text-xs">
                                    <a href="{{ route('orders.show', $order) }}" class="text-primary-600 dark:text-primary-400 hover:underline">
                                        {{ $order->order_number }}
                                    </a>
                                </td>
                                <td class="text-sm">
                                    @if($mlAcc)
                                    <div class="flex items-center gap-1.5">
                                        {!! $mlAcc->marketplace_type->logoSvg('w-4 h-4') !!}
                                        <span class="text-xs text-gray-600 dark:text-zinc-400">{{ $mlAcc->account_name }}</span>
                                    </div>
                                    @else
                                        <span class="text-gray-400 dark:text-zinc-500">—</span>
                                    @endif
                                </td>
                                <td class="text-center text-sm">
                                    {{ $order->items->sum('quantity') }}
                                </td>
                                <td>
                                    <x-ui.badge :color="$order->status->color()">{{ $order->status->label() }}</x-ui.badge>
                                </td>
                                <td>
                                    <x-ui.badge :color="$order->payment_status->color()">{{ $order->payment_status->label() }}</x-ui.badge>
                                </td>
                                <td class="font-mono text-sm">R$ {{ number_format($order->total, 2, ',', '.') }}</td>
                                <td class="text-sm text-gray-500 dark:text-zinc-400 whitespace-nowrap">
                                    {{ $order->created_at->format('d/m/Y') }}
                                </td>
                                <td class="text-sm text-gray-500 dark:text-zinc-400 whitespace-nowrap">
                                    @if($order->shipped_at)
                                        {{ $order->shipped_at->format('d/m/Y') }}
                                    @elseif($order->tracking_code)
                                        <span class="text-xs font-mono">{{ $order->tracking_code }}</span>
                                    @else
                                        <span class="text-gray-300 dark:text-zinc-600">—</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('orders.show', $order) }}" class="btn-ghost btn-sm">
                                        <x-heroicon-o-eye class="w-4 h-4" />
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="9" class="text-center py-8 text-gray-400 dark:text-zinc-500">
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
