<div>
    {{-- Contadores --}}
    @php
        $tabs = [
            ['key' => 'pending',   'label' => 'Pendentes',  'color' => 'text-amber-600 dark:text-amber-400',   'bg' => 'bg-amber-50 dark:bg-amber-900/20'],
            ['key' => 'purchased', 'label' => 'Comprados',  'color' => 'text-emerald-600 dark:text-emerald-400', 'bg' => 'bg-emerald-50 dark:bg-emerald-900/20'],
            ['key' => 'cancelled', 'label' => 'Cancelados', 'color' => 'text-gray-500 dark:text-zinc-400',     'bg' => 'bg-gray-50 dark:bg-zinc-800'],
            ['key' => '',          'label' => 'Todos',      'color' => 'text-gray-600 dark:text-zinc-300',     'bg' => 'bg-gray-50 dark:bg-zinc-800'],
        ];
    @endphp
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-4">
        @foreach($tabs as $tab)
        <button wire:click="$set('filterStatus', '{{ $tab['key'] }}')"
                class="card p-3 text-center transition {{ $filterStatus === $tab['key'] ? 'ring-2 ring-primary-500' : '' }}">
            <p class="text-2xl font-bold {{ $tab['color'] }}">
                @if($tab['key'])
                    {{ $counts[$tab['key']] ?? 0 }}
                @else
                    {{ array_sum($counts) }}
                @endif
            </p>
            <p class="text-xs text-gray-500 dark:text-zinc-400">{{ $tab['label'] }}</p>
        </button>
        @endforeach
    </div>

    {{-- Toolbar --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3 mb-4">
        <input type="text" wire:model.live.debounce.300ms="search" class="form-input w-full sm:w-64" placeholder="Buscar pedido ou título...">
        <div class="flex-1"></div>
        <button wire:click="openNewForm" class="btn-primary">
            <x-heroicon-o-plus class="w-4 h-4" /> Nova Solicitação
        </button>
    </div>

    {{-- Lista --}}
    <x-ui.card :padding="false">
        @if($requests->isEmpty())
            <div class="text-center py-10">
                <x-heroicon-o-shopping-cart class="w-10 h-10 text-gray-300 dark:text-zinc-600 mx-auto mb-2" />
                <p class="text-gray-500 dark:text-zinc-400">Nenhuma solicitação de compra encontrada.</p>
            </div>
        @else
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Título</th>
                        <th>Pedido</th>
                        <th>Itens</th>
                        <th>Custo</th>
                        <th>Fornecedor</th>
                        <th class="text-center">Status</th>
                        <th class="text-right">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($requests as $pr)
                    <tr>
                        <td>
                            <button wire:click="openDetail({{ $pr->id }})" class="font-semibold text-gray-900 dark:text-white hover:text-primary-600 dark:hover:text-primary-400 text-left">
                                {{ $pr->title ?: 'Solicitação #' . $pr->id }}
                            </button>
                            <p class="text-xs text-gray-400 dark:text-zinc-500">{{ $pr->created_at->format('d/m/Y H:i') }}</p>
                        </td>
                        <td>
                            @if($pr->order)
                                <a href="{{ route('orders.show', $pr->order_id) }}" class="text-sm text-primary-600 dark:text-primary-400 hover:underline">
                                    #{{ $pr->order->order_number }}
                                </a>
                            @else
                                <span class="text-gray-300 dark:text-zinc-600">Manual</span>
                            @endif
                        </td>
                        <td>
                            <span class="text-sm">{{ $pr->items->count() }} item(ns)</span>
                        </td>
                        <td>
                            <span class="text-sm font-medium">{{ $pr->total_cost_formatted }}</span>
                        </td>
                        <td>
                            @if($pr->supplier)
                                <span class="text-sm text-gray-700 dark:text-zinc-300">{{ $pr->supplier->name }}</span>
                            @else
                                <span class="text-gray-300 dark:text-zinc-600">—</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if($pr->status === 'pending')
                                <span class="inline-flex items-center text-xs font-bold px-2 py-0.5 rounded-full bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400">Pendente</span>
                            @elseif($pr->status === 'purchased')
                                <span class="inline-flex items-center text-xs font-bold px-2 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400">Comprado</span>
                            @elseif($pr->status === 'cancelled')
                                <span class="inline-flex items-center text-xs font-bold px-2 py-0.5 rounded-full bg-gray-100 dark:bg-zinc-700 text-gray-500 dark:text-zinc-400">Cancelado</span>
                            @endif
                        </td>
                        <td class="text-right">
                            <div class="flex items-center justify-end gap-1">
                                @if($pr->status === 'pending')
                                    <button wire:click="openPurchaseForm({{ $pr->id }})" class="btn-ghost btn-xs text-emerald-600" title="Marcar como comprado">
                                        <x-heroicon-o-check-circle class="w-4 h-4" />
                                    </button>
                                    <button wire:click="cancelRequest({{ $pr->id }})" wire:confirm="Cancelar solicitação?" class="btn-ghost btn-xs text-red-500" title="Cancelar">
                                        <x-heroicon-o-x-circle class="w-4 h-4" />
                                    </button>
                                @elseif($pr->status === 'purchased')
                                    <button wire:click="reopenRequest({{ $pr->id }})" class="btn-ghost btn-xs text-amber-600" title="Reabrir">
                                        <x-heroicon-o-arrow-path class="w-4 h-4" />
                                    </button>
                                @endif
                                <button wire:click="openDetail({{ $pr->id }})" class="btn-ghost btn-xs" title="Ver detalhes">
                                    <x-heroicon-o-eye class="w-3.5 h-3.5" />
                                </button>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="px-4 py-3">
                {{ $requests->links() }}
            </div>
        @endif
    </x-ui.card>

    {{-- ═══════════════════════════════════════════════════════════════
         MODAL: Nova Solicitação Manual
    ═══════════════════════════════════════════════════════════════ --}}
    @if($showNewForm)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 overflow-y-auto py-8"
         @keydown.escape.window="$wire.set('showNewForm', false)">
        <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-xl w-full max-w-2xl mx-4 p-6"
             @click.outside="$wire.set('showNewForm', false)">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Nova Solicitação de Compra</h3>

            <div class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Título *</label>
                        <input type="text" wire:model="newTitle" class="form-input" placeholder="Ex: Compra de insumos, Tecido para pedido X...">
                        @error('newTitle') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="form-label">Pedido <span class="text-gray-400 font-normal">(opcional)</span></label>
                        <select wire:model="newOrderId" class="form-input">
                            <option value="">— Sem pedido vinculado —</option>
                            @foreach($recentOrders as $o)
                                <option value="{{ $o->id }}">#{{ $o->order_number }} — {{ $o->customer_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label class="form-label mb-2">Itens</label>
                    <div class="space-y-3">
                        @foreach($newItems as $i => $item)
                        <div class="flex items-start gap-2 bg-gray-50 dark:bg-zinc-700/50 rounded-lg p-3">
                            <div class="flex-1 grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <div class="sm:col-span-2">
                                    <select wire:model.live="newItems.{{ $i }}.product_id" class="form-input text-sm">
                                        <option value="">— Produto (opcional) —</option>
                                        @foreach($products as $p)
                                            <option value="{{ $p->id }}">{{ $p->sku ? "[$p->sku] " : '' }}{{ $p->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="sm:col-span-2">
                                    <input type="text" wire:model="newItems.{{ $i }}.description" class="form-input text-sm" placeholder="Descrição *">
                                    @error("newItems.{$i}.description") <p class="form-error">{{ $message }}</p> @enderror
                                </div>
                                <div class="flex gap-2">
                                    <div class="w-20">
                                        <input type="number" wire:model="newItems.{{ $i }}.quantity" class="form-input text-sm" min="1" placeholder="Qtd">
                                    </div>
                                    <div class="flex-1">
                                        <input type="number" step="0.01" wire:model="newItems.{{ $i }}.unit_cost" class="form-input text-sm" placeholder="Custo R$">
                                    </div>
                                </div>
                                <div>
                                    <input type="url" wire:model="newItems.{{ $i }}.link" class="form-input text-sm" placeholder="Link (opcional)">
                                </div>
                            </div>
                            @if(count($newItems) > 1)
                            <button wire:click="removeNewItem({{ $i }})" class="text-red-400 hover:text-red-600 mt-1">
                                <x-heroicon-o-x-mark class="w-4 h-4" />
                            </button>
                            @endif
                        </div>
                        @endforeach
                    </div>
                    <button wire:click="addNewItem" class="btn-ghost text-sm mt-2">
                        <x-heroicon-o-plus class="w-3.5 h-3.5" /> Adicionar item
                    </button>
                </div>

                <div>
                    <label class="form-label">Observações</label>
                    <textarea wire:model="newNotes" rows="2" class="form-input" placeholder="Notas adicionais..."></textarea>
                </div>
            </div>

            <div class="flex justify-end gap-3 mt-6">
                <button wire:click="$set('showNewForm', false)" class="btn-secondary">Cancelar</button>
                <button wire:click="saveNewRequest" class="btn-primary">Criar Solicitação</button>
            </div>
        </div>
    </div>
    @endif

    {{-- ═══════════════════════════════════════════════════════════════
         MODAL: Marcar como comprado
    ═══════════════════════════════════════════════════════════════ --}}
    @if($showPurchaseForm)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
         @keydown.escape.window="$wire.set('showPurchaseForm', false)">
        <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-xl w-full max-w-sm mx-4 p-6"
             @click.outside="$wire.set('showPurchaseForm', false)">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Confirmar Compra</h3>

            <div class="space-y-4">
                <div>
                    <label class="form-label">Fornecedor *</label>
                    <select wire:model="purchaseSupplierId" class="form-input">
                        <option value="">Selecione...</option>
                        @foreach($suppliers as $s)
                            <option value="{{ $s->id }}">{{ $s->name }}</option>
                        @endforeach
                    </select>
                    @error('purchaseSupplierId') <p class="form-error">{{ $message }}</p> @enderror
                    @if($suppliers->isEmpty())
                        <p class="text-xs text-amber-600 dark:text-amber-400 mt-1">
                            Nenhum fornecedor cadastrado.
                            <a href="{{ route('purchases.suppliers') }}" class="underline">Cadastre um</a>.
                        </p>
                    @endif
                </div>
            </div>

            <div class="flex justify-end gap-3 mt-6">
                <button wire:click="$set('showPurchaseForm', false)" class="btn-secondary">Cancelar</button>
                <button wire:click="confirmPurchase" class="btn-primary">Confirmar Compra</button>
            </div>
        </div>
    </div>
    @endif

    {{-- ═══════════════════════════════════════════════════════════════
         MODAL: Detalhes
    ═══════════════════════════════════════════════════════════════ --}}
    @if($showDetail && $detailRequest)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 overflow-y-auto py-8"
         @keydown.escape.window="$wire.set('showDetail', false)">
        <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-xl w-full max-w-2xl mx-4 p-6"
             @click.outside="$wire.set('showDetail', false)">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ $detailRequest->title ?: 'Solicitação #' . $detailRequest->id }}
                    </h3>
                    <p class="text-sm text-gray-500 dark:text-zinc-400">
                        Criada em {{ $detailRequest->created_at->format('d/m/Y H:i') }}
                        @if($detailRequest->order)
                            — Pedido
                            <a href="{{ route('orders.show', $detailRequest->order_id) }}" class="text-primary-600 dark:text-primary-400 hover:underline">
                                #{{ $detailRequest->order->order_number }}
                            </a>
                        @endif
                    </p>
                </div>
                <button wire:click="$set('showDetail', false)" class="text-gray-400 hover:text-gray-600">
                    <x-heroicon-o-x-mark class="w-5 h-5" />
                </button>
            </div>

            @if($detailRequest->status === 'purchased')
            <div class="flex items-center gap-3 bg-emerald-50 dark:bg-emerald-900/10 border border-emerald-200 dark:border-emerald-800 rounded-lg px-4 py-2 mb-4">
                <x-heroicon-s-check-circle class="w-5 h-5 text-emerald-500" />
                <div class="text-sm">
                    <span class="font-semibold text-emerald-700 dark:text-emerald-400">Comprado</span>
                    @if($detailRequest->supplier)
                        — {{ $detailRequest->supplier->name }}
                    @endif
                    @if($detailRequest->purchased_at)
                        em {{ $detailRequest->purchased_at->format('d/m/Y H:i') }}
                    @endif
                    @if($detailRequest->purchasedByUser)
                        por {{ $detailRequest->purchasedByUser->name }}
                    @endif
                </div>
            </div>
            @endif

            <table class="data-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th class="text-right">Qtd</th>
                        <th class="text-right">Custo Un.</th>
                        <th class="text-right">Total</th>
                        <th>Link</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($detailRequest->items as $item)
                    <tr>
                        <td>
                            <span class="font-medium text-gray-900 dark:text-white">{{ $item->description }}</span>
                            @if($item->product)
                                <span class="text-xs text-gray-400">({{ $item->product->sku }})</span>
                            @endif
                            @if($item->notes)
                                <p class="text-xs text-gray-400 mt-0.5">{{ $item->notes }}</p>
                            @endif
                        </td>
                        <td class="text-right">{{ $item->quantity }}</td>
                        <td class="text-right text-sm">{{ $item->unit_cost_formatted }}</td>
                        <td class="text-right text-sm font-medium">R$ {{ number_format($item->total_cost_cents / 100, 2, ',', '.') }}</td>
                        <td>
                            @if($item->link)
                                <a href="{{ $item->link }}" target="_blank" class="text-xs text-primary-600 dark:text-primary-400 hover:underline">Link</a>
                            @else
                                <span class="text-gray-300 dark:text-zinc-600">—</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-gray-200 dark:border-zinc-600">
                        <td colspan="3" class="text-right font-semibold">Total</td>
                        <td class="text-right font-bold text-gray-900 dark:text-white">{{ $detailRequest->total_cost_formatted }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>

            @if($detailRequest->notes)
            <div class="mt-4 text-sm text-gray-600 dark:text-zinc-300">
                <strong>Obs:</strong> {{ $detailRequest->notes }}
            </div>
            @endif
        </div>
    </div>
    @endif
</div>
