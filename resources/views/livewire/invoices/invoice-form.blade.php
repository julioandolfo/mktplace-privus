<div>
    <form wire:submit="save">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Main content (2/3) --}}
            <div class="lg:col-span-2 space-y-6">
                {{-- Link to order --}}
                <x-ui.card title="Vincular Pedido">
                    <div class="space-y-4">
                        @if($order_id)
                            @php $linkedOrder = \App\Models\Order::find($order_id); @endphp
                            @if($linkedOrder)
                            <div class="flex items-center justify-between p-3 rounded-lg border border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800/50">
                                <div>
                                    <span class="font-mono font-medium text-primary-600 dark:text-primary-400">{{ $linkedOrder->order_number }}</span>
                                    <span class="text-gray-500 dark:text-zinc-400 ml-2">{{ $linkedOrder->customer_name }}</span>
                                    <span class="text-gray-500 dark:text-zinc-400 ml-2">R$ {{ number_format($linkedOrder->total, 2, ',', '.') }}</span>
                                </div>
                                <button type="button" wire:click="$set('order_id', null)" class="text-red-500 hover:text-red-700">
                                    <x-heroicon-o-x-mark class="w-4 h-4" />
                                </button>
                            </div>
                            @endif
                        @else
                            <div class="relative">
                                <div class="relative">
                                    <x-heroicon-o-magnifying-glass class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-zinc-500" />
                                    <input type="text" wire:model.live.debounce.300ms="orderSearch" placeholder="Buscar pedido por numero ou cliente..."
                                           class="form-input pl-10">
                                </div>

                                @if(count($orderResults) > 0)
                                <div class="absolute z-10 mt-1 w-full bg-white dark:bg-zinc-800 rounded-lg shadow-lg border border-gray-200 dark:border-zinc-700 max-h-48 overflow-y-auto">
                                    @foreach($orderResults as $result)
                                    <button type="button" wire:click="selectOrder({{ $result['id'] }})"
                                            class="w-full flex items-center justify-between px-3 py-2 text-sm hover:bg-gray-100 dark:hover:bg-zinc-700">
                                        <span class="font-mono">{{ $result['order_number'] }} - {{ $result['customer_name'] }}</span>
                                        <span class="text-gray-500 dark:text-zinc-400">R$ {{ number_format($result['total'], 2, ',', '.') }}</span>
                                    </button>
                                    @endforeach
                                </div>
                                @endif
                            </div>
                            <p class="text-xs text-gray-500 dark:text-zinc-400">Opcional. Vincule a um pedido para preencher dados automaticamente.</p>
                        @endif
                    </div>
                </x-ui.card>

                {{-- Customer --}}
                <x-ui.card title="Destinatario">
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="customer_name" class="form-label">Nome / Razao Social *</label>
                                <input type="text" id="customer_name" wire:model="customer_name" class="form-input" required>
                                @error('customer_name') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="customer_document" class="form-label">CPF / CNPJ</label>
                                <input type="text" id="customer_document" wire:model="customer_document" class="form-input" maxlength="18">
                            </div>
                        </div>
                    </div>
                </x-ui.card>

                {{-- Customer address --}}
                <x-ui.card title="Endereco do Destinatario">
                    <div class="space-y-4">
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label for="customer_zipcode" class="form-label">CEP</label>
                                <input type="text" id="customer_zipcode" wire:model="customer_zipcode" class="form-input" maxlength="9">
                            </div>
                            <div class="col-span-2">
                                <label for="customer_street" class="form-label">Rua</label>
                                <input type="text" id="customer_street" wire:model="customer_street" class="form-input">
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label for="customer_number" class="form-label">Numero</label>
                                <input type="text" id="customer_number" wire:model="customer_number" class="form-input">
                            </div>
                            <div>
                                <label for="customer_complement" class="form-label">Complemento</label>
                                <input type="text" id="customer_complement" wire:model="customer_complement" class="form-input">
                            </div>
                            <div>
                                <label for="customer_neighborhood" class="form-label">Bairro</label>
                                <input type="text" id="customer_neighborhood" wire:model="customer_neighborhood" class="form-input">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="customer_city" class="form-label">Cidade</label>
                                <input type="text" id="customer_city" wire:model="customer_city" class="form-input">
                            </div>
                            <div>
                                <label for="customer_state" class="form-label">Estado</label>
                                <select id="customer_state" wire:model="customer_state" class="form-input">
                                    <option value="">Selecione</option>
                                    @foreach(['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'] as $uf)
                                    <option value="{{ $uf }}">{{ $uf }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </x-ui.card>

                {{-- Values --}}
                <x-ui.card title="Valores">
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <label for="total_products" class="form-label">Produtos (R$) *</label>
                            <input type="number" step="0.01" id="total_products" wire:model.live="total_products" class="form-input" min="0" required>
                            @error('total_products') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="total_shipping" class="form-label">Frete (R$)</label>
                            <input type="number" step="0.01" id="total_shipping" wire:model.live="total_shipping" class="form-input" min="0">
                        </div>
                        <div>
                            <label for="total_discount" class="form-label">Desconto (R$)</label>
                            <input type="number" step="0.01" id="total_discount" wire:model.live="total_discount" class="form-input" min="0">
                        </div>
                        <div>
                            <label for="total_tax" class="form-label">Impostos (R$)</label>
                            <input type="number" step="0.01" id="total_tax" wire:model.live="total_tax" class="form-input" min="0">
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-gray-200 dark:border-zinc-700">
                        <div class="flex justify-end">
                            <div class="text-right">
                                <span class="text-sm text-gray-500 dark:text-zinc-400">Total da Nota:</span>
                                <span class="text-lg font-bold ml-2">R$ {{ number_format($this->total, 2, ',', '.') }}</span>
                            </div>
                        </div>
                    </div>
                </x-ui.card>
            </div>

            {{-- Sidebar (1/3) --}}
            <div class="space-y-6">
                {{-- Invoice config --}}
                <x-ui.card title="Configuracao">
                    <div class="space-y-4">
                        <div>
                            <label for="type" class="form-label">Tipo</label>
                            <select id="type" wire:model="type" class="form-input">
                                <option value="nfe">NF-e</option>
                                <option value="nfce">NFC-e</option>
                            </select>
                        </div>
                        <div>
                            <label for="series" class="form-label">Serie</label>
                            <input type="text" id="series" wire:model="series" class="form-input" maxlength="3">
                        </div>
                        <div>
                            <label for="nature_operation" class="form-label">Natureza da Operacao</label>
                            <input type="text" id="nature_operation" wire:model="nature_operation" class="form-input">
                            @error('nature_operation') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </x-ui.card>

                {{-- Emitter --}}
                <x-ui.card title="Emitente">
                    <div>
                        <label for="company_id" class="form-label">Empresa</label>
                        <select id="company_id" wire:model="company_id" class="form-input">
                            <option value="">Selecione</option>
                            @foreach($companies as $company)
                                <option value="{{ $company->id }}">{{ $company->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </x-ui.card>
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex items-center justify-end gap-3 mt-6">
            <a href="{{ route('invoices.index') }}" class="btn-secondary">Cancelar</a>
            <button type="submit" class="btn-primary" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="save">
                    <x-heroicon-s-check class="w-4 h-4" />
                    {{ $invoice ? 'Salvar Alteracoes' : 'Criar Nota Fiscal' }}
                </span>
                <span wire:loading wire:target="save">Salvando...</span>
            </button>
        </div>
    </form>
</div>
