<x-app-layout>
    <x-slot name="header">Produtos</x-slot>
    <x-slot name="subtitle">Gerencie seu catalogo de produtos</x-slot>
    <x-slot name="actions">
        <a href="{{ route('products.create') }}" class="btn-primary">
            <x-heroicon-s-plus class="w-4 h-4" />
            Novo Produto
        </a>
    </x-slot>
    <x-slot name="breadcrumbs">
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <span class="text-gray-700 dark:text-zinc-200">Produtos</span>
        </li>
    </x-slot>

    <livewire:products.product-list />
</x-app-layout>
