<div class="space-y-6">

    {{-- Contadores --}}
    <div class="grid grid-cols-2 gap-4">
        <button wire:click="$set('filterStatus', 'awaiting_production')"
                class="card p-4 text-left transition-all {{ $filterStatus === 'awaiting_production' ? 'ring-2 ring-amber-400' : '' }}">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Aguardando Produção</p>
                    <p class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-1">{{ $counts['awaiting_production'] }}</p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center">
                    <x-heroicon-o-clock class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                </div>
            </div>
        </button>
        <button wire:click="$set('filterStatus', 'in_production')"
                class="card p-4 text-left transition-all {{ $filterStatus === 'in_production' ? 'ring-2 ring-purple-400' : '' }}">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Em Produção</p>
                    <p class="text-2xl font-bold text-purple-600 dark:text-purple-400 mt-1">{{ $counts['in_production'] }}</p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center">
                    <x-heroicon-o-cog-8-tooth class="w-5 h-5 text-purple-600 dark:text-purple-400" />
                </div>
            </div>
        </button>
    </div>

    {{-- Filtros --}}
    <div class="flex items-center gap-3">
        <div class="relative flex-1">
            <x-heroicon-o-magnifying-glass class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
            <input type="text" wire:model.live.debounce.400ms="search"
                   placeholder="Buscar pedido, cliente, SKU..."
                   class="form-input pl-9">
        </div>
        @if($filterStatus)
        <button wire:click="$set('filterStatus', '')" class="btn-secondary btn-sm">
            <x-heroicon-o-x-mark class="w-4 h-4" />
            Todos
        </button>
        @endif
    </div>

    {{-- Cards de pedidos --}}
    @if($orders->isEmpty())
    <div class="card p-10 text-center">
        <x-heroicon-o-cog-8-tooth class="w-12 h-12 text-gray-300 dark:text-zinc-600 mx-auto mb-3" />
        <p class="text-gray-500 dark:text-zinc-400">Nenhum pedido em produção no momento.</p>
    </div>
    @else

    <div class="space-y-4">
        @foreach($orders as $order)
        @php
            $pipeline = $order->pipeline_status;
            $allDone  = $order->items->every(fn ($i) =>
                $i->production_status->value === 'complete' ||
                $i->production_status->value === 'not_required'
            );
        @endphp
        <div class="card overflow-hidden">
            {{-- Cabeçalho do card --}}
            <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100 dark:border-zinc-700
                        {{ $pipeline->value === 'awaiting_production' ? 'bg-amber-50 dark:bg-amber-900/10' : 'bg-purple-50 dark:bg-purple-900/10' }}">
                <div class="flex items-center gap-3">
                    <a href="{{ route('orders.show', $order) }}"
                       class="font-mono font-bold text-primary-600 dark:text-primary-400 hover:underline">
                        {{ $order->order_number }}
                    </a>
                    <x-ui.badge :color="$pipeline->color()">{{ $pipeline->label() }}</x-ui.badge>
                    @if($order->marketplaceAccount)
                        <x-ui.badge color="default" class="text-xs">
                            {{ $order->marketplaceAccount->account_name }}
                        </x-ui.badge>
                    @endif
                </div>

                <div class="flex items-center gap-2">
                    @if($pipeline->value === 'awaiting_production')
                    <button wire:click="startProduction({{ $order->id }})"
                            wire:loading.attr="disabled"
                            class="btn-secondary btn-sm">
                        <x-heroicon-o-play class="w-4 h-4" />
                        Iniciar Produção
                    </button>
                    @endif

                    @if(!$allDone)
                    <button wire:click="completeProduction({{ $order->id }})"
                            wire:loading.attr="disabled"
                            wire:confirm="Marcar todos os itens como concluídos e mover para expedição?"
                            class="btn-primary btn-sm">
                        <x-heroicon-o-check-circle class="w-4 h-4" />
                        Concluir Produção
                    </button>
                    @else
                    <span class="flex items-center gap-1.5 text-sm text-green-600 dark:text-green-400 font-medium">
                        <x-heroicon-s-check-circle class="w-4 h-4" />
                        Pronto para expedição
                    </span>
                    @endif
                </div>
            </div>

            {{-- Info do pedido --}}
            <div class="flex items-center gap-6 px-5 py-2 border-b border-gray-50 dark:border-zinc-800 bg-white dark:bg-zinc-800 text-sm text-gray-600 dark:text-zinc-400">
                <span>
                    <x-heroicon-o-user class="w-3.5 h-3.5 inline mr-1" />
                    {{ $order->customer_name }}
                </span>
                @if($order->paid_at)
                <span>
                    <x-heroicon-o-calendar class="w-3.5 h-3.5 inline mr-1" />
                    Pago {{ $order->paid_at->format('d/m/Y') }}
                </span>
                @endif
                @php $deadline = $order->meta['ml_shipping_deadline'] ?? null; @endphp
                @if($deadline)
                <span class="{{ \Carbon\Carbon::parse($deadline)->isPast() ? 'text-red-500 font-medium' : '' }}">
                    <x-heroicon-o-clock class="w-3.5 h-3.5 inline mr-1" />
                    Despachar até {{ \Carbon\Carbon::parse($deadline)->format('d/m H:i') }}
                </span>
                @endif
            </div>

            {{-- Itens com produção + artwork --}}
            <div class="divide-y divide-gray-100 dark:divide-zinc-700/50">
                @foreach($order->items as $item)
                @php
                    $prodStatus   = $item->production_status;
                    $needsProd    = $prodStatus->value !== 'not_required';
                    $hasArtwork   = ! empty($item->artwork_url);
                    $artApproved  = $item->artwork_approved;
                @endphp
                <div class="flex items-start gap-4 px-5 py-4
                            {{ $prodStatus->value === 'complete' ? 'bg-green-50/40 dark:bg-green-900/5' : '' }}">

                    {{-- ARTWORK — destaque visual --}}
                    <div class="flex-shrink-0" x-data="{ showArtModal: false }">
                        @if($hasArtwork)
                        <button @click="showArtModal = true" class="relative group">
                            <img src="{{ $item->artwork_url }}" alt="Arte"
                                 class="w-20 h-20 object-cover rounded-lg border-2
                                        {{ $artApproved ? 'border-green-400' : 'border-purple-400' }}
                                        group-hover:opacity-90 transition-opacity" />
                            <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                                <x-heroicon-s-magnifying-glass-plus class="w-6 h-6 text-white drop-shadow" />
                            </div>
                            @if($artApproved)
                            <div class="absolute -top-1 -right-1 w-5 h-5 bg-green-500 rounded-full flex items-center justify-center">
                                <x-heroicon-s-check class="w-3 h-3 text-white" />
                            </div>
                            @endif
                        </button>

                        {{-- Modal da arte expandida --}}
                        <div x-show="showArtModal" x-cloak
                             @click.self="showArtModal = false"
                             @keydown.escape.window="showArtModal = false"
                             class="fixed inset-0 z-50 flex items-center justify-center bg-black/70">
                            <div class="relative max-w-3xl max-h-[90vh] p-2">
                                <img src="{{ $item->artwork_url }}" alt="Arte completa"
                                     class="max-w-full max-h-[85vh] object-contain rounded-xl shadow-2xl" />
                                <button @click="showArtModal = false"
                                        class="absolute top-4 right-4 w-8 h-8 bg-black/50 rounded-full flex items-center justify-center text-white">
                                    <x-heroicon-o-x-mark class="w-5 h-5" />
                                </button>
                                <div class="absolute bottom-4 left-1/2 -translate-x-1/2 flex gap-2">
                                    <button wire:click="toggleArtworkApproved({{ $item->id }})"
                                            @click="showArtModal = false"
                                            class="btn-sm {{ $artApproved ? 'btn-secondary' : 'bg-green-600 text-white border-green-600' }} text-xs px-3">
                                        {{ $artApproved ? 'Desaprovar arte' : '✓ Aprovar arte' }}
                                    </button>
                                </div>
                            </div>
                        </div>
                        @else
                        {{-- Sem arte: área de input --}}
                        <div x-data="{ editing: false, artUrl: '' }"
                             class="w-20 h-20 rounded-lg border-2 border-dashed border-gray-300 dark:border-zinc-600
                                    flex flex-col items-center justify-center gap-1 text-gray-400 cursor-pointer
                                    hover:border-purple-400 transition-colors"
                             @click="editing = true">
                            <x-heroicon-o-photo class="w-6 h-6" />
                            <span class="text-[10px] text-center leading-tight">Definir<br>arte</span>

                            <div x-show="editing" x-cloak @click.stop
                                 class="fixed inset-0 z-40 flex items-center justify-center bg-black/50"
                                 @keydown.escape.window="editing = false">
                                <div class="bg-white dark:bg-zinc-800 rounded-xl p-5 w-80 shadow-xl">
                                    <p class="font-medium mb-3 text-sm">URL da arte/mockup</p>
                                    <input type="url" x-model="artUrl"
                                           placeholder="https://..." class="form-input mb-3 text-sm">
                                    <div class="flex gap-2 justify-end">
                                        <button @click="editing = false" class="btn-secondary btn-sm">Cancelar</button>
                                        <button @click="$wire.saveArtwork({{ $item->id }}, artUrl); editing = false"
                                                class="btn-primary btn-sm">Salvar</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>

                    {{-- Detalhes do item --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="font-medium text-gray-900 dark:text-white leading-tight">
                                    {{ $item->name }}
                                </p>
                                @if($item->sku)
                                <p class="text-xs font-mono text-gray-400 dark:text-zinc-500">{{ $item->sku }}</p>
                                @endif
                                <p class="text-xs text-gray-500 dark:text-zinc-400 mt-0.5">
                                    Qtd: <strong>{{ $item->quantity }}</strong>
                                    @if($needsProd && $item->production_notes)
                                        · <span class="italic">{{ $item->production_notes }}</span>
                                    @endif
                                </p>
                            </div>

                            {{-- Status e controles por item --}}
                            @if($needsProd)
                            <div class="flex items-center gap-2 flex-shrink-0">
                                <select wire:change="updateItemStatus({{ $item->id }}, $event.target.value)"
                                        class="form-input text-xs py-1 h-auto">
                                    @foreach(\App\Enums\ProductionStatus::cases() as $ps)
                                    <option value="{{ $ps->value }}"
                                            {{ $prodStatus->value === $ps->value ? 'selected' : '' }}>
                                        {{ $ps->label() }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            @else
                            <x-ui.badge color="default" class="text-xs flex-shrink-0">Não requer prod.</x-ui.badge>
                            @endif
                        </div>

                        {{-- Barra de progresso de produção --}}
                        @if($needsProd)
                        <div class="mt-2 flex items-center gap-2">
                            @php
                                $pct = match($prodStatus->value) {
                                    'pending'     => 0,
                                    'in_progress' => 50,
                                    'complete'    => 100,
                                    default       => 0,
                                };
                                $barColor = match($prodStatus->value) {
                                    'complete'    => 'bg-green-500',
                                    'in_progress' => 'bg-purple-500',
                                    default       => 'bg-gray-300 dark:bg-zinc-600',
                                };
                            @endphp
                            <div class="flex-1 h-1.5 bg-gray-200 dark:bg-zinc-700 rounded-full overflow-hidden">
                                <div class="{{ $barColor }} h-1.5 rounded-full transition-all"
                                     style="width: {{ $pct }}%"></div>
                            </div>
                            <span class="text-xs text-gray-400 dark:text-zinc-500 flex-shrink-0">{{ $prodStatus->label() }}</span>
                        </div>
                        @endif

                        {{-- Aviso arte não aprovada --}}
                        @if($hasArtwork && !$artApproved && $prodStatus->value === 'in_progress')
                        <p class="mt-1 text-xs text-amber-600 dark:text-amber-400 flex items-center gap-1">
                            <x-heroicon-o-exclamation-triangle class="w-3.5 h-3.5" />
                            Arte não aprovada — clique na imagem para revisar
                        </p>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endforeach
    </div>

    {{ $orders->links() }}
    @endif

</div>
