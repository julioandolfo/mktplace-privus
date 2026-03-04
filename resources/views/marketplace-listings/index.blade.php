<x-app-layout>
    <x-slot name="header">Anuncios</x-slot>
    <x-slot name="subtitle">Listings importados dos marketplaces</x-slot>
    <x-slot name="breadcrumbs">
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <span class="text-gray-700 dark:text-zinc-200">Anuncios</span>
        </li>
    </x-slot>

    <div class="space-y-4">
        {{-- Filters --}}
        <div class="card p-4">
            <form method="GET" class="flex flex-wrap gap-3">
                <input type="text" name="search" value="{{ request('search') }}"
                    placeholder="Buscar por título ou ID..." class="form-input flex-1 min-w-48">

                <select name="account" class="form-input w-48">
                    <option value="">Todas as contas</option>
                    @foreach($accounts as $acc)
                        <option value="{{ $acc->id }}" @selected(request('account') == $acc->id)>
                            {{ $acc->account_name }}
                        </option>
                    @endforeach
                </select>

                <select name="status" class="form-input w-36">
                    <option value="">Todos os status</option>
                    @foreach(['active' => 'Ativo', 'paused' => 'Pausado', 'closed' => 'Encerrado', 'deleted' => 'Deletado'] as $val => $label)
                        <option value="{{ $val }}" @selected(request('status') === $val)>{{ $label }}</option>
                    @endforeach
                </select>

                <select name="linked" class="form-input w-40">
                    <option value="">Com e sem produto</option>
                    <option value="1" @selected(request('linked') === '1')>Com produto vinculado</option>
                    <option value="0" @selected(request('linked') === '0')>Sem produto vinculado</option>
                </select>

                <button type="submit" class="btn-primary">
                    <x-heroicon-o-magnifying-glass class="w-4 h-4" />
                    Filtrar
                </button>
                @if(request()->hasAny(['search','account','status','linked']))
                <a href="{{ route('listings.index') }}" class="btn-secondary">Limpar</a>
                @endif
            </form>
        </div>

        {{-- Table --}}
        <div class="card overflow-hidden">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Anuncio</th>
                        <th>Conta</th>
                        <th>Status ML</th>
                        <th>Preco</th>
                        <th>Estoque</th>
                        <th>Produto Vinculado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($listings as $listing)
                    <tr>
                        <td>
                            <div class="font-medium text-gray-900 dark:text-white">{{ $listing->title }}</div>
                            <div class="text-xs font-mono text-gray-400 dark:text-zinc-500">{{ $listing->external_id }}</div>
                        </td>
                        <td class="text-sm">{{ $listing->marketplaceAccount?->account_name ?? '—' }}</td>
                        <td>
                            <x-ui.badge :color="$listing->status_color">
                                {{ match($listing->status) {
                                    'active'  => 'Ativo',
                                    'paused'  => 'Pausado',
                                    'closed'  => 'Encerrado',
                                    'deleted' => 'Deletado',
                                    default   => $listing->status,
                                } }}
                            </x-ui.badge>
                        </td>
                        <td class="font-mono">R$ {{ number_format($listing->price, 2, ',', '.') }}</td>
                        <td>{{ $listing->available_quantity ?? '—' }}</td>
                        <td>
                            @if($listing->product)
                                <div class="flex items-center gap-1.5">
                                    <x-heroicon-s-check-circle class="w-3.5 h-3.5 text-emerald-500" />
                                    <span class="text-sm">{{ $listing->product->name }}</span>
                                    @if($listing->product_quantity > 1)
                                        <span class="badge-info text-xs">×{{ $listing->product_quantity }}</span>
                                    @endif
                                </div>
                            @else
                                <span class="badge-warning">Sem vinculo</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('listings.show', $listing) }}" class="btn-ghost btn-sm">
                                <x-heroicon-o-link class="w-4 h-4" />
                                Vincular
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-12 text-gray-500 dark:text-zinc-400">
                            Nenhum anúncio encontrado. Sincronize uma conta para importar.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $listings->links() }}
    </div>
</x-app-layout>
