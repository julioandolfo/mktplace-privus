<x-app-layout>
    <x-slot name="header">Kits</x-slot>
    <x-slot name="subtitle">Gerencie kits compostos por outros produtos</x-slot>
    <x-slot name="actions">
        <a href="{{ route('products.create') }}?type=kit" class="btn-primary">
            <x-heroicon-s-plus class="w-4 h-4" />
            Novo Kit
        </a>
    </x-slot>
    <x-slot name="breadcrumbs">
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <span class="text-gray-700 dark:text-zinc-200">Kits</span>
        </li>
    </x-slot>

    @php
        $kits = \App\Models\Product::where('type', 'kit')
            ->with(['kitItems.componentProduct', 'primaryImage'])
            ->orderBy('name')
            ->get();
    @endphp

    @if($kits->isEmpty())
        <x-ui.card>
            <x-ui.empty-state
                title="Nenhum kit cadastrado"
                description="Crie um produto do tipo Kit e adicione componentes a ele.">
                <x-slot name="icon">
                    <x-heroicon-o-rectangle-group class="w-8 h-8 text-gray-400 dark:text-zinc-500" />
                </x-slot>
                <x-slot name="action">
                    <a href="{{ route('products.create') }}?type=kit" class="btn-primary">
                        <x-heroicon-s-plus class="w-4 h-4" />
                        Criar Kit
                    </a>
                </x-slot>
            </x-ui.empty-state>
        </x-ui.card>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($kits as $kit)
            <div class="card p-5">
                <div class="flex items-start gap-4 mb-4">
                    @if($kit->primaryImage)
                        <img src="{{ asset('storage/' . $kit->primaryImage->path) }}" alt="" class="w-16 h-16 rounded-lg object-cover flex-shrink-0">
                    @else
                        <div class="w-16 h-16 rounded-lg bg-gray-100 dark:bg-zinc-700 flex items-center justify-center flex-shrink-0">
                            <x-heroicon-o-rectangle-group class="w-8 h-8 text-gray-400 dark:text-zinc-500" />
                        </div>
                    @endif
                    <div class="min-w-0">
                        <h3 class="font-semibold text-gray-900 dark:text-white truncate">{{ $kit->name }}</h3>
                        <p class="text-sm text-gray-500 dark:text-zinc-400 font-mono">{{ $kit->sku }}</p>
                        <p class="text-lg font-bold text-gray-900 dark:text-white mt-1">R$ {{ number_format($kit->price, 2, ',', '.') }}</p>
                    </div>
                </div>

                <div class="border-t border-gray-200 dark:border-zinc-700 pt-3">
                    <p class="text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase mb-2">Componentes ({{ $kit->kitItems->count() }})</p>
                    <div class="space-y-1.5">
                        @foreach($kit->kitItems as $item)
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-700 dark:text-zinc-300 truncate">{{ $item->componentProduct->name }}</span>
                            <span class="text-gray-500 dark:text-zinc-400 flex-shrink-0 ml-2">x{{ $item->quantity }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 mt-4 pt-3 border-t border-gray-200 dark:border-zinc-700">
                    <x-ui.badge :color="$kit->status->color()">{{ $kit->status->label() }}</x-ui.badge>
                    <a href="{{ route('products.edit', $kit) }}" class="btn-ghost btn-sm">
                        <x-heroicon-o-pencil-square class="w-4 h-4" />
                        Editar
                    </a>
                </div>
            </div>
            @endforeach
        </div>
    @endif
</x-app-layout>
