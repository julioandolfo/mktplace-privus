<x-app-layout>
    <x-slot name="header">Nota Fiscal {{ $invoice->number ? '#' . $invoice->number : '(Pendente)' }}</x-slot>
    <x-slot name="actions">
        @if($invoice->pdf_url)
        <a href="{{ $invoice->pdf_url }}" target="_blank" class="btn-secondary">
            <x-heroicon-o-arrow-down-tray class="w-4 h-4" />
            PDF
        </a>
        @endif
    </x-slot>
    <x-slot name="breadcrumbs">
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <a href="{{ route('invoices.index') }}" class="hover:text-gray-700 dark:hover:text-zinc-200">Notas Fiscais</a>
        </li>
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <span class="text-gray-700 dark:text-zinc-200">{{ $invoice->number ?? 'Pendente' }}</span>
        </li>
    </x-slot>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Main content --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Invoice info --}}
            <x-ui.card title="Dados da Nota">
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="text-gray-500 dark:text-zinc-400">Tipo</span>
                        <p class="font-medium mt-1">{{ strtoupper($invoice->type) }}</p>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-zinc-400">Serie</span>
                        <p class="font-medium mt-1">{{ $invoice->series }}</p>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-zinc-400">Natureza da Operacao</span>
                        <p class="font-medium mt-1">{{ $invoice->nature_operation }}</p>
                    </div>
                    @if($invoice->protocol)
                    <div>
                        <span class="text-gray-500 dark:text-zinc-400">Protocolo</span>
                        <p class="font-medium font-mono mt-1">{{ $invoice->protocol }}</p>
                    </div>
                    @endif
                </div>
                @if($invoice->access_key)
                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-zinc-700">
                    <span class="text-xs text-gray-500 dark:text-zinc-400">Chave de Acesso</span>
                    <p class="font-mono text-sm mt-1 break-all">{{ $invoice->formatted_access_key }}</p>
                </div>
                @endif
            </x-ui.card>

            {{-- Values --}}
            <x-ui.card title="Valores">
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600 dark:text-zinc-400">Total dos Produtos</span>
                        <span>R$ {{ number_format($invoice->total_products, 2, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600 dark:text-zinc-400">Frete</span>
                        <span>R$ {{ number_format($invoice->total_shipping, 2, ',', '.') }}</span>
                    </div>
                    @if($invoice->total_discount > 0)
                    <div class="flex justify-between">
                        <span class="text-gray-600 dark:text-zinc-400">Desconto</span>
                        <span class="text-red-600 dark:text-red-400">- R$ {{ number_format($invoice->total_discount, 2, ',', '.') }}</span>
                    </div>
                    @endif
                    @if($invoice->total_tax > 0)
                    <div class="flex justify-between">
                        <span class="text-gray-600 dark:text-zinc-400">Impostos</span>
                        <span>R$ {{ number_format($invoice->total_tax, 2, ',', '.') }}</span>
                    </div>
                    @endif
                    <div class="flex justify-between font-medium text-base pt-2 border-t border-gray-200 dark:border-zinc-700">
                        <span>Total da Nota</span>
                        <span>R$ {{ number_format($invoice->total, 2, ',', '.') }}</span>
                    </div>
                </div>
            </x-ui.card>

            {{-- Order items --}}
            @if($invoice->order && $invoice->order->items->isNotEmpty())
            <x-ui.card title="Itens do Pedido" :padding="false">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Produto</th>
                            <th class="text-center">Qtd</th>
                            <th class="text-right">Valor Unit.</th>
                            <th class="text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoice->order->items as $item)
                        <tr>
                            <td>
                                <span class="font-medium">{{ $item->name }}</span>
                                @if($item->sku)
                                <span class="text-xs font-mono text-gray-400 ml-1">({{ $item->sku }})</span>
                                @endif
                            </td>
                            <td class="text-center">{{ $item->quantity }}</td>
                            <td class="text-right">R$ {{ number_format($item->unit_price, 2, ',', '.') }}</td>
                            <td class="text-right font-medium">R$ {{ number_format($item->total, 2, ',', '.') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-ui.card>
            @endif

            {{-- Rejection/Cancellation --}}
            @if($invoice->rejection_reason)
            <x-ui.card title="Motivo da Rejeicao">
                <p class="text-sm text-red-600 dark:text-red-400">{{ $invoice->rejection_reason }}</p>
            </x-ui.card>
            @endif
            @if($invoice->cancellation_reason)
            <x-ui.card title="Motivo do Cancelamento">
                <p class="text-sm text-gray-600 dark:text-zinc-400">{{ $invoice->cancellation_reason }}</p>
            </x-ui.card>
            @endif
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">
            {{-- Status --}}
            <x-ui.card title="Status">
                <div class="space-y-3">
                    <div>
                        <x-ui.badge :color="$invoice->status->color()">{{ $invoice->status->label() }}</x-ui.badge>
                    </div>
                    @if($invoice->order)
                    <div>
                        <span class="text-xs text-gray-500 dark:text-zinc-400">Pedido Vinculado</span>
                        <p class="mt-1">
                            <a href="{{ route('orders.show', $invoice->order) }}" class="font-mono text-primary-600 dark:text-primary-400 hover:underline">
                                {{ $invoice->order->order_number }}
                            </a>
                        </p>
                    </div>
                    @endif
                </div>
            </x-ui.card>

            {{-- Customer --}}
            <x-ui.card title="Destinatario">
                <div class="space-y-2 text-sm">
                    <p class="font-medium">{{ $invoice->customer_name }}</p>
                    @if($invoice->customer_document)
                    <p class="text-gray-600 dark:text-zinc-400">{{ $invoice->customer_document }}</p>
                    @endif
                    @if($invoice->customer_address)
                        @php $addr = $invoice->customer_address; @endphp
                        <div class="text-gray-600 dark:text-zinc-400 mt-2">
                            <p>{{ $addr['street'] ?? '' }}, {{ $addr['number'] ?? '' }}</p>
                            @if(!empty($addr['complement'])) <p>{{ $addr['complement'] }}</p> @endif
                            <p>{{ $addr['neighborhood'] ?? '' }}</p>
                            <p>{{ $addr['city'] ?? '' }} - {{ $addr['state'] ?? '' }}</p>
                            @if(!empty($addr['zipcode'])) <p>CEP: {{ $addr['zipcode'] }}</p> @endif
                        </div>
                    @endif
                </div>
            </x-ui.card>

            {{-- Emitter --}}
            @if($invoice->company)
            <x-ui.card title="Emitente">
                <p class="text-sm font-medium">{{ $invoice->company->name }}</p>
                @if($invoice->company->document)
                <p class="text-sm text-gray-600 dark:text-zinc-400">{{ $invoice->company->formatted_document }}</p>
                @endif
            </x-ui.card>
            @endif

            {{-- Timeline --}}
            <x-ui.card title="Historico">
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-zinc-400">Criada em</span>
                        <span>{{ $invoice->created_at->format('d/m/Y H:i') }}</span>
                    </div>
                    @if($invoice->cancelled_at)
                    <div class="flex justify-between">
                        <span class="text-red-500">Cancelada em</span>
                        <span class="text-red-500">{{ $invoice->cancelled_at->format('d/m/Y H:i') }}</span>
                    </div>
                    @endif
                </div>
            </x-ui.card>
        </div>
    </div>
</x-app-layout>
