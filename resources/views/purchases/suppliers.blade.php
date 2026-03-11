<x-app-layout>
    <x-slot name="header">Fornecedores</x-slot>
    <x-slot name="subtitle">Gerencie seus fornecedores</x-slot>
    <x-slot name="breadcrumbs">
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <a href="{{ route('purchases.index') }}" class="hover:text-gray-700 dark:hover:text-zinc-200">Compras</a>
        </li>
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <span class="text-gray-700 dark:text-zinc-200">Fornecedores</span>
        </li>
    </x-slot>

    <livewire:purchases.suppliers-list />
</x-app-layout>
