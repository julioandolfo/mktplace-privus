<x-app-layout>
    <x-slot name="header">Estoque</x-slot>
    <x-slot name="subtitle">Controle de estoque por produto e local</x-slot>
    <x-slot name="breadcrumbs">
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <span class="text-gray-700 dark:text-zinc-200">Estoque</span>
        </li>
    </x-slot>

    <livewire:stock.stock-overview />
</x-app-layout>
