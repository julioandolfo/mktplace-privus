<div>
    <form wire:submit="save" class="space-y-6">
        <x-ui.card title="Bonificação por Pontos">
            <div class="space-y-5 max-w-lg">
                {{-- Toggle ativo --}}
                <div class="flex items-center justify-between">
                    <div>
                        <p class="font-semibold text-gray-900 dark:text-white">Sistema de Bonificação</p>
                        <p class="text-sm text-gray-500 dark:text-zinc-400">Ativar/desativar cálculo de pontos e bonificação</p>
                    </div>
                    <button type="button" wire:click="$toggle('is_active')"
                            class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 {{ $is_active ? 'bg-primary-500' : 'bg-gray-300 dark:bg-zinc-600' }}">
                        <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform duration-200 {{ $is_active ? 'translate-x-5' : 'translate-x-0' }}"></span>
                    </button>
                </div>

                <div class="h-px bg-gray-100 dark:bg-zinc-700"></div>

                {{-- Valor do ponto --}}
                <div>
                    <label class="form-label">Valor de cada ponto (centavos)</label>
                    <div class="flex items-center gap-3">
                        <input type="number" wire:model.live="points_value_cents" class="form-input w-32" min="1">
                        <span class="text-sm text-gray-500 dark:text-zinc-400">= {{ $pointValueFormatted }} por ponto</span>
                    </div>
                    <p class="text-xs text-gray-400 dark:text-zinc-500 mt-1">Ex: 10 = R$ 0,10 por ponto, 100 = R$ 1,00 por ponto</p>
                    @error('points_value_cents') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                {{-- Pontos padrão por produto --}}
                <div>
                    <label class="form-label">Pontos padrão por unidade de produto</label>
                    <input type="number" wire:model="default_product_points" class="form-input w-32" min="1">
                    <p class="text-xs text-gray-400 dark:text-zinc-500 mt-1">Usado quando o produto não tem pontos configurados individualmente.</p>
                    @error('default_product_points') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                {{-- Buffer de dias --}}
                <div>
                    <label class="form-label">Dias de folga antes do prazo</label>
                    <input type="number" wire:model="deadline_buffer_days" class="form-input w-32" min="0" max="10">
                    <p class="text-xs text-gray-400 dark:text-zinc-500 mt-1">Quantos dias antes do prazo real a meta diária deve considerar. Ex: 1 = processar 1 dia antes do vencimento.</p>
                    @error('deadline_buffer_days') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <x-slot name="footer">
                <div class="flex justify-end">
                    <button type="submit" class="btn-primary">Salvar Configurações</button>
                </div>
            </x-slot>
        </x-ui.card>

        {{-- Explicação --}}
        <div class="flex items-start gap-3 bg-blue-50 dark:bg-blue-900/10 border border-blue-200 dark:border-blue-800 rounded-xl px-4 py-3">
            <x-heroicon-o-information-circle class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" />
            <div class="text-sm text-blue-700 dark:text-blue-300">
                <p class="font-medium">Como funciona a pontuação?</p>
                <ul class="mt-1 space-y-1 text-blue-600 dark:text-blue-400 list-disc ml-4">
                    <li>Cada produto tem pontos por unidade (padrão global ou customizado no cadastro do produto)</li>
                    <li>Ao embalar: operador que conferiu ganha pontos de <strong>embalagem</strong></li>
                    <li>Ao despachar: operador que despachou ganha pontos de <strong>despacho</strong></li>
                    <li>Cada etapa ganha independentemente — sem divisão entre operadores</li>
                    <li>No final do mês, feche o período para travar valores e gerar relatório</li>
                </ul>
            </div>
        </div>
    </form>
</div>
