<div>
    @if($loading)
        <div class="animate-pulse flex items-center gap-2 p-3">
            <div class="h-4 w-32 bg-gray-200 dark:bg-zinc-700 rounded"></div>
        </div>
    @elseif(count($balances) === 0)
        {{-- No active ME accounts, hide widget --}}
    @else
        @if($mode === 'compact')
            {{-- Compact: horizontal badges for expedition/dashboard --}}
            <div class="flex flex-wrap items-center gap-3">
                @foreach($balances as $b)
                <div class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border
                    {{ ($b['balance'] ?? 0) > 0
                        ? 'bg-emerald-50 dark:bg-emerald-900/20 border-emerald-200 dark:border-emerald-700'
                        : 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-700' }}">
                    <x-heroicon-o-wallet class="w-4 h-4 {{ ($b['balance'] ?? 0) > 0 ? 'text-emerald-500' : 'text-red-500' }}" />
                    <div class="text-xs">
                        <span class="font-medium text-gray-600 dark:text-zinc-300">{{ $b['name'] }}</span>
                        @if($b['environment'] === 'sandbox')
                            <span class="text-amber-500 text-[10px]">(SB)</span>
                        @endif
                    </div>
                    @if(isset($b['error']))
                        <span class="text-xs text-red-500">{{ $b['error'] }}</span>
                    @else
                        <span class="font-bold text-sm {{ ($b['balance'] ?? 0) > 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-700 dark:text-red-400' }}">
                            R$ {{ number_format($b['balance'], 2, ',', '.') }}
                        </span>
                    @endif
                    <button wire:click="openAddBalance({{ $b['id'] }}, '{{ addslashes($b['name']) }}')"
                            class="ml-1 p-1 rounded hover:bg-white/50 dark:hover:bg-zinc-700/50 transition"
                            title="Inserir saldo">
                        <x-heroicon-o-plus-circle class="w-4 h-4 text-gray-500 dark:text-zinc-400" />
                    </button>
                </div>
                @endforeach
                <button wire:click="fetchBalances" class="p-1.5 rounded hover:bg-gray-100 dark:hover:bg-zinc-700 transition" title="Atualizar saldo">
                    <x-heroicon-o-arrow-path class="w-4 h-4 text-gray-400 {{ $loading ? 'animate-spin' : '' }}" />
                </button>
            </div>
        @else
            {{-- Full: cards for settings page --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach($balances as $b)
                <div class="card p-4">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-2">
                            <x-heroicon-o-wallet class="w-5 h-5 {{ ($b['balance'] ?? 0) > 0 ? 'text-emerald-500' : 'text-red-500' }}" />
                            <span class="font-medium text-gray-700 dark:text-zinc-200">{{ $b['name'] }}</span>
                            @if($b['environment'] === 'sandbox')
                                <x-ui.badge color="warning">Sandbox</x-ui.badge>
                            @endif
                        </div>
                        <button wire:click="fetchBalances" class="p-1 rounded hover:bg-gray-100 dark:hover:bg-zinc-700 transition" title="Atualizar">
                            <x-heroicon-o-arrow-path class="w-4 h-4 text-gray-400" />
                        </button>
                    </div>
                    @if(isset($b['error']))
                        <p class="text-sm text-red-500">{{ $b['error'] }}</p>
                    @else
                        <p class="text-3xl font-bold {{ ($b['balance'] ?? 0) > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                            R$ {{ number_format($b['balance'], 2, ',', '.') }}
                        </p>
                        @if(($b['balance'] ?? 0) < 10)
                            <p class="text-xs text-amber-600 dark:text-amber-400 mt-1">
                                <x-heroicon-o-exclamation-triangle class="w-3.5 h-3.5 inline" />
                                Saldo baixo! Adicione créditos para continuar enviando.
                            </p>
                        @endif
                    @endif
                    <button wire:click="openAddBalance({{ $b['id'] }}, '{{ addslashes($b['name']) }}')"
                            class="mt-3 btn-primary btn-sm w-full">
                        <x-heroicon-o-plus-circle class="w-4 h-4" />
                        Inserir Saldo
                    </button>
                </div>
                @endforeach
            </div>
        @endif
    @endif

    {{-- Modal: Inserir Saldo --}}
    @if($showAddBalanceModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" wire:click.self="closeAddBalance">
        <div class="bg-white dark:bg-zinc-900 rounded-xl shadow-xl max-w-md w-full mx-4 p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Inserir Saldo — {{ $addBalanceAccountName }}
                </h3>
                <button wire:click="closeAddBalance" class="p-1 rounded hover:bg-gray-100 dark:hover:bg-zinc-700">
                    <x-heroicon-o-x-mark class="w-5 h-5 text-gray-400" />
                </button>
            </div>

            @if($paymentLink)
                <div class="text-center py-4">
                    <x-heroicon-o-check-circle class="w-12 h-12 text-green-500 mx-auto mb-3" />
                    <p class="text-sm text-gray-600 dark:text-zinc-300 mb-4">
                        Solicitação criada! Realize o pagamento para creditar o saldo.
                    </p>
                    <a href="{{ $paymentLink }}" target="_blank" rel="noopener"
                       class="btn-primary w-full justify-center">
                        <x-heroicon-o-arrow-top-right-on-square class="w-4 h-4" />
                        Abrir Pagamento
                    </a>
                    <button wire:click="closeAddBalance" class="btn-secondary w-full mt-2">
                        Fechar
                    </button>
                </div>
            @else
                <div class="space-y-4">
                    <div>
                        <label class="form-label">Valor (R$)</label>
                        <input type="number" wire:model="addBalanceValue" min="1" max="50000" step="0.01"
                               class="form-input" placeholder="50.00">
                        <p class="text-xs text-gray-400 mt-1">Mínimo R$ 1,00 — Máximo R$ 50.000,00 por transação</p>
                        @error('addBalanceValue') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Método de Pagamento</label>
                        <div class="grid grid-cols-2 gap-3 mt-1">
                            <label class="relative flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition
                                {{ $addBalanceMethod === 'pix' ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20' : 'border-gray-200 dark:border-zinc-700 hover:border-gray-300' }}">
                                <input type="radio" wire:model="addBalanceMethod" value="pix" class="sr-only">
                                <div class="text-center w-full">
                                    <svg class="w-6 h-6 mx-auto mb-1 {{ $addBalanceMethod === 'pix' ? 'text-primary-600' : 'text-gray-400' }}" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M17.24 14.34l-3.59-3.59a.92.92 0 00-1.3 0l-3.59 3.59a.92.92 0 000 1.3l3.59 3.59a.92.92 0 001.3 0l3.59-3.59a.92.92 0 000-1.3zM12 2L2 12l10 10 10-10L12 2z"/>
                                    </svg>
                                    <span class="text-sm font-medium {{ $addBalanceMethod === 'pix' ? 'text-primary-700 dark:text-primary-300' : 'text-gray-600 dark:text-zinc-400' }}">PIX</span>
                                </div>
                            </label>
                            <label class="relative flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition
                                {{ $addBalanceMethod === 'boleto' ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20' : 'border-gray-200 dark:border-zinc-700 hover:border-gray-300' }}">
                                <input type="radio" wire:model="addBalanceMethod" value="boleto" class="sr-only">
                                <div class="text-center w-full">
                                    <x-heroicon-o-document-text class="w-6 h-6 mx-auto mb-1 {{ $addBalanceMethod === 'boleto' ? 'text-primary-600' : 'text-gray-400' }}" />
                                    <span class="text-sm font-medium {{ $addBalanceMethod === 'boleto' ? 'text-primary-700 dark:text-primary-300' : 'text-gray-600 dark:text-zinc-400' }}">Boleto</span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 p-3 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700">
                        <x-heroicon-o-information-circle class="w-4 h-4 text-blue-500 flex-shrink-0" />
                        <p class="text-xs text-blue-700 dark:text-blue-300">
                            @if($addBalanceMethod === 'pix')
                                O QR Code PIX será gerado para pagamento imediato. O saldo é creditado em poucos minutos.
                            @else
                                O boleto será gerado para pagamento. O saldo é creditado em até 3 dias úteis.
                            @endif
                        </p>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button wire:click="closeAddBalance" class="btn-secondary flex-1">Cancelar</button>
                        <button wire:click="submitAddBalance" class="btn-primary flex-1"
                                wire:loading.attr="disabled" wire:loading.class="opacity-50">
                            <span wire:loading.remove wire:target="submitAddBalance">
                                <x-heroicon-o-banknotes class="w-4 h-4" />
                                Gerar {{ $addBalanceMethod === 'pix' ? 'PIX' : 'Boleto' }}
                            </span>
                            <span wire:loading wire:target="submitAddBalance">Processando...</span>
                        </button>
                    </div>
                </div>
            @endif
        </div>
    </div>
    @endif
</div>
