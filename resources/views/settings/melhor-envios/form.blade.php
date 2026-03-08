<x-app-layout>
    <x-slot name="header">{{ $account->exists ? 'Editar' : 'Nova' }} Conta Melhor Envios</x-slot>
    <x-slot name="breadcrumbs">
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <a href="{{ route('settings.index') }}" class="hover:text-gray-700 dark:hover:text-zinc-200">Configurações</a>
        </li>
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <a href="{{ route('settings.me.index') }}" class="hover:text-gray-700 dark:hover:text-zinc-200">Melhor Envios</a>
        </li>
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <span class="text-gray-700 dark:text-zinc-200">{{ $account->exists ? $account->name : 'Nova' }}</span>
        </li>
    </x-slot>

    @php $fromAddr = $account->from_address ?? []; $pkg = $account->default_package ?? []; @endphp

    <form method="POST"
          action="{{ $account->exists ? route('settings.me.update', $account) : route('settings.me.store') }}"
          class="space-y-6 max-w-3xl">
        @csrf
        @if($account->exists) @method('PUT') @endif

        {{-- Identificação --}}
        <x-ui.card title="Identificação">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="form-label">Nome da Conta <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $account->name) }}"
                           placeholder="Ex: Loja Principal — ME Produção" class="form-input" required>
                </div>
                <div>
                    <label class="form-label">Ambiente <span class="text-red-500">*</span></label>
                    <select name="environment" class="form-input" required>
                        <option value="production" {{ old('environment', $account->environment) === 'production' ? 'selected' : '' }}>
                            Produção
                        </option>
                        <option value="sandbox" {{ old('environment', $account->environment) === 'sandbox' ? 'selected' : '' }}>
                            Sandbox (testes)
                        </option>
                    </select>
                </div>
            </div>
        </x-ui.card>

        {{-- Credenciais OAuth --}}
        <x-ui.card title="Credenciais OAuth2">
            @if($account->exists && $account->is_active)
            <div class="flex items-center gap-2 p-3 mb-4 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700">
                <x-heroicon-s-check-circle class="w-5 h-5 text-green-500 flex-shrink-0" />
                <div class="text-sm text-green-700 dark:text-green-300">
                    <strong>Conta conectada!</strong>
                    @if($account->token_expires_at)
                        Token expira em {{ $account->token_expires_at->format('d/m/Y H:i') }}.
                    @endif
                    <a href="{{ route('me.connect', $account) }}" class="underline ml-2">Reconectar</a>
                </div>
            </div>
            @elseif($account->exists)
            <div class="flex items-center gap-2 p-3 mb-4 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700">
                <x-heroicon-o-exclamation-triangle class="w-4 h-4 text-amber-500 flex-shrink-0" />
                <p class="text-sm text-amber-700 dark:text-amber-300">
                    Conta não conectada. Salve primeiro e depois clique em
                    <a href="{{ route('me.connect', $account) }}" class="underline font-medium">Conectar OAuth</a>.
                </p>
            </div>
            @else
            <div class="flex items-start gap-2 p-3 mb-4 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700">
                <x-heroicon-o-information-circle class="w-4 h-4 text-blue-500 flex-shrink-0 mt-0.5" />
                <p class="text-sm text-blue-700 dark:text-blue-300">
                    Após salvar, você será direcionado para autorizar a integração com o Melhor Envios via OAuth2.
                    O <strong>Client ID</strong> e <strong>Client Secret</strong> são obtidos no painel de desenvolvedor do Melhor Envios.
                </p>
            </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Client ID</label>
                    <input type="text" name="client_id"
                           value="{{ old('client_id', $account->client_id) }}"
                           placeholder="Obtido no painel ME → Aplicativos" class="form-input font-mono text-sm">
                </div>
                <div>
                    <label class="form-label">Client Secret</label>
                    <input type="password" name="client_secret"
                           value="{{ $account->exists ? '••••••••' : '' }}"
                           placeholder="{{ $account->exists ? 'Deixe em branco para manter' : 'Client Secret' }}"
                           class="form-input font-mono text-sm">
                </div>
            </div>
        </x-ui.card>

        {{-- Remetente --}}
        <x-ui.card title="Dados do Remetente (Coleta)">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Nome do Remetente</label>
                    <input type="text" name="from_name"
                           value="{{ old('from_name', $account->from_name) }}"
                           placeholder="Nome que aparece nas etiquetas" class="form-input">
                </div>
                <div>
                    <label class="form-label">CPF/CNPJ do Remetente</label>
                    <input type="text" name="from_document"
                           value="{{ old('from_document', $account->from_document) }}"
                           placeholder="Somente números" class="form-input font-mono">
                </div>
                <div>
                    <label class="form-label">CEP de Coleta <span class="text-red-500">*</span></label>
                    <input type="text" name="from_cep"
                           value="{{ old('from_cep', $account->from_cep) }}"
                           placeholder="00000-000" class="form-input font-mono" maxlength="9">
                </div>
                <div>
                    <label class="form-label">Telefone</label>
                    <input type="text" name="from_phone"
                           value="{{ old('from_phone', $fromAddr['phone'] ?? '') }}"
                           placeholder="(11) 99999-9999" class="form-input">
                </div>
                <div>
                    <label class="form-label">E-mail</label>
                    <input type="email" name="from_email"
                           value="{{ old('from_email', $fromAddr['email'] ?? '') }}"
                           placeholder="coleta@empresa.com.br" class="form-input">
                </div>
            </div>

            <div class="mt-4 pt-4 border-t border-gray-100 dark:border-zinc-800">
                <p class="text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase tracking-wider mb-3">Endereço de Coleta</p>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="md:col-span-2">
                        <label class="form-label">Logradouro</label>
                        <input type="text" name="from_street"
                               value="{{ old('from_street', $fromAddr['street'] ?? '') }}"
                               class="form-input">
                    </div>
                    <div>
                        <label class="form-label">Número</label>
                        <input type="text" name="from_number"
                               value="{{ old('from_number', $fromAddr['number'] ?? '') }}"
                               class="form-input">
                    </div>
                    <div>
                        <label class="form-label">Complemento</label>
                        <input type="text" name="from_complement"
                               value="{{ old('from_complement', $fromAddr['complement'] ?? '') }}"
                               class="form-input">
                    </div>
                    <div>
                        <label class="form-label">Bairro</label>
                        <input type="text" name="from_district"
                               value="{{ old('from_district', $fromAddr['neighborhood'] ?? '') }}"
                               class="form-input">
                    </div>
                    <div>
                        <label class="form-label">Cidade</label>
                        <input type="text" name="from_city"
                               value="{{ old('from_city', $fromAddr['city'] ?? '') }}"
                               class="form-input">
                    </div>
                    <div>
                        <label class="form-label">UF</label>
                        <input type="text" name="from_state"
                               value="{{ old('from_state', $fromAddr['state'] ?? '') }}"
                               maxlength="2" class="form-input uppercase" style="text-transform:uppercase">
                    </div>
                </div>
            </div>
        </x-ui.card>

        {{-- Dimensões padrão --}}
        <x-ui.card title="Dimensões Padrão do Pacote">
            <p class="text-sm text-gray-500 dark:text-zinc-400 mb-4">
                Usadas como ponto de partida na cotação inline. Podem ser ajustadas por pedido.
            </p>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div>
                    <label class="form-label">Peso (kg)</label>
                    <input type="number" step="0.01" name="pkg_weight"
                           value="{{ old('pkg_weight', $pkg['weight'] ?? 0.5) }}"
                           min="0.01" class="form-input">
                </div>
                <div>
                    <label class="form-label">Largura (cm)</label>
                    <input type="number" name="pkg_width"
                           value="{{ old('pkg_width', $pkg['width'] ?? 12) }}"
                           min="1" class="form-input">
                </div>
                <div>
                    <label class="form-label">Altura (cm)</label>
                    <input type="number" name="pkg_height"
                           value="{{ old('pkg_height', $pkg['height'] ?? 4) }}"
                           min="1" class="form-input">
                </div>
                <div>
                    <label class="form-label">Comprimento (cm)</label>
                    <input type="number" name="pkg_length"
                           value="{{ old('pkg_length', $pkg['length'] ?? 17) }}"
                           min="1" class="form-input">
                </div>
            </div>
        </x-ui.card>

        <div class="flex justify-end gap-3">
            <a href="{{ route('settings.me.index') }}" class="btn-secondary">Cancelar</a>
            <button type="submit" class="btn-primary">
                <x-heroicon-o-check class="w-4 h-4" />
                {{ $account->exists ? 'Salvar alterações' : 'Criar e configurar OAuth' }}
            </button>
        </div>
    </form>
</x-app-layout>
