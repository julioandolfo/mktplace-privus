<x-app-layout>
    <x-slot name="header">Expedicao</x-slot>
    <x-slot name="subtitle">Gerencie envios e rastreamento de pedidos</x-slot>
    <x-slot name="breadcrumbs">
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <span class="text-gray-700 dark:text-zinc-200">Expedicao</span>
        </li>
    </x-slot>

    <livewire:orders.expedition-board />
</x-app-layout>
