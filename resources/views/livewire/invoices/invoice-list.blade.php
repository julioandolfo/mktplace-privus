<div>
    {{-- Filters --}}
    <div class="card p-4 mb-6">
        <div class="flex flex-col sm:flex-row gap-4">
            <div class="flex-1">
                <div class="relative">
                    <x-heroicon-o-magnifying-glass class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-zinc-500" />
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Buscar por numero, chave de acesso ou cliente..."
                           class="form-input pl-10">
                </div>
            </div>
            <div class="flex gap-3">
                <select wire:model.live="status" class="form-input w-44">
                    <option value="">Todos Status</option>
                    @foreach($statuses as $s)
                        <option value="{{ $s->value }}">{{ $s->label() }}</option>
                    @endforeach
                </select>
                <select wire:model.live="type" class="form-input w-36">
                    <option value="">Todos Tipos</option>
                    <option value="nfe">NF-e</option>
                    <option value="nfce">NFC-e</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Table --}}
    @if($invoices->isEmpty())
        <x-ui.card>
            <x-ui.empty-state
                title="Nenhuma nota fiscal encontrada"
                description="{{ $search ? 'Tente refinar sua busca.' : 'Crie sua primeira nota fiscal para comecar.' }}">
                <x-slot name="icon">
                    <x-heroicon-o-document-text class="w-8 h-8 text-gray-400 dark:text-zinc-500" />
                </x-slot>
                @unless($search)
                <x-slot name="action">
                    <a href="{{ route('invoices.create') }}" class="btn-primary">
                        <x-heroicon-s-plus class="w-4 h-4" />
                        Nova Nota Fiscal
                    </a>
                </x-slot>
                @endunless
            </x-ui.empty-state>
        </x-ui.card>
    @else
        <x-ui.card :padding="false">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>
                            <button wire:click="sortBy('number')" class="flex items-center gap-1 hover:text-gray-700 dark:hover:text-zinc-200">
                                Numero
                                @if($sortField === 'number')
                                    <x-heroicon-s-chevron-up class="w-3 h-3 {{ $sortDirection === 'desc' ? 'rotate-180' : '' }}" />
                                @endif
                            </button>
                        </th>
                        <th>Tipo</th>
                        <th>Destinatario</th>
                        <th>Pedido</th>
                        <th>
                            <button wire:click="sortBy('total')" class="flex items-center gap-1 hover:text-gray-700 dark:hover:text-zinc-200">
                                Total
                                @if($sortField === 'total')
                                    <x-heroicon-s-chevron-up class="w-3 h-3 {{ $sortDirection === 'desc' ? 'rotate-180' : '' }}" />
                                @endif
                            </button>
                        </th>
                        <th>Status</th>
                        <th>
                            <button wire:click="sortBy('created_at')" class="flex items-center gap-1 hover:text-gray-700 dark:hover:text-zinc-200">
                                Data
                                @if($sortField === 'created_at')
                                    <x-heroicon-s-chevron-up class="w-3 h-3 {{ $sortDirection === 'desc' ? 'rotate-180' : '' }}" />
                                @endif
                            </button>
                        </th>
                        <th class="text-right">Acoes</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoices as $invoice)
                    <tr wire:key="invoice-{{ $invoice->id }}">
                        <td>
                            <a href="{{ route('invoices.show', $invoice) }}" class="font-mono font-medium text-primary-600 dark:text-primary-400 hover:underline">
                                {{ $invoice->number ?? 'Pendente' }}
                            </a>
                            @if($invoice->series)
                            <span class="text-xs text-gray-400 dark:text-zinc-500">Serie {{ $invoice->series }}</span>
                            @endif
                        </td>
                        <td>
                            <x-ui.badge color="info">{{ strtoupper($invoice->type) }}</x-ui.badge>
                        </td>
                        <td>
                            <span class="font-medium text-gray-900 dark:text-white">{{ $invoice->customer_name }}</span>
                            @if($invoice->customer_document)
                            <p class="text-xs text-gray-500 dark:text-zinc-400">{{ $invoice->customer_document }}</p>
                            @endif
                        </td>
                        <td>
                            @if($invoice->order)
                            <a href="{{ route('orders.show', $invoice->order) }}" class="font-mono text-sm text-primary-600 dark:text-primary-400 hover:underline">
                                {{ $invoice->order->order_number }}
                            </a>
                            @else
                            <span class="text-gray-400 dark:text-zinc-500">-</span>
                            @endif
                        </td>
                        <td class="font-medium">R$ {{ number_format($invoice->total, 2, ',', '.') }}</td>
                        <td>
                            <x-ui.badge :color="$invoice->status->color()">{{ $invoice->status->label() }}</x-ui.badge>
                        </td>
                        <td class="text-sm text-gray-500 dark:text-zinc-400">
                            {{ $invoice->created_at->format('d/m/Y') }}
                        </td>
                        <td class="text-right">
                            <div class="flex items-center justify-end gap-1" x-data="{ open: false }">
                                <a href="{{ route('invoices.show', $invoice) }}" class="btn-ghost btn-xs">
                                    <x-heroicon-o-eye class="w-4 h-4" />
                                </a>
                                <div class="relative">
                                    <button @click="open = !open" class="btn-ghost btn-xs">
                                        <x-heroicon-o-ellipsis-vertical class="w-4 h-4" />
                                    </button>
                                    <div x-show="open" @click.away="open = false" x-transition
                                         class="absolute right-0 mt-1 w-40 bg-white dark:bg-zinc-800 rounded-lg shadow-lg border border-gray-200 dark:border-zinc-700 py-1 z-10">
                                        @if($invoice->pdf_url)
                                        <a href="{{ $invoice->pdf_url }}" target="_blank" @click="open = false"
                                           class="w-full flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-zinc-300 hover:bg-gray-100 dark:hover:bg-zinc-700">
                                            <x-heroicon-o-arrow-down-tray class="w-4 h-4" />
                                            Download PDF
                                        </a>
                                        @endif
                                        @if($invoice->xml_url)
                                        <a href="{{ $invoice->xml_url }}" target="_blank" @click="open = false"
                                           class="w-full flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-zinc-300 hover:bg-gray-100 dark:hover:bg-zinc-700">
                                            <x-heroicon-o-code-bracket class="w-4 h-4" />
                                            Download XML
                                        </a>
                                        @endif
                                        @if($invoice->status->value !== 'approved')
                                        <button wire:click="deleteInvoice({{ $invoice->id }})" wire:confirm="Tem certeza que deseja remover esta nota fiscal?" @click="open = false"
                                                class="w-full flex items-center gap-2 px-3 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-gray-100 dark:hover:bg-zinc-700">
                                            <x-heroicon-o-trash class="w-4 h-4" />
                                            Remover
                                        </button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            @if($invoices->hasPages())
            <div class="px-4 py-3 border-t border-gray-200 dark:border-zinc-700">
                {{ $invoices->links() }}
            </div>
            @endif
        </x-ui.card>
    @endif
</div>
