<x-app-layout>
    <x-slot name="header">{{ $account->exists ? 'Editar' : 'Nova' }} Conta Webmaniabr</x-slot>
    <x-slot name="breadcrumbs">
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <a href="{{ route('settings.index') }}" class="hover:text-gray-700">Configurações</a>
        </li>
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <a href="{{ route('settings.webmania.index') }}" class="hover:text-gray-700">Webmaniabr</a>
        </li>
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <span class="text-gray-700 dark:text-zinc-200">{{ $account->exists ? $account->name : 'Nova' }}</span>
        </li>
    </x-slot>

    <form method="POST"
          action="{{ $account->exists ? route('settings.webmania.update', $account) : route('settings.webmania.store') }}"
          class="space-y-6 max-w-3xl">
        @csrf
        @if($account->exists) @method('PUT') @endif

        {{-- SEÇÃO: Identificação --}}
        <x-ui.card title="Identificação">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="form-label">Nome da Conta <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $account->name) }}"
                           placeholder="Ex: Empresa Principal — Produção" class="form-input" required>
                </div>
                <div>
                    <label class="form-label">Ambiente <span class="text-red-500">*</span></label>
                    <select name="environment" class="form-input" required>
                        <option value="producao" {{ old('environment', $account->environment) === 'producao' ? 'selected' : '' }}>
                            Produção
                        </option>
                        <option value="homologacao" {{ old('environment', $account->environment) === 'homologacao' ? 'selected' : '' }}>
                            Homologação (testes)
                        </option>
                    </select>
                </div>
                <div class="flex items-center gap-3 pt-6">
                    <input type="checkbox" id="is_active" name="is_active" value="1"
                           {{ old('is_active', $account->is_active ?? false) ? 'checked' : '' }}
                           class="rounded border-gray-300 dark:border-zinc-600">
                    <label for="is_active" class="text-sm font-medium">Conta ativa</label>
                </div>
            </div>
        </x-ui.card>

        {{-- SEÇÃO: Credenciais API 2.0 (Bearer) --}}
        <x-ui.card title="Credenciais API 2.0 — Bearer Token (NF-e)">
            <p class="text-sm text-gray-500 dark:text-zinc-400 mb-4">
                Use o Bearer Token da API 2.0 para emissão de NF-e.
                Encontre em: painel Webmaniabr → Configurações → API 2.0.
            </p>
            <div>
                <label class="form-label">Bearer Token</label>
                <input type="password" name="bearer_token"
                       value="{{ $account->exists ? '••••••••' : '' }}"
                       placeholder="{{ $account->exists ? 'Deixe em branco para manter o atual' : 'Informe o Bearer Token' }}"
                       class="form-input font-mono">
            </div>
        </x-ui.card>

        {{-- SEÇÃO: Credenciais API 1.0 (OAuth — legado) --}}
        <details class="border border-gray-200 dark:border-zinc-700 rounded-xl">
            <summary class="px-5 py-4 cursor-pointer font-medium text-gray-700 dark:text-zinc-300 text-sm">
                Credenciais API 1.0 — OAuth (legado, NFS-e municipal)
            </summary>
            <div class="px-5 pb-5 pt-2 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Consumer Key</label>
                    <input type="password" name="consumer_key"
                           value="{{ $account->exists ? '••••••••' : '' }}" class="form-input font-mono">
                </div>
                <div>
                    <label class="form-label">Consumer Secret</label>
                    <input type="password" name="consumer_secret"
                           value="{{ $account->exists ? '••••••••' : '' }}" class="form-input font-mono">
                </div>
                <div>
                    <label class="form-label">Access Token</label>
                    <input type="password" name="access_token"
                           value="{{ $account->exists ? '••••••••' : '' }}" class="form-input font-mono">
                </div>
                <div>
                    <label class="form-label">Access Token Secret</label>
                    <input type="password" name="access_token_secret"
                           value="{{ $account->exists ? '••••••••' : '' }}" class="form-input font-mono">
                </div>
            </div>
        </details>

        {{-- SEÇÃO: Padrões de Emissão --}}
        <x-ui.card title="Padrões de Emissão de NF-e">
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                <div>
                    <label class="form-label">Série</label>
                    <input type="text" name="default_series"
                           value="{{ old('default_series', $account->default_series) }}"
                           placeholder="1" class="form-input">
                </div>
                <div>
                    <label class="form-label">CFOP padrão</label>
                    <input type="text" name="default_cfop"
                           value="{{ old('default_cfop', $account->default_cfop) }}"
                           placeholder="5102" class="form-input font-mono">
                    <p class="text-xs text-gray-400 mt-1">5102 = venda fora do estado</p>
                </div>
                <div>
                    <label class="form-label">Natureza da Operação</label>
                    <input type="text" name="default_nature_operation"
                           value="{{ old('default_nature_operation', $account->default_nature_operation ?? 'Venda') }}"
                           class="form-input">
                </div>
                <div>
                    <label class="form-label">NCM padrão</label>
                    <input type="text" name="default_ncm"
                           value="{{ old('default_ncm', $account->default_ncm) }}"
                           placeholder="00000000" class="form-input font-mono">
                </div>
                <div>
                    <label class="form-label">CEST padrão</label>
                    <input type="text" name="default_cest"
                           value="{{ old('default_cest', $account->default_cest) }}"
                           placeholder="Opcional" class="form-input font-mono">
                </div>
                <div>
                    <label class="form-label">CST/CSOSN padrão</label>
                    <input type="text" name="default_tax_class"
                           value="{{ old('default_tax_class', $account->default_tax_class ?? '400') }}"
                           placeholder="400" class="form-input font-mono">
                    <p class="text-xs text-gray-400 mt-1">400 = isento</p>
                </div>
                <div>
                    <label class="form-label">Origem do produto</label>
                    <select name="default_origin" class="form-input">
                        @foreach([
                            '0' => '0 — Nacional',
                            '1' => '1 — Importado',
                            '2' => '2 — Importado, adq. no mercado interno',
                            '3' => '3 — Nacional, > 40% conteúdo importado',
                        ] as $val => $lbl)
                        <option value="{{ $val }}" {{ old('default_origin', $account->default_origin ?? '0') === $val ? 'selected' : '' }}>
                            {{ $lbl }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Modalidade de Frete</label>
                    <select name="default_shipping_modality" class="form-input">
                        @foreach([
                            '9' => '9 — Sem Frete',
                            '0' => '0 — CIF (remetente paga)',
                            '1' => '1 — FOB (destinatário paga)',
                            '2' => '2 — Terceiros',
                        ] as $val => $lbl)
                        <option value="{{ $val }}" {{ old('default_shipping_modality', $account->default_shipping_modality ?? '9') === $val ? 'selected' : '' }}>
                            {{ $lbl }}
                        </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </x-ui.card>

        {{-- SEÇÃO: Intermediador (marketplace) --}}
        <details class="border border-gray-200 dark:border-zinc-700 rounded-xl">
            <summary class="px-5 py-4 cursor-pointer font-medium text-gray-700 dark:text-zinc-300 text-sm">
                Intermediador (obrigatório para vendas via marketplace)
            </summary>
            <div class="px-5 pb-5 pt-2 grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="form-label">Tipo</label>
                    <select name="intermediador_type" class="form-input">
                        <option value="0" {{ old('intermediador_type', $account->intermediador_type ?? '0') === '0' ? 'selected' : '' }}>
                            0 — Operação sem intermediador
                        </option>
                        <option value="1" {{ old('intermediador_type', $account->intermediador_type ?? '') === '1' ? 'selected' : '' }}>
                            1 — Operação com intermediador
                        </option>
                    </select>
                </div>
                <div>
                    <label class="form-label">CNPJ do Intermediador</label>
                    <input type="text" name="intermediador_cnpj"
                           value="{{ old('intermediador_cnpj', $account->intermediador_cnpj) }}"
                           placeholder="00.000.000/0001-00" class="form-input font-mono">
                    <p class="text-xs text-gray-400 mt-1">Ex: Mercado Livre — 03.007.331/0001-41</p>
                </div>
                <div>
                    <label class="form-label">Identificador Comprador</label>
                    <input type="text" name="intermediador_id"
                           value="{{ old('intermediador_id', $account->intermediador_id) }}"
                           placeholder="CPF ou email do comprador" class="form-input">
                </div>
            </div>
        </details>

        {{-- SEÇÃO: Informações Complementares --}}
        <x-ui.card title="Informações Complementares">
            <div class="space-y-3">
                <div>
                    <label class="form-label">Info ao Fisco (padrão)</label>
                    <textarea name="additional_info_fisco" rows="3" class="form-input">{{ old('additional_info_fisco', $account->additional_info_fisco) }}</textarea>
                </div>
                <div>
                    <label class="form-label">Info ao Contribuinte/Consumidor (padrão)</label>
                    <textarea name="additional_info_consumer" rows="3" class="form-input">{{ old('additional_info_consumer', $account->additional_info_consumer) }}</textarea>
                </div>
            </div>
        </x-ui.card>

        {{-- SEÇÃO: Automação --}}
        <x-ui.card title="Automação de Emissão">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="form-label">Emitir NF-e automaticamente quando</label>
                    <select name="auto_emit_trigger" class="form-input">
                        <option value="none" {{ old('auto_emit_trigger', $account->auto_emit_trigger ?? 'none') === 'none' ? 'selected' : '' }}>
                            Nunca (somente manual)
                        </option>
                        <option value="processing" {{ old('auto_emit_trigger', $account->auto_emit_trigger ?? '') === 'processing' ? 'selected' : '' }}>
                            Pedido confirmado/pago
                        </option>
                        <option value="completed" {{ old('auto_emit_trigger', $account->auto_emit_trigger ?? '') === 'completed' ? 'selected' : '' }}>
                            Pedido marcado como enviado
                        </option>
                    </select>
                </div>
                <div>
                    <label class="form-label">E-mail para alertas de erro</label>
                    <input type="email" name="error_email"
                           value="{{ old('error_email', $account->error_email) }}"
                           placeholder="notificacoes@empresa.com.br" class="form-input">
                </div>

                <div class="flex items-center gap-3">
                    <input type="checkbox" id="auto_send_email" name="auto_send_email" value="1"
                           {{ old('auto_send_email', $account->auto_send_email ?? false) ? 'checked' : '' }}
                           class="rounded border-gray-300 dark:border-zinc-600">
                    <label for="auto_send_email" class="text-sm">Enviar e-mail com DANFE ao cliente após emissão</label>
                </div>
                <div class="flex items-center gap-3">
                    <input type="checkbox" id="emit_with_order_date" name="emit_with_order_date" value="1"
                           {{ old('emit_with_order_date', $account->emit_with_order_date ?? false) ? 'checked' : '' }}
                           class="rounded border-gray-300 dark:border-zinc-600">
                    <label for="emit_with_order_date" class="text-sm">Usar a data do pedido como data de emissão</label>
                </div>
            </div>
        </x-ui.card>

        <div class="flex justify-end gap-3">
            <a href="{{ route('settings.webmania.index') }}" class="btn-secondary">Cancelar</a>
            <button type="submit" class="btn-primary">
                <x-heroicon-o-check class="w-4 h-4" />
                {{ $account->exists ? 'Salvar alterações' : 'Criar conta' }}
            </button>
        </div>
    </form>
</x-app-layout>
