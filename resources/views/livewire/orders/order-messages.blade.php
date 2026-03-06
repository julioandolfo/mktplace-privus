<div wire:poll.30000ms="refreshMessages" class="space-y-4">

    {{-- Header com contador --}}
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Mensagens</h3>
            @if($unreadCount > 0)
            <span class="inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-white bg-red-500 rounded-full">
                {{ $unreadCount }}
            </span>
            @endif
        </div>
        <button wire:click="refreshMessages" wire:loading.attr="disabled"
            class="text-xs text-gray-400 dark:text-zinc-500 hover:text-gray-600 dark:hover:text-zinc-300 flex items-center gap-1 transition-colors">
            <x-heroicon-o-arrow-path class="w-3.5 h-3.5" wire:loading.class="animate-spin" />
            Atualizar
        </button>
    </div>

    {{-- Thread de mensagens --}}
    @if($messages->isEmpty())
    <div class="text-center py-8 text-sm text-gray-400 dark:text-zinc-500">
        <x-heroicon-o-chat-bubble-left-right class="w-8 h-8 mx-auto mb-2 opacity-40" />
        <p>Nenhuma mensagem ainda.</p>
        <p class="text-xs mt-1">O comprador deve iniciar a conversa.</p>
    </div>
    @else
    <div class="space-y-3 max-h-96 overflow-y-auto pr-1" id="messages-thread">
        @foreach($messages as $msg)
        @php
            $isSent = $msg->is_sent;
            $isModerated = $msg->is_moderated;
        @endphp
        <div class="flex {{ $isSent ? 'justify-end' : 'justify-start' }}">
            <div class="max-w-[80%] {{ $isSent
                ? 'bg-primary-600 text-white rounded-2xl rounded-br-sm'
                : 'bg-gray-100 dark:bg-zinc-800 text-gray-900 dark:text-white rounded-2xl rounded-bl-sm'
            }} px-4 py-2.5 text-sm">

                @if($isModerated)
                <p class="text-xs italic opacity-60 mb-1">
                    <x-heroicon-o-shield-exclamation class="w-3 h-3 inline" />
                    Mensagem moderada pelo ML
                </p>
                @endif

                <p class="leading-relaxed break-words">{{ $msg->text ?: '(sem conteúdo)' }}</p>

                <div class="flex items-center gap-1.5 mt-1 {{ $isSent ? 'justify-end' : 'justify-start' }}">
                    <span class="text-[11px] opacity-60">{{ $msg->created_at_formatted }}</span>
                    @if($isSent)
                        @if($msg->is_read)
                        <x-heroicon-s-check-circle class="w-3 h-3 opacity-60" />
                        @else
                        <x-heroicon-o-check-circle class="w-3 h-3 opacity-60" />
                        @endif
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Campo de resposta --}}
    @if($canReply)
    <div class="border-t border-gray-100 dark:border-zinc-800 pt-4">

        @if($sendError)
        <div class="mb-3 flex items-center gap-2 text-xs text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 rounded-lg px-3 py-2">
            <x-heroicon-o-x-circle class="w-4 h-4 flex-shrink-0" />
            {{ $sendError }}
        </div>
        @endif

        @if($sendSuccess)
        <div class="mb-3 flex items-center gap-2 text-xs text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/20 rounded-lg px-3 py-2">
            <x-heroicon-o-check-circle class="w-4 h-4 flex-shrink-0" />
            {{ $sendSuccess }}
        </div>
        @endif

        <div class="flex gap-2">
            <div class="flex-1 relative">
                <textarea
                    wire:model="newMessage"
                    rows="2"
                    maxlength="350"
                    placeholder="Responder ao comprador..."
                    class="form-input w-full text-sm resize-none pr-12"
                    @keydown.ctrl.enter="$wire.sendMessage()"
                ></textarea>
                <span class="absolute bottom-2 right-2 text-[10px] {{ strlen($newMessage) > 300 ? 'text-red-400' : 'text-gray-400 dark:text-zinc-600' }}">
                    {{ strlen($newMessage) }}/350
                </span>
            </div>
            <button
                wire:click="sendMessage"
                wire:loading.attr="disabled"
                :disabled="$wire.newMessage.length === 0"
                class="btn-primary btn-sm self-end flex-shrink-0 disabled:opacity-50"
                title="Enviar (Ctrl+Enter)">
                <span wire:loading.remove wire:target="sendMessage">
                    <x-heroicon-o-paper-airplane class="w-4 h-4" />
                </span>
                <span wire:loading wire:target="sendMessage">
                    <x-heroicon-o-arrow-path class="w-4 h-4 animate-spin" />
                </span>
            </button>
        </div>
        <p class="text-xs text-gray-400 dark:text-zinc-600 mt-1.5">
            Ctrl+Enter para enviar. Máx. 350 caracteres.
            O comprador deve iniciar a conversa — não é possível abrir novas conversas.
        </p>
    </div>
    @else
    <div class="border-t border-gray-100 dark:border-zinc-800 pt-3">
        <p class="text-xs text-gray-400 dark:text-zinc-500 text-center">
            @if(in_array($order->status->value, ['cancelled', 'returned']))
                Mensagens desabilitadas para pedidos cancelados/devolvidos.
            @else
                Aguardando o comprador iniciar a conversa.
            @endif
        </p>
    </div>
    @endif

    <script>
        // Auto-scroll to bottom when messages load
        document.addEventListener('livewire:updated', () => {
            const thread = document.getElementById('messages-thread');
            if (thread) {
                thread.scrollTop = thread.scrollHeight;
            }
        });
    </script>
</div>
