<x-app-layout>
    <x-slot name="header">Compras</x-slot>
    <x-slot name="subtitle">Solicitações de compra e acompanhamento</x-slot>
    <x-slot name="breadcrumbs">
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <span class="text-gray-700 dark:text-zinc-200">Compras</span>
        </li>
    </x-slot>

    <livewire:purchases.purchases-board />
</x-app-layout>
