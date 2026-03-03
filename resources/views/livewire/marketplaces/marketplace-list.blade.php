<div>
    {{-- Stats --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Total de Contas</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ $counts['total'] }}</p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                    <x-heroicon-o-globe-alt class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                </div>
            </div>
        </div>
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Ativas</p>
                    <p class="text-2xl font-bold text-green-600 dark:text-green-400 mt-1">{{ $counts['active'] }}</p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                    <x-heroicon-o-check-circle class="w-5 h-5 text-green-600 dark:text-green-400" />
                </div>
            </div>
        </div>
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Com Erro</p>
                    <p class="text-2xl font-bold {{ $counts['error'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white' }} mt-1">{{ $counts['error'] }}</p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-red-100 dark:bg-red-900/30 flex items-center justify-center">
                    <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-red-600 dark:text-red-400" />
                </div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card p-4 mb-6">
        <div class="flex flex-col sm:flex-row gap-4">
            <div class="flex-1">
                <div class="relative">
                    <x-heroicon-o-magnifying-glass class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-zinc-500" />
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Buscar por nome ou shop ID..."
                           class="form-input pl-10">
                </div>
            </div>
            <div class="flex gap-3">
                <select wire:model.live="status" class="form-input w-36">
                    <option value="">Todos Status</option>
                    @foreach($statuses as $s)
                        <option value="{{ $s->value }}">{{ $s->label() }}</option>
                    @endforeach
                </select>
                <select wire:model.live="type" class="form-input w-44">
                    <option value="">Todos Marketplaces</option>
                    @foreach($types as $t)
                        <option value="{{ $t->value }}">{{ $t->label() }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    {{-- Table --}}
    @if($accounts->isEmpty())
        <x-ui.card>
            <x-ui.empty-state
                title="Nenhuma conta de marketplace"
                description="{{ $search ? 'Tente refinar sua busca.' : 'Conecte seu primeiro marketplace para comecar a vender.' }}">
                <x-slot name="icon">
                    <x-heroicon-o-globe-alt class="w-8 h-8 text-gray-400 dark:text-zinc-500" />
                </x-slot>
                @unless($search)
                <x-slot name="action">
                    <a href="{{ route('marketplaces.create') }}" class="btn-primary">
                        <x-heroicon-s-plus class="w-4 h-4" />
                        Nova Conta
                    </a>
                </x-slot>
                @endunless
            </x-ui.empty-state>
        </x-ui.card>
    @else
        <x-ui.card :padding="false">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Marketplace</th>
                        <th>
                            <button wire:click="sortBy('account_name')" class="flex items-center gap-1 hover:text-gray-700 dark:hover:text-zinc-200">
                                Conta
                                @if($sortField === 'account_name')
                                    <x-heroicon-s-chevron-up class="w-3 h-3 {{ $sortDirection === 'desc' ? 'rotate-180' : '' }}" />
                                @endif
                            </button>
                        </th>
                        <th>Empresa</th>
                        <th>Status</th>
                        <th>Ultima Sync</th>
                        <th>
                            <button wire:click="sortBy('created_at')" class="flex items-center gap-1 hover:text-gray-700 dark:hover:text-zinc-200">
                                Criado em
                                @if($sortField === 'created_at')
                                    <x-heroicon-s-chevron-up class="w-3 h-3 {{ $sortDirection === 'desc' ? 'rotate-180' : '' }}" />
                                @endif
                            </button>
                        </th>
                        <th class="text-right">Acoes</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($accounts as $account)
                    <tr wire:key="mp-{{ $account->id }}">
                        <td>
                            <div class="flex items-center gap-2">
                                <span class="w-3 h-3 rounded-full flex-shrink-0" style="background-color: {{ $account->marketplace_type->color() }}"></span>
                                <span class="font-medium">{{ $account->marketplace_type->label() }}</span>
                            </div>
                        </td>
                        <td>
                            <a href="{{ route('marketplaces.show', $account) }}" class="font-medium text-gray-900 dark:text-white hover:text-primary-600 dark:hover:text-primary-400">
                                {{ $account->account_name }}
                            </a>
                            @if($account->shop_id)
                            <p class="text-xs font-mono text-gray-500 dark:text-zinc-400">{{ $account->shop_id }}</p>
                            @endif
                        </td>
                        <td class="text-sm text-gray-600 dark:text-zinc-400">
                            {{ $account->company?->name ?? '-' }}
                        </td>
                        <td>
                            <x-ui.badge :color="$account->status->color()">{{ $account->status->label() }}</x-ui.badge>
                            @if($account->status->value === 'active' && $account->isTokenExpired())
                                <p class="text-xs text-yellow-600 dark:text-yellow-400 mt-0.5">Token expirado</p>
                            @endif
                        </td>
                        <td class="text-sm text-gray-500 dark:text-zinc-400">
                            @if($account->last_synced_at)
                                {{ $account->last_synced_at->diffForHumans() }}
                            @else
                                <span class="text-gray-400 dark:text-zinc-500">Nunca</span>
                            @endif
                        </td>
                        <td class="text-sm text-gray-500 dark:text-zinc-400">
                            {{ $account->created_at->format('d/m/Y') }}
                        </td>
                        <td class="text-right">
                            <div class="flex items-center justify-end gap-1" x-data="{ open: false }">
                                <a href="{{ route('marketplaces.show', $account) }}" class="btn-ghost btn-xs">
                                    <x-heroicon-o-eye class="w-4 h-4" />
                                </a>
                                <div class="relative">
                                    <button @click="open = !open" class="btn-ghost btn-xs">
                                        <x-heroicon-o-ellipsis-vertical class="w-4 h-4" />
                                    </button>
                                    <div x-show="open" @click.away="open = false" x-transition
                                         class="absolute right-0 mt-1 w-44 bg-white dark:bg-zinc-800 rounded-lg shadow-lg border border-gray-200 dark:border-zinc-700 py-1 z-10">
                                        <a href="{{ route('marketplaces.edit', $account) }}" @click="open = false"
                                           class="w-full flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-zinc-300 hover:bg-gray-100 dark:hover:bg-zinc-700">
                                            <x-heroicon-o-pencil-square class="w-4 h-4" />
                                            Editar
                                        </a>
                                        <button wire:click="toggleStatus({{ $account->id }})" @click="open = false"
                                                class="w-full flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-zinc-300 hover:bg-gray-100 dark:hover:bg-zinc-700">
                                            @if($account->status->value === 'active')
                                                <x-heroicon-o-pause class="w-4 h-4" />
                                                Desativar
                                            @else
                                                <x-heroicon-o-play class="w-4 h-4" />
                                                Ativar
                                            @endif
                                        </button>
                                        <button wire:click="deleteAccount({{ $account->id }})" wire:confirm="Tem certeza que deseja remover esta conta?" @click="open = false"
                                                class="w-full flex items-center gap-2 px-3 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-gray-100 dark:hover:bg-zinc-700">
                                            <x-heroicon-o-trash class="w-4 h-4" />
                                            Remover
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            @if($accounts->hasPages())
            <div class="px-4 py-3 border-t border-gray-200 dark:border-zinc-700">
                {{ $accounts->links() }}
            </div>
            @endif
        </x-ui.card>
    @endif
</div>
