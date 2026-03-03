<div>
    <form wire:submit="save">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Main content (2/3) --}}
            <div class="lg:col-span-2 space-y-6">
                {{-- Customer info --}}
                <x-ui.card title="Dados do Cliente">
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="customer_name" class="form-label">Nome *</label>
                                <input type="text" id="customer_name" wire:model="customer_name" class="form-input" required>
                                @error('customer_name') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="customer_document" class="form-label">CPF / CNPJ</label>
                                <input type="text" id="customer_document" wire:model="customer_document" class="form-input" maxlength="18">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="customer_email" class="form-label">E-mail</label>
                                <input type="email" id="customer_email" wire:model="customer_email" class="form-input">
                                @error('customer_email') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="customer_phone" class="form-label">Telefone</label>
                                <input type="text" id="customer_phone" wire:model="customer_phone" class="form-input" maxlength="20">
                            </div>
                        </div>
                    </div>
                </x-ui.card>

                {{-- Items --}}
                <x-ui.card title="Itens do Pedido">
                    <div class="space-y-4">
                        {{-- Product search --}}
                        <div class="relative">
                            <label class="form-label">Adicionar Produto</label>
                            <div class="relative">
                                <x-heroicon-o-magnifying-glass class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-zinc-500" />
                                <input type="text" wire:model.live.debounce.300ms="productSearch" placeholder="Buscar produto por nome ou SKU..."
                                       class="form-input pl-10">
                            </div>

                            @if(count($productResults) > 0)
                            <div class="absolute z-10 mt-1 w-full bg-white dark:bg-zinc-800 rounded-lg shadow-lg border border-gray-200 dark:border-zinc-700 max-h-64 overflow-y-auto">
                                @foreach($productResults as $product)
                                <div class="border-b border-gray-100 dark:border-zinc-700 last:border-0">
                                    @if(count($product['variants']) > 0)
                                        <div class="px-3 py-2 text-xs font-medium text-gray-500 dark:text-zinc-400 bg-gray-50 dark:bg-zinc-800/50">
                                            {{ $product['name'] }} ({{ $product['sku'] }})
                                        </div>
                                        @foreach($product['variants'] as $variant)
                                        <button type="button" wire:click="addProduct({{ $product['id'] }}, {{ $variant['id'] }})"
                                                class="w-full flex items-center justify-between px-3 py-2 pl-6 text-sm hover:bg-gray-100 dark:hover:bg-zinc-700">
                                            <span>{{ $variant['name'] }} <span class="font-mono text-xs text-gray-400">({{ $variant['sku'] }})</span></span>
                                            <span class="text-gray-500 dark:text-zinc-400">R$ {{ number_format($variant['price'], 2, ',', '.') }}</span>
                                        </button>
                                        @endforeach
                                    @else
                                        <button type="button" wire:click="addProduct({{ $product['id'] }})"
                                                class="w-full flex items-center justify-between px-3 py-2 text-sm hover:bg-gray-100 dark:hover:bg-zinc-700">
                                            <span>{{ $product['name'] }} <span class="font-mono text-xs text-gray-400">({{ $product['sku'] }})</span></span>
                                            <span class="text-gray-500 dark:text-zinc-400">R$ {{ number_format($product['price'], 2, ',', '.') }}</span>
                                        </button>
                                    @endif
                                </div>
                                @endforeach
                            </div>
                            @endif
                        </div>

                        @error('items') <p class="form-error">{{ $message }}</p> @enderror

                        {{-- Items list --}}
                        @forelse($items as $index => $item)
                        <div wire:key="item-{{ $index }}" class="p-4 rounded-lg border border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800/50">
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $item['name'] }}</span>
                                <button type="button" wire:click="removeItem({{ $index }})" class="text-red-500 hover:text-red-700">
                                    <x-heroicon-o-trash class="w-4 h-4" />
                                </button>
                            </div>
                            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                                <div>
                                    <label class="form-label">SKU</label>
                                    <input type="text" wire:model="items.{{ $index }}.sku" class="form-input font-mono text-sm" readonly>
                                </div>
                                <div>
                                    <label class="form-label">Qtd *</label>
                                    <input type="number" wire:model.live="items.{{ $index }}.quantity" class="form-input" min="1" required>
                                    @error("items.{$index}.quantity") <p class="form-error">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="form-label">Preco Unit. (R$) *</label>
                                    <input type="number" step="0.01" wire:model.live="items.{{ $index }}.unit_price" class="form-input" min="0" required>
                                    @error("items.{$index}.unit_price") <p class="form-error">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="form-label">Desconto (R$)</label>
                                    <input type="number" step="0.01" wire:model.live="items.{{ $index }}.discount" class="form-input" min="0">
                                </div>
                            </div>
                        </div>
                        @empty
                        <p class="text-sm text-gray-500 dark:text-zinc-400">Nenhum item adicionado. Busque um produto acima.</p>
                        @endforelse

                        {{-- Items summary --}}
                        @if(count($items) > 0)
                        <div class="pt-4 border-t border-gray-200 dark:border-zinc-700">
                            <div class="flex justify-end">
                                <div class="w-64 space-y-1 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-500 dark:text-zinc-400">Subtotal</span>
                                        <span class="font-medium">R$ {{ number_format($this->subtotal, 2, ',', '.') }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>
                </x-ui.card>

                {{-- Shipping address --}}
                <x-ui.card title="Endereco de Entrega">
                    <div class="space-y-4">
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label for="shipping_zipcode" class="form-label">CEP</label>
                                <input type="text" id="shipping_zipcode" wire:model="shipping_zipcode" class="form-input" maxlength="9">
                            </div>
                            <div class="col-span-2">
                                <label for="shipping_street" class="form-label">Rua</label>
                                <input type="text" id="shipping_street" wire:model="shipping_street" class="form-input">
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label for="shipping_number" class="form-label">Numero</label>
                                <input type="text" id="shipping_number" wire:model="shipping_number" class="form-input">
                            </div>
                            <div>
                                <label for="shipping_complement" class="form-label">Complemento</label>
                                <input type="text" id="shipping_complement" wire:model="shipping_complement" class="form-input">
                            </div>
                            <div>
                                <label for="shipping_neighborhood" class="form-label">Bairro</label>
                                <input type="text" id="shipping_neighborhood" wire:model="shipping_neighborhood" class="form-input">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="shipping_city" class="form-label">Cidade</label>
                                <input type="text" id="shipping_city" wire:model="shipping_city" class="form-input">
                            </div>
                            <div>
                                <label for="shipping_state" class="form-label">Estado</label>
                                <select id="shipping_state" wire:model="shipping_state" class="form-input">
                                    <option value="">Selecione</option>
                                    <option value="AC">AC</option>
                                    <option value="AL">AL</option>
                                    <option value="AP">AP</option>
                                    <option value="AM">AM</option>
                                    <option value="BA">BA</option>
                                    <option value="CE">CE</option>
                                    <option value="DF">DF</option>
                                    <option value="ES">ES</option>
                                    <option value="GO">GO</option>
                                    <option value="MA">MA</option>
                                    <option value="MT">MT</option>
                                    <option value="MS">MS</option>
                                    <option value="MG">MG</option>
                                    <option value="PA">PA</option>
                                    <option value="PB">PB</option>
                                    <option value="PR">PR</option>
                                    <option value="PE">PE</option>
                                    <option value="PI">PI</option>
                                    <option value="RJ">RJ</option>
                                    <option value="RN">RN</option>
                                    <option value="RS">RS</option>
                                    <option value="RO">RO</option>
                                    <option value="RR">RR</option>
                                    <option value="SC">SC</option>
                                    <option value="SP">SP</option>
                                    <option value="SE">SE</option>
                                    <option value="TO">TO</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </x-ui.card>

                {{-- Notes --}}
                <x-ui.card title="Observacoes">
                    <div class="space-y-4">
                        <div>
                            <label for="notes" class="form-label">Observacoes do Pedido</label>
                            <textarea id="notes" wire:model="notes" class="form-input" rows="3" maxlength="2000"></textarea>
                        </div>
                        <div>
                            <label for="internal_notes" class="form-label">Notas Internas</label>
                            <textarea id="internal_notes" wire:model="internal_notes" class="form-input" rows="3" maxlength="2000"></textarea>
                            <p class="text-xs text-gray-500 dark:text-zinc-400 mt-1">Visiveis apenas para a equipe</p>
                        </div>
                    </div>
                </x-ui.card>
            </div>

            {{-- Sidebar (1/3) --}}
            <div class="space-y-6">
                {{-- Status --}}
                <x-ui.card title="Status">
                    <div class="space-y-4">
                        <div>
                            <label for="status" class="form-label">Status do Pedido</label>
                            <select id="status" wire:model="status" class="form-input">
                                @foreach($statuses as $s)
                                    <option value="{{ $s->value }}">{{ $s->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="payment_status" class="form-label">Status do Pagamento</label>
                            <select id="payment_status" wire:model="payment_status" class="form-input">
                                @foreach($paymentStatuses as $ps)
                                    <option value="{{ $ps->value }}">{{ $ps->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="payment_method" class="form-label">Forma de Pagamento</label>
                            <select id="payment_method" wire:model="payment_method" class="form-input">
                                <option value="">Selecione</option>
                                <option value="pix">PIX</option>
                                <option value="credit_card">Cartao de Credito</option>
                                <option value="debit_card">Cartao de Debito</option>
                                <option value="boleto">Boleto</option>
                                <option value="transfer">Transferencia</option>
                                <option value="cash">Dinheiro</option>
                            </select>
                        </div>
                    </div>
                </x-ui.card>

                {{-- Shipping --}}
                <x-ui.card title="Envio">
                    <div class="space-y-4">
                        <div>
                            <label for="shipping_method" class="form-label">Metodo de Envio</label>
                            <input type="text" id="shipping_method" wire:model="shipping_method" class="form-input" placeholder="Ex: Correios PAC">
                        </div>
                        <div>
                            <label for="tracking_code" class="form-label">Codigo de Rastreio</label>
                            <input type="text" id="tracking_code" wire:model="tracking_code" class="form-input font-mono">
                        </div>
                        <div>
                            <label for="shipping_cost" class="form-label">Custo do Frete (R$)</label>
                            <input type="number" step="0.01" id="shipping_cost" wire:model.live="shipping_cost" class="form-input" min="0">
                        </div>
                    </div>
                </x-ui.card>

                {{-- Discount & Total --}}
                <x-ui.card title="Resumo">
                    <div class="space-y-4">
                        <div>
                            <label for="discount" class="form-label">Desconto Geral (R$)</label>
                            <input type="number" step="0.01" id="discount" wire:model.live="discount" class="form-input" min="0">
                        </div>

                        <div class="pt-3 border-t border-gray-200 dark:border-zinc-700 space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-500 dark:text-zinc-400">Subtotal</span>
                                <span>R$ {{ number_format($this->subtotal, 2, ',', '.') }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500 dark:text-zinc-400">Frete</span>
                                <span>R$ {{ number_format((float) $shipping_cost, 2, ',', '.') }}</span>
                            </div>
                            @if((float) $discount > 0)
                            <div class="flex justify-between">
                                <span class="text-gray-500 dark:text-zinc-400">Desconto</span>
                                <span class="text-red-600 dark:text-red-400">- R$ {{ number_format((float) $discount, 2, ',', '.') }}</span>
                            </div>
                            @endif
                            <div class="flex justify-between font-medium text-base pt-2 border-t border-gray-200 dark:border-zinc-700">
                                <span>Total</span>
                                <span>R$ {{ number_format($this->total, 2, ',', '.') }}</span>
                            </div>
                        </div>
                    </div>
                </x-ui.card>

                {{-- Order number (edit only) --}}
                @if($order)
                <x-ui.card title="Informacoes">
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-zinc-400">Numero</span>
                            <span class="font-mono font-medium">{{ $order->order_number }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-zinc-400">Criado em</span>
                            <span>{{ $order->created_at->format('d/m/Y H:i') }}</span>
                        </div>
                    </div>
                </x-ui.card>
                @endif
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex items-center justify-end gap-3 mt-6">
            <a href="{{ route('orders.index') }}" class="btn-secondary">Cancelar</a>
            <button type="submit" class="btn-primary" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="save">
                    <x-heroicon-s-check class="w-4 h-4" />
                    {{ $order ? 'Salvar Alteracoes' : 'Cadastrar Pedido' }}
                </span>
                <span wire:loading wire:target="save">Salvando...</span>
            </button>
        </div>
    </form>
</div>
