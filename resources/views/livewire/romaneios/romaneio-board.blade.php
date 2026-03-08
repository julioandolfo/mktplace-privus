<div class="min-h-screen bg-gray-50 dark:bg-zinc-900"
     x-on:scan-success.window="playBeep(880, 150)"
     x-on:scan-error.window="playBeep(220, 400)"
     x-on:scan-warning.window="playBeep(440, 300)"
     x-on:scan-info.window="playBeep(660, 150)">

    {{-- Sound engine via AudioContext --}}
    <script>
        function playBeep(freq, dur) {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.connect(gain); gain.connect(ctx.destination);
                osc.frequency.value = freq; osc.type = 'sine';
                gain.gain.setValueAtTime(0.3, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + dur / 1000);
                osc.start(ctx.currentTime); osc.stop(ctx.currentTime + dur / 1000);
            } catch(e) {}
        }
    </script>

    {{-- HEADER --}}
    <div class="bg-white dark:bg-zinc-800 border-b border-gray-200 dark:border-zinc-700 px-4 py-3 sticky top-0 z-10">
        <div class="max-w-7xl mx-auto flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('romaneios.show', $romaneio) }}" class="btn-ghost btn-sm">
                    <x-heroicon-o-arrow-left class="w-4 h-4" />
                </a>
                <div>
                    <h1 class="font-bold text-gray-900 dark:text-white">{{ $romaneio->name }}</h1>
                    <p class="text-xs text-gray-500 dark:text-zinc-400">Tela de Bipagem — Romaneio #{{ $romaneio->id }}</p>
                </div>
            </div>

            {{-- Contadores globais --}}
            <div class="flex items-center gap-4 text-center">
                <div>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $pedidosCompletos }}/{{ $totalPedidos }}</p>
                    <p class="text-xs text-gray-500">pedidos</p>
                </div>
                <div class="text-gray-300 dark:text-zinc-600 text-xl">·</div>
                <div>
                    <p class="text-2xl font-bold {{ $bipados === $totalVolumes ? 'text-green-600 dark:text-green-400' : 'text-gray-900 dark:text-white' }}">
                        {{ $bipados }}/{{ $totalVolumes }}
                    </p>
                    <p class="text-xs text-gray-500">volumes</p>
                </div>

                @if($pedidosCompletos === $totalPedidos && $totalPedidos > 0)
                <button wire:click="$set('showCloseModal', true)"
                        class="btn-primary animate-pulse">
                    <x-heroicon-s-check-circle class="w-4 h-4" />
                    Fechar Romaneio
                </button>
                @else
                <button wire:click="$set('showCloseModal', true)" class="btn-secondary">
                    Fechar Romaneio
                </button>
                @endif
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 py-6">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- ESQUERDO — Scanner + Feedback --}}
            <div class="space-y-4">

                {{-- Input scanner --}}
                <div class="card p-5">
                    <label class="block text-sm font-semibold text-gray-700 dark:text-zinc-300 mb-2">
                        Bipagem (QR code da etiqueta ou número do pedido)
                    </label>
                    <div class="relative">
                        <x-heroicon-o-qr-code class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                        <input type="text"
                               wire:model="scanInput"
                               wire:keydown.enter="scan"
                               id="scan-input"
                               autofocus
                               autocomplete="off"
                               placeholder="Bipe o QR code ou digit o número do pedido..."
                               class="form-input pl-10 text-xl font-mono h-14">
                    </div>
                    <p class="text-xs text-gray-400 mt-2">
                        Aceita: QR code da etiqueta de caixa · número do pedido (ex: PED20260308001) · modo 1 e modo 2
                    </p>
                </div>

                {{-- Feedback --}}
                @if($scanMessage)
                <div class="card p-4 border-l-4 {{ match($scanStatus) {
                    'success' => 'border-green-400 bg-green-50 dark:bg-green-900/20',
                    'error'   => 'border-red-400 bg-red-50 dark:bg-red-900/20',
                    'warning' => 'border-amber-400 bg-amber-50 dark:bg-amber-900/20',
                    'info'    => 'border-blue-400 bg-blue-50 dark:bg-blue-900/20',
                    default   => 'border-gray-200',
                } }}">
                    <p class="font-medium text-sm {{ match($scanStatus) {
                        'success' => 'text-green-800 dark:text-green-300',
                        'error'   => 'text-red-800 dark:text-red-300',
                        'warning' => 'text-amber-800 dark:text-amber-300',
                        'info'    => 'text-blue-800 dark:text-blue-300',
                        default   => 'text-gray-700',
                    } }}">{{ $scanMessage }}</p>
                </div>
                @endif

                {{-- Histórico de scans --}}
                @if(count($scanHistory) > 0)
                <div class="card p-4">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-zinc-300 mb-3">Últimas bipagens</h3>
                    <div class="space-y-1.5 max-h-60 overflow-y-auto">
                        @foreach($scanHistory as $i => $entry)
                        <div class="flex items-center gap-3 text-sm {{ $i === 0 ? 'font-medium' : 'opacity-60' }}">
                            <span class="font-mono text-xs text-gray-400 flex-shrink-0">{{ $entry['time'] }}</span>
                            <span class="text-gray-700 dark:text-zinc-300 truncate">{{ $entry['message'] }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

            </div>

            {{-- DIREITO — Lista de pedidos no romaneio --}}
            <div class="space-y-3 max-h-[calc(100vh-200px)] overflow-y-auto pr-1">
                @foreach($romaneio->items->sortByDesc(fn($i) => $i->isComplete()) as $item)
                @php
                    $order    = $item->order;
                    $complete = $item->isComplete();
                @endphp
                <div class="card p-4 {{ $complete ? 'border-green-400 dark:border-green-600 bg-green-50/30 dark:bg-green-900/10' : '' }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <a href="{{ route('orders.show', $order) }}"
                                   class="font-mono font-semibold text-primary-600 dark:text-primary-400 hover:underline text-sm">
                                    {{ $order->order_number }}
                                </a>
                                @if($complete)
                                    <x-heroicon-s-check-circle class="w-4 h-4 text-green-500 flex-shrink-0" />
                                @endif
                            </div>
                            <p class="text-sm text-gray-600 dark:text-zinc-400 truncate">{{ $order->customer_name }}</p>
                            @if($order->shipping_address)
                                @php $addr = $order->shipping_address; @endphp
                                <p class="text-xs text-gray-400 dark:text-zinc-500">
                                    {{ $addr['city'] ?? '' }}{{ !empty($addr['state']) ? '/' . $addr['state'] : '' }}
                                </p>
                            @endif
                        </div>

                        {{-- Progresso volumes --}}
                        <div class="flex-shrink-0 text-right">
                            <p class="text-2xl font-bold {{ $complete ? 'text-green-600 dark:text-green-400' : 'text-gray-900 dark:text-white' }}">
                                {{ $item->volumes_scanned }}/{{ $item->volumes }}
                            </p>
                            <p class="text-xs text-gray-400">volumes</p>
                        </div>
                    </div>

                    {{-- Barra de progresso --}}
                    <div class="mt-3 bg-gray-200 dark:bg-zinc-700 rounded-full h-2">
                        <div class="{{ $complete ? 'bg-green-500' : 'bg-primary-500' }} h-2 rounded-full transition-all"
                             style="width: {{ $item->progress_percent }}%"></div>
                    </div>

                    {{-- Itens incluídos (resumo) --}}
                    @if($item->items_detail)
                    <p class="text-xs text-gray-400 dark:text-zinc-500 mt-2">
                        {{ collect($item->items_detail)->sum('quantity') }} itens neste despacho
                    </p>
                    @endif
                </div>
                @endforeach

                @if($romaneio->items->isEmpty())
                <div class="card p-6 text-center">
                    <x-heroicon-o-inbox class="w-8 h-8 text-gray-300 dark:text-zinc-600 mx-auto mb-2" />
                    <p class="text-sm text-gray-500 dark:text-zinc-400">
                        Romaneio vazio — bipe pedidos para adicioná-los (Modo 2)
                    </p>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- MODAL — Fechar Romaneio --}}
    @if($showCloseModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60">
        <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-2xl w-full max-w-md mx-4 p-6">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2">Fechar Romaneio</h3>

            @if($pedidosCompletos < $totalPedidos)
            <div class="flex items-start gap-2 p-3 mb-4 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700">
                <x-heroicon-s-exclamation-triangle class="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" />
                <div class="text-sm text-amber-700 dark:text-amber-300">
                    <strong>{{ $totalPedidos - $pedidosCompletos }} pedido(s)</strong> ainda possuem volumes não bipados.
                    Ao fechar, todos serão marcados como despachados mesmo assim.
                </div>
            </div>
            @endif

            <div class="bg-gray-50 dark:bg-zinc-700/50 rounded-lg p-4 mb-6 space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-gray-600 dark:text-zinc-400">Total de pedidos</span>
                    <strong>{{ $totalPedidos }}</strong>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600 dark:text-zinc-400">Pedidos concluídos</span>
                    <strong class="text-green-600">{{ $pedidosCompletos }}</strong>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600 dark:text-zinc-400">Total de volumes</span>
                    <strong>{{ $totalVolumes }}</strong>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600 dark:text-zinc-400">Volumes bipados</span>
                    <strong>{{ $bipados }}</strong>
                </div>
            </div>

            <p class="text-sm text-gray-600 dark:text-zinc-400 mb-5">
                Ao fechar, todos os pedidos serão marcados como <strong>Despachados</strong>
                no sistema e os marketplaces configurados serão atualizados.
            </p>

            <div class="flex justify-end gap-3">
                <button wire:click="$set('showCloseModal', false)" class="btn-secondary">
                    Cancelar
                </button>
                <button wire:click="closeRomaneio"
                        wire:loading.attr="disabled"
                        class="btn-primary bg-green-600 hover:bg-green-700 border-green-600">
                    <span wire:loading.remove wire:target="closeRomaneio">
                        <x-heroicon-s-check class="w-4 h-4" />
                        Fechar e Marcar Despachado
                    </span>
                    <span wire:loading wire:target="closeRomaneio">Fechando...</span>
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Auto-foco no input após updates --}}
    <script>
        document.addEventListener('livewire:updated', () => {
            document.getElementById('scan-input')?.focus();
        });
        document.getElementById('scan-input')?.focus();
    </script>
</div>
