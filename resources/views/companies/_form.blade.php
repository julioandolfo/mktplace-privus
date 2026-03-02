@php $company = $company ?? null; @endphp

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    {{-- Company info --}}
    <x-ui.card title="Dados da Empresa">
        <div class="space-y-4">
            <div>
                <label for="name" class="form-label">Nome Fantasia *</label>
                <input type="text" id="name" name="name" value="{{ old('name', $company?->name) }}" class="form-input" required>
                @error('name') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="trade_name" class="form-label">Razao Social</label>
                <input type="text" id="trade_name" name="trade_name" value="{{ old('trade_name', $company?->trade_name) }}" class="form-input">
                @error('trade_name') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="document_type" class="form-label">Tipo *</label>
                    <select id="document_type" name="document_type" class="form-input">
                        <option value="cnpj" {{ old('document_type', $company?->document_type) === 'cnpj' ? 'selected' : '' }}>CNPJ</option>
                        <option value="cpf" {{ old('document_type', $company?->document_type) === 'cpf' ? 'selected' : '' }}>CPF</option>
                    </select>
                </div>
                <div>
                    <label for="document" class="form-label">Documento *</label>
                    <input type="text" id="document" name="document" value="{{ old('document', $company?->document) }}" class="form-input" required>
                    @error('document') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="state_registration" class="form-label">Inscricao Estadual</label>
                    <input type="text" id="state_registration" name="state_registration" value="{{ old('state_registration', $company?->state_registration) }}" class="form-input">
                </div>
                <div>
                    <label for="municipal_registration" class="form-label">Inscricao Municipal</label>
                    <input type="text" id="municipal_registration" name="municipal_registration" value="{{ old('municipal_registration', $company?->municipal_registration) }}" class="form-input">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="phone" class="form-label">Telefone</label>
                    <input type="text" id="phone" name="phone" value="{{ old('phone', $company?->phone) }}" class="form-input">
                </div>
                <div>
                    <label for="email" class="form-label">E-mail</label>
                    <input type="email" id="email" name="email" value="{{ old('email', $company?->email) }}" class="form-input">
                </div>
            </div>

            @if($company)
            <div class="flex items-center gap-3">
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" class="sr-only peer" {{ old('is_active', $company->is_active) ? 'checked' : '' }}>
                    <div class="w-11 h-6 bg-gray-200 dark:bg-zinc-600 peer-focus:ring-2 peer-focus:ring-primary-500 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary-600"></div>
                </label>
                <span class="text-sm text-gray-700 dark:text-zinc-300">Empresa ativa</span>
            </div>
            @endif
        </div>
    </x-ui.card>

    {{-- Address --}}
    <x-ui.card title="Endereco">
        <div class="space-y-4">
            <div>
                <label for="address_zip_code" class="form-label">CEP</label>
                <input type="text" id="address_zip_code" name="address[zip_code]" value="{{ old('address.zip_code', $company?->address['zip_code'] ?? '') }}" class="form-input" maxlength="10">
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div class="col-span-2">
                    <label for="address_street" class="form-label">Logradouro</label>
                    <input type="text" id="address_street" name="address[street]" value="{{ old('address.street', $company?->address['street'] ?? '') }}" class="form-input">
                </div>
                <div>
                    <label for="address_number" class="form-label">Numero</label>
                    <input type="text" id="address_number" name="address[number]" value="{{ old('address.number', $company?->address['number'] ?? '') }}" class="form-input">
                </div>
            </div>

            <div>
                <label for="address_complement" class="form-label">Complemento</label>
                <input type="text" id="address_complement" name="address[complement]" value="{{ old('address.complement', $company?->address['complement'] ?? '') }}" class="form-input">
            </div>

            <div>
                <label for="address_neighborhood" class="form-label">Bairro</label>
                <input type="text" id="address_neighborhood" name="address[neighborhood]" value="{{ old('address.neighborhood', $company?->address['neighborhood'] ?? '') }}" class="form-input">
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div class="col-span-2">
                    <label for="address_city" class="form-label">Cidade</label>
                    <input type="text" id="address_city" name="address[city]" value="{{ old('address.city', $company?->address['city'] ?? '') }}" class="form-input">
                </div>
                <div>
                    <label for="address_state" class="form-label">UF</label>
                    <select id="address_state" name="address[state]" class="form-input">
                        <option value="">--</option>
                        @foreach(['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'] as $uf)
                        <option value="{{ $uf }}" {{ old('address.state', $company?->address['state'] ?? '') === $uf ? 'selected' : '' }}>{{ $uf }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </x-ui.card>
</div>

<div class="flex items-center justify-end gap-3 mt-6">
    <a href="{{ route('companies.index') }}" class="btn-secondary">Cancelar</a>
    <button type="submit" class="btn-primary">
        <x-heroicon-s-check class="w-4 h-4" />
        {{ $company ? 'Salvar Alteracoes' : 'Cadastrar Empresa' }}
    </button>
</div>
