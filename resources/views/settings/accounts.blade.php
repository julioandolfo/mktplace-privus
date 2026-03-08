<x-app-layout>
    <x-slot name="header">Configurações de Contas de Marketplace</x-slot>
    <x-slot name="breadcrumbs">
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <a href="{{ route('settings.index') }}" class="hover:text-gray-700 dark:hover:text-zinc-200">Configurações</a>
        </li>
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <span class="text-gray-700 dark:text-zinc-200">Contas de Marketplace</span>
        </li>
    </x-slot>

    <div class="space-y-6">
        <p class="text-sm text-gray-600 dark:text-zinc-400">
            Configure os vínculos de cada canal de vendas com Webmaniabr (NF-e), Melhor Envios e as opções de expedição.
        </p>

        @foreach($accounts as $account)
        <x-ui.card>
            <x-slot name="title">
                <div class="flex items-center gap-3">
                    <x-ui.badge :color="$account->marketplace_type->color()">
                        {{ $account->marketplace_type->label() }}
                    </x-ui.badge>
                    <span>{{ $account->account_name }}</span>
                    @if(!$account->is_active)
                        <x-ui.badge color="default">Inativo</x-ui.badge>
                    @endif
                </div>
            </x-slot>

            <form method="POST" action="{{ route('settings.accounts.update', $account) }}" class="space-y-5">
                @csrf @method('PUT')

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    {{-- NF-e — Webmaniabr --}}
                    <div>
                        <label class="form-label">
                            <x-heroicon-o-document-text class="w-4 h-4 inline mr-1" />
                            Conta Webmaniabr (NF-e)
                        </label>
                        <select name="webmania_account_id" class="form-input">
                            <option value="">— Nenhuma —</option>
                            @foreach($webmaniaAccounts as $wa)
                            <option value="{{ $wa->id }}"
                                    {{ $account->webmania_account_id == $wa->id ? 'selected' : '' }}>
                                {{ $wa->name }}
                            </option>
                            @endforeach
                        </select>
                        @if($webmaniaAccounts->isEmpty())
                        <p class="text-xs text-amber-600 dark:text-amber-400 mt-1">
                            <a href="{{ route('settings.webmania.create') }}" class="underline">Criar uma conta Webmaniabr</a>
                        </p>
                        @endif
                    </div>

                    {{-- Melhor Envios --}}
                    <div>
                        <label class="form-label">
                            <x-heroicon-o-truck class="w-4 h-4 inline mr-1" />
                            Conta Melhor Envios
                        </label>
                        <select name="melhor_envios_account_id" class="form-input">
                            <option value="">— Nenhuma —</option>
                            @foreach($melhorEnviosAccounts as $me)
                            <option value="{{ $me->id }}"
                                    {{ $account->melhor_envios_account_id == $me->id ? 'selected' : '' }}>
                                {{ $me->name }}
                            </option>
                            @endforeach
                        </select>
                        @if($melhorEnviosAccounts->isEmpty())
                        <p class="text-xs text-gray-400 dark:text-zinc-500 mt-1">Nenhuma conta ME cadastrada.</p>
                        @endif
                    </div>

                    {{-- WooCommerce — status personalizados --}}
                    @if($account->marketplace_type->value === 'woocommerce')
                    <div>
                        <label class="form-label">Status "Pronto para Envio" (WooCommerce)</label>
                        <input type="text" name="woo_ready_to_ship_status"
                               value="{{ ($account->settings ?? [])['woo_ready_to_ship_status'] ?? '' }}"
                               placeholder="Ex: processing ou wc-pronto-para-envio" class="form-input font-mono text-sm">
                        <p class="text-xs text-gray-400 mt-1">
                            Slug do status customizado. Deixe em branco para não atualizar.
                        </p>
                    </div>
                    <div>
                        <label class="form-label">Status "Enviado" (WooCommerce)</label>
                        <input type="text" name="woo_shipped_status"
                               value="{{ ($account->settings ?? [])['woo_shipped_status'] ?? '' }}"
                               placeholder="Ex: completed ou wc-enviado" class="form-input font-mono text-sm">
                    </div>
                    @endif

                    {{-- Expedição --}}
                    <div>
                        <label class="form-label">Formato de etiqueta de volume</label>
                        <select name="expedition_label_format" class="form-input">
                            <option value="a4" {{ (($account->settings ?? [])['expedition_label_format'] ?? 'a4') === 'a4' ? 'selected' : '' }}>
                                A4 (2 etiquetas por folha)
                            </option>
                            <option value="a6" {{ (($account->settings ?? [])['expedition_label_format'] ?? '') === 'a6' ? 'selected' : '' }}>
                                A6 (térmica / zebra)
                            </option>
                        </select>
                    </div>

                    <div class="flex items-center gap-3 pt-1">
                        <input type="checkbox" id="check_packing_{{ $account->id }}"
                               name="expedition_check_packing" value="1"
                               {{ (($account->settings ?? [])['expedition_check_packing'] ?? false) ? 'checked' : '' }}
                               class="rounded border-gray-300 dark:border-zinc-600">
                        <label for="check_packing_{{ $account->id }}" class="text-sm">
                            Exigir conferência de embalagem por bipe antes de liberar etiqueta
                        </label>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="btn-primary btn-sm">
                        <x-heroicon-o-check class="w-4 h-4" />
                        Salvar
                    </button>
                </div>
            </form>
        </x-ui.card>
        @endforeach

        @if($accounts->isEmpty())
        <x-ui.card>
            <div class="text-center py-8">
                <p class="text-gray-500 dark:text-zinc-400">Nenhuma conta de marketplace cadastrada.</p>
            </div>
        </x-ui.card>
        @endif
    </div>
</x-app-layout>
