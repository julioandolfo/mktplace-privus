<div class="min-h-screen bg-gray-50 dark:bg-zinc-900" x-data="{}"
     x-on:scan-success.window="document.getElementById('scan-sound-ok')?.play()"
     x-on:scan-error.window="document.getElementById('scan-sound-err')?.play()">

    {{-- Sons de feedback (beep via AudioContext) --}}
    <audio id="scan-sound-ok" src="" preload="auto"></audio>
    <audio id="scan-sound-err" src="" preload="auto"></audio>

    {{-- Header --}}
    <div class="bg-white dark:bg-zinc-800 border-b border-gray-200 dark:border-zinc-700 px-4 py-3">
        <div class="max-w-7xl mx-auto flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ route('expedition.index') }}" class="btn-ghost btn-sm">
                    <x-heroicon-o-arrow-left class="w-4 h-4" />
                </a>
                <div>
                    <h1 class="font-bold text-gray-900 dark:text-white">Conferência de Embalagem</h1>
                    <p class="text-sm text-gray-500 dark:text-zinc-400">
                        Pedido {{ $order->order_number }} — {{ $order->customer_name }}
                    </p>
                </div>
            </div>

            {{-- Progresso global --}}
            <div class="flex items-center gap-3">
                <div class="text-right">
                    <p class="text-2xl font-bold {{ $this->isAllScanned ? 'text-green-600 dark:text-green-400' : 'text-gray-900 dark:text-white' }}">
                        {{ $this->totalScanned }}/{{ $this->totalNeeded }}
                    </p>
                    <p class="text-xs text-gray-500">itens conferidos</p>
                </div>

                @if($this->isAllScanned)
                <button wire:click="completePacking"
                        class="btn-primary">
                    <x-heroicon-s-check-circle class="w-4 h-4" />
                    Setar como Embalado
                </button>
                @endif
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 py-6">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- ESQUERDO — Campo de scan + Feedback --}}
            <div class="space-y-4">

                {{-- Input scanner --}}
                <div class="card p-4">
                    <label class="form-label">Bipagem (EAN / SKU)</label>
                    <div class="relative">
                        <x-heroicon-o-qr-code class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                        <input type="text"
                               wire:model="scanInput"
                               wire:keydown.enter="scan"
                               id="scan-input"
                               autofocus
                               autocomplete="off"
                               placeholder="Bipe o código ou digite e pressione Enter..."
                               class="form-input pl-10 text-lg font-mono">
                    </div>
                    <p class="text-xs text-gray-400 dark:text-zinc-500 mt-1">
                        Compatível com leitores USB/Bluetooth (simulam teclado)
                    </p>
                </div>

                {{-- Feedback de scan --}}
                @if($scanMessage)
                <div class="card p-4 {{ $scanStatus === 'success' ? 'border-green-400 bg-green-50 dark:bg-green-900/20' : ($scanStatus === 'error' ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : 'border-amber-400 bg-amber-50 dark:bg-amber-900/20') }}">
                    <div class="flex items-center gap-2">
                        @if($scanStatus === 'success')
                            <x-heroicon-s-check-circle class="w-5 h-5 text-green-600 dark:text-green-400 flex-shrink-0" />
                            <span class="font-medium text-green-800 dark:text-green-300">{{ $scanMessage }}</span>
                        @elseif($scanStatus === 'error')
                            <x-heroicon-s-x-circle class="w-5 h-5 text-red-600 dark:text-red-400 flex-shrink-0" />
                            <span class="font-medium text-red-800 dark:text-red-300">{{ $scanMessage }}</span>
                        @else
                            <x-heroicon-s-exclamation-triangle class="w-5 h-5 text-amber-600 dark:text-amber-400 flex-shrink-0" />
                            <span class="font-medium text-amber-800 dark:text-amber-300">{{ $scanMessage }}</span>
                        @endif
                    </div>
                </div>
                @endif

                {{-- Histórico de scans --}}
                @if(count($scanHistory) > 0)
                <div class="card p-4">
                    <h3 class="font-semibold text-gray-700 dark:text-zinc-300 text-sm mb-3">
                        Histórico desta sessão
                    </h3>
                    <div class="space-y-2 max-h-48 overflow-y-auto">
                        @foreach($scanHistory as $entry)
                        <div class="flex items-center justify-between text-sm">
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-xs text-gray-400">{{ $entry['time'] }}</span>
                                <span class="text-gray-700 dark:text-zinc-300 truncate max-w-[200px]">{{ $entry['name'] }}</span>
                            </div>
                            <span class="text-xs font-medium {{ $entry['qty'] >= $entry['of'] ? 'text-green-600 dark:text-green-400' : 'text-amber-600 dark:text-amber-400' }}">
                                {{ $entry['qty'] }}/{{ $entry['of'] }}
                            </span>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

            </div>

            {{-- DIREITO — Lista de itens do pedido --}}
            <div class="space-y-3">
                @foreach($order->items as $item)
                @php
                    $scanned = $scannedItems[$item->id] ?? 0;
                    $needed  = $item->pending_quantity;
                    $isDone  = $scanned >= $needed;
                    $hasEan  = $item->product?->ean_gtin || $item->variant?->ean_gtin;
                @endphp
                <div class="card p-4 {{ $isDone ? 'border-green-400 dark:border-green-600' : '' }}">
                    <div class="flex items-start gap-3">

                        {{-- Artwork em destaque --}}
                        @if($item->has_artwork)
                        <div class="flex-shrink-0">
                            <img src="{{ $item->artwork_url }}" alt="Arte"
                                 class="w-20 h-20 rounded-lg object-cover border-2 border-purple-400">
                            <p class="text-xs text-center text-purple-600 dark:text-purple-400 mt-1 font-medium">ARTE</p>
                        </div>
                        @elseif($item->product?->primaryImage)
                        <div class="flex-shrink-0">
                            <img src="{{ $item->product->primaryImage->url }}" alt="{{ $item->name }}"
                                 class="w-16 h-16 rounded-lg object-cover">
                        </div>
                        @else
                        <div class="w-16 h-16 rounded-lg bg-gray-100 dark:bg-zinc-700 flex items-center justify-center flex-shrink-0">
                            <x-heroicon-o-photo class="w-6 h-6 text-gray-400" />
                        </div>
                        @endif

                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-gray-900 dark:text-white">{{ $item->name }}</p>
                            <p class="text-xs text-gray-500 dark:text-zinc-400">
                                SKU: {{ $item->sku ?? '—' }}
                                @if($hasEan) · EAN: {{ $item->variant?->ean_gtin ?? $item->product?->ean_gtin }} @endif
                            </p>

                            @if(! $hasEan)
                            <p class="text-xs text-amber-600 dark:text-amber-400 mt-1">
                                Sem EAN/SKU — confirme manualmente
                            </p>
                            @endif
                        </div>

                        {{-- Contador e ação --}}
                        <div class="flex-shrink-0 text-right">
                            <div class="text-2xl font-bold {{ $isDone ? 'text-green-600 dark:text-green-400' : 'text-gray-900 dark:text-white' }}">
                                {{ $scanned }}/{{ $needed }}
                            </div>
                            @if($isDone)
                                <x-heroicon-s-check-circle class="w-6 h-6 text-green-500 ml-auto mt-1" />
                            @elseif(! $hasEan)
                                <button wire:click="manualCheck({{ $item->id }})"
                                        class="btn-secondary btn-xs mt-2">
                                    Confirmar
                                </button>
                            @endif
                        </div>
                    </div>

                    {{-- Barra de progresso --}}
                    <div class="mt-3 bg-gray-200 dark:bg-zinc-700 rounded-full h-1.5">
                        <div class="{{ $isDone ? 'bg-green-500' : 'bg-primary-500' }} h-1.5 rounded-full transition-all"
                             style="width: {{ $needed > 0 ? min(100, round(($scanned / $needed) * 100)) : 100 }}%"></div>
                    </div>
                </div>
                @endforeach

                {{-- Botão forçar conclusão --}}
                @if(! $this->isAllScanned)
                <button wire:click="completePacking"
                        wire:confirm="Marcar como embalado mesmo com itens não conferidos?"
                        class="w-full btn-secondary">
                    Forçar Conclusão (embalado sem conferência total)
                </button>
                @endif
            </div>

        </div>
    </div>

    {{-- Re-foca input após cada scan --}}
    <script>
        document.addEventListener('livewire:updated', function() {
            const input = document.getElementById('scan-input');
            if (input) input.focus();
        });
        document.getElementById('scan-input')?.focus();
    </script>
</div>
