{{--
    Partial: menu ⋮ de ações da expedição
    Variáveis recebidas:
      $order         — Order model
      $mlStep        — int|null (1..4)
      $genStep       — int|null (1..2)
      $isMl          — bool
      $mlShippingId  — string|null
      $isShipped     — bool
--}}
@php
    $isShipped ??= false;
    $mlStep    ??= null;
    $genStep   ??= null;
@endphp

<div x-data="{
        open: false,
        top: 0, left: 0,
        toggle() {
            this.open = !this.open;
            if (this.open) {
                const r = this.$refs.btn.getBoundingClientRect();
                this.top  = r.bottom + window.scrollY + 4;
                this.left = r.right  + window.scrollX - 224;
            }
        }
    }">
    <button x-ref="btn" @click="toggle()" class="btn-ghost btn-xs px-1" title="Mais ações">
        <x-heroicon-o-ellipsis-vertical class="w-4 h-4" />
    </button>
    <template x-teleport="body">
        <div x-show="open" x-cloak
             @click.outside="open = false"
             @keydown.escape.window="open = false"
             x-transition:enter="transition ease-out duration-100"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             :style="`position:fixed;top:${top}px;left:${left}px;z-index:9999;`"
             class="w-56 bg-white dark:bg-zinc-800 rounded-lg shadow-2xl border border-gray-100 dark:border-zinc-700 py-1 text-left">

            {{-- ── Etiqueta de Volume (sempre disponível) ── --}}
            <a href="{{ route('romaneios.etiquetas-avulso', ['orders' => $order->id]) }}"
               target="_blank"
               @click="open = false"
               class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-zinc-300 hover:bg-gray-50 dark:hover:bg-zinc-700">
                <x-heroicon-o-document-text class="w-4 h-4 text-gray-400" />
                Etiqueta de Volume
            </a>

            {{-- ── Conferir embalagem (modal) ── --}}
            @if(!$isShipped)
            <button wire:click="openPackingModal({{ $order->id }})"
                    @click="open = false"
                    class="w-full flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-zinc-300 hover:bg-gray-50 dark:hover:bg-zinc-700">
                <x-heroicon-o-clipboard-document-check class="w-4 h-4 text-gray-400" />
                Conferir embalagem
            </button>
            @endif

            {{-- ── Marcar Embalado (rápido, sem conferência) ── --}}
            @if(!$isShipped)
            <button wire:click="markPacked({{ $order->id }})"
                    wire:confirm="Marcar {{ $order->order_number }} como embalado sem conferência?"
                    @click="open = false"
                    class="w-full flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-zinc-300 hover:bg-gray-50 dark:hover:bg-zinc-700">
                <x-heroicon-o-archive-box class="w-4 h-4 text-gray-400" />
                Marcar Embalado
            </button>
            @endif

            {{-- ── Marcar Enviado (disponível quando embalado, mesmo sem NF-e para genéricos) ── --}}
            @if(!$isShipped)
            <button wire:click="markShipped({{ $order->id }})"
                    wire:confirm="Marcar {{ $order->order_number }} como enviado?"
                    @click="open = false"
                    class="w-full flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-zinc-300 hover:bg-gray-50 dark:hover:bg-zinc-700">
                <x-heroicon-o-truck class="w-4 h-4 text-gray-400" />
                Marcar Enviado
            </button>
            @endif

            {{-- ── Etiqueta ML (quando disponível) ── --}}
            @if($isMl && $mlShippingId)
            <a href="{{ route('orders.ml-label', $order) }}"
               target="_blank"
               @click="open = false"
               class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-zinc-300 hover:bg-gray-50 dark:hover:bg-zinc-700">
                <x-heroicon-o-tag class="w-4 h-4 text-amber-500" />
                Etiqueta ML (Correios)
            </a>
            @endif

            {{-- ── Re-abrir para Re-envio (disponível para pedidos enviados) ── --}}
            @if($isShipped)
            <div class="my-1 border-t border-gray-100 dark:border-zinc-700"></div>
            <button wire:click="revertToReadyToShip({{ $order->id }})"
                    wire:confirm="Reabrir {{ $order->order_number }} para re-envio? O status voltará para Embalado."
                    @click="open = false"
                    class="w-full flex items-center gap-2 px-3 py-2 text-sm text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/20">
                <x-heroicon-o-arrow-uturn-left class="w-4 h-4" />
                Re-abrir para Re-envio
            </button>
            @endif

            {{-- ── Divisor + Ver Pedido (sempre) ── --}}
            <div class="my-1 border-t border-gray-100 dark:border-zinc-700"></div>
            <a href="{{ route('orders.show', $order) }}"
               @click="open = false"
               class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-zinc-300 hover:bg-gray-50 dark:hover:bg-zinc-700">
                <x-heroicon-o-eye class="w-4 h-4 text-gray-400" />
                Ver Pedido
            </a>

        </div>
    </template>
</div>
