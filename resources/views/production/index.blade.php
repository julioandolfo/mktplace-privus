<x-app-layout>
    <x-slot name="header">Producao</x-slot>
    <x-slot name="subtitle">Gerencie a fila de producao dos pedidos</x-slot>
    <x-slot name="breadcrumbs">
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <span class="text-gray-700 dark:text-zinc-200">Producao</span>
        </li>
    </x-slot>

    <livewire:orders.production-board />
</x-app-layout>
