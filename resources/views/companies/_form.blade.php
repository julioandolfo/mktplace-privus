@php $company = $company ?? null; @endphp

<div
    class="grid grid-cols-1 lg:grid-cols-2 gap-6"
    x-data="{
        docType: @js(old('document_type', $company?->document_type ?? 'cnpj')),
        loading: false,
        apiError: '',

        formatCnpj(v) {
            v = v.replace(/\D/g, '').slice(0, 14);
            if (v.length > 12) return v.slice(0,2)+'.'+v.slice(2,5)+'.'+v.slice(5,8)+'/'+v.slice(8,12)+'-'+v.slice(12);
            if (v.length > 8)  return v.slice(0,2)+'.'+v.slice(2,5)+'.'+v.slice(5,8)+'/'+v.slice(8);
            if (v.length > 5)  return v.slice(0,2)+'.'+v.slice(2,5)+'.'+v.slice(5);
            if (v.length > 2)  return v.slice(0,2)+'.'+v.slice(2);
            return v;
        },

        formatCpf(v) {
            v = v.replace(/\D/g, '').slice(0, 11);
            if (v.length > 9) return v.slice(0,3)+'.'+v.slice(3,6)+'.'+v.slice(6,9)+'-'+v.slice(9);
            if (v.length > 6) return v.slice(0,3)+'.'+v.slice(3,6)+'.'+v.slice(6);
            if (v.length > 3) return v.slice(0,3)+'.'+v.slice(3);
            return v;
        },

        onDocInput(event) {
            this.apiError = '';
            let fmt = this.docType === 'cnpj'
                ? this.formatCnpj(event.target.value)
                : this.formatCpf(event.target.value);
            event.target.value = fmt;
            let digits = fmt.replace(/\D/g, '');
            if (this.docType === 'cnpj' && digits.length === 14) {
                this.fetchCnpj(digits);
            }
        },

        onDocTypeChange() {
            this.apiError = '';
            let input = document.getElementById('document');
            if (input) input.value = '';
        },

        async fetchCnpj(cnpj) {
            this.loading = true;
            this.apiError = '';
            try {
                const res = await fetch('https://brasilapi.com.br/api/cnpj/v1/' + cnpj);
                if (res.status === 404) {
                    this.apiError = 'CNPJ não encontrado na Receita Federal.';
                    return;
                }
                if (!res.ok) {
                    this.apiError = 'Erro ao consultar CNPJ. Preencha os dados manualmente.';
                    return;
                }
                const d = await res.json();

                this.fill('name', d.nome_fantasia || d.razao_social || '');
                this.fill('trade_name', d.razao_social || '');

                if (d.ddd_telefone_1) {
                    let p = d.ddd_telefone_1.replace(/\D/g, '');
                    let fmtPhone = p.length >= 11
                        ? '(' + p.slice(0,2) + ') ' + p.slice(2,7) + '-' + p.slice(7,11)
                        : '(' + p.slice(0,2) + ') ' + p.slice(2,6) + '-' + p.slice(6,10);
                    this.fill('phone', fmtPhone);
                }
                if (d.email) this.fill('email', d.email.toLowerCase());

                if (d.cep) {
                    let c = d.cep.replace(/\D/g, '');
                    this.fill('address_zip_code', c.slice(0,5) + '-' + c.slice(5));
                }
                this.fill('address_street', d.logradouro || '');
                this.fill('address_number', d.numero || '');
                this.fill('address_complement', d.complemento || '');
                this.fill('address_neighborhood', d.bairro || '');
                this.fill('address_city', d.municipio || '');
                if (d.uf) {
                    let sel = document.getElementById('address_state');
                    if (sel) sel.value = d.uf;
                }

            } catch (e) {
                this.apiError = 'Erro de conexão ao consultar CNPJ.';
            } finally {
                this.loading = false;
            }
        },

        fill(id, value) {
            let el = document.getElementById(id);
            if (el && value) el.value = value;
        },

        init() {
            let input = document.getElementById('document');
            if (input && input.value) {
                input.value = this.docType === 'cnpj'
                    ? this.formatCnpj(input.value)
                    : this.formatCpf(input.value);
            }
        }
    }"
>
    {{-- Company info --}}
    <x-ui.card title="Dados da Empresa">
        <div class="space-y-4">

            {{-- Logo (base64 upload — resized client-side to avoid large payloads) --}}
            <div
                class="flex items-center gap-4 pb-4 border-b border-gray-100 dark:border-zinc-700"
                x-data="{
                    preview: @js($company?->logo_path ? asset('storage/' . $company->logo_path) : null),
                    logoBase64: '',
                    logoName: '',
                    logoError: '',
                    pickFile(event) {
                        const file = event.target.files[0];
                        if (!file) return;
                        if (file.size > 5 * 1024 * 1024) {
                            this.logoError = 'Arquivo muito grande. Máximo 5 MB.';
                            event.target.value = '';
                            return;
                        }
                        this.logoError = '';
                        this.logoName = file.name;
                        this.preview = URL.createObjectURL(file);
                        this.resizeAndEncode(file);
                    },
                    resizeAndEncode(file) {
                        const img = new Image();
                        img.onload = () => {
                            const MAX = 400;
                            let w = img.width, h = img.height;
                            if (w > MAX || h > MAX) {
                                if (w > h) { h = Math.round(h * MAX / w); w = MAX; }
                                else        { w = Math.round(w * MAX / h); h = MAX; }
                            }
                            const canvas = document.createElement('canvas');
                            canvas.width = w;
                            canvas.height = h;
                            canvas.getContext('2d').drawImage(img, 0, 0, w, h);
                            this.logoBase64 = canvas.toDataURL('image/png', 0.9);
                            URL.revokeObjectURL(img.src);
                        };
                        img.src = URL.createObjectURL(file);
                    }
                }"
            >
                <input type="hidden" name="logo_base64" :value="logoBase64">
                <div class="flex-shrink-0">
                    <div class="w-20 h-20 rounded-lg border-2 border-dashed border-gray-300 dark:border-zinc-600 flex items-center justify-center overflow-hidden bg-gray-50 dark:bg-zinc-800">
                        <template x-if="preview">
                            <img :src="preview" class="w-full h-full object-contain p-1">
                        </template>
                        <template x-if="!preview">
                            <x-heroicon-o-building-office-2 class="w-8 h-8 text-gray-300 dark:text-zinc-600" />
                        </template>
                    </div>
                </div>
                <div class="flex-1 min-w-0">
                    <label class="form-label">Logo da Empresa</label>
                    <input
                        type="file"
                        accept="image/png,image/jpeg,image/webp,image/svg+xml"
                        class="block w-full text-sm text-gray-500 dark:text-zinc-400 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-sm file:font-medium file:bg-primary-50 file:text-primary-700 dark:file:bg-primary-900/30 dark:file:text-primary-400 hover:file:bg-primary-100 dark:hover:file:bg-primary-900/50 cursor-pointer"
                        @change="pickFile($event)"
                    >
                    <p class="mt-1 text-xs text-gray-400 dark:text-zinc-500">PNG, JPG, WEBP ou SVG. Max 2 MB.</p>
                    <p x-show="logoError" x-text="logoError" class="mt-1 text-xs text-red-500"></p>
                    @if($company?->logo_path)
                    <label class="flex items-center gap-1.5 mt-1.5 text-xs text-red-500 cursor-pointer select-none">
                        <input type="checkbox" name="remove_logo" value="1" class="rounded border-gray-300 text-red-500" @change="if ($event.target.checked) { preview = null; logoBase64 = ''; }">
                        Remover logo atual
                    </label>
                    @endif
                    @error('logo') <p class="form-error mt-1">{{ $message }}</p> @enderror
                    @error('logo_base64') <p class="form-error mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- CNPJ/CPF — primeiro campo --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="document_type" class="form-label">Tipo *</label>
                    <select id="document_type" name="document_type" class="form-input" x-model="docType" @change="onDocTypeChange()">
                        <option value="cnpj">CNPJ</option>
                        <option value="cpf">CPF</option>
                    </select>
                </div>
                <div>
                    <label for="document" class="form-label">
                        <span x-text="docType === 'cnpj' ? 'CNPJ *' : 'CPF *'">CNPJ *</span>
                    </label>
                    <div class="relative">
                        <input
                            type="text"
                            id="document"
                            name="document"
                            value="{{ old('document', $company?->document) }}"
                            class="form-input pr-8"
                            :placeholder="docType === 'cnpj' ? '00.000.000/0001-00' : '000.000.000-00'"
                            @input="onDocInput($event)"
                            autocomplete="off"
                            required
                        >
                        <div x-show="loading" class="absolute inset-y-0 right-2 flex items-center pointer-events-none">
                            <svg class="animate-spin w-4 h-4 text-primary-500" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                        </div>
                    </div>
                    @error('document') <p class="form-error">{{ $message }}</p> @enderror
                    <p x-show="apiError" x-text="apiError" class="mt-1 text-xs text-red-500 dark:text-red-400"></p>
                </div>
            </div>

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

<div class="flex items-center justify-end gap-3 mt-6" x-data="{ submitting: false }">
    <a href="{{ route('companies.index') }}" class="btn-secondary" x-bind:class="{ 'pointer-events-none opacity-50': submitting }">Cancelar</a>
    <button
        type="submit"
        class="btn-primary inline-flex items-center gap-2"
        :disabled="submitting"
        :class="{ 'opacity-75 cursor-not-allowed': submitting }"
        @click="submitting = true; $nextTick(() => $el.closest('form').submit())"
    >
        <svg x-show="submitting" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
        </svg>
        <x-heroicon-s-check x-show="!submitting" class="w-4 h-4" />
        <span x-text="submitting ? 'Salvando...' : '{{ $company ? 'Salvar Alteracoes' : 'Cadastrar Empresa' }}'"></span>
    </button>
</div>
