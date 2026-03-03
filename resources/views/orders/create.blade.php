<x-app-layout>
    <x-slot name="header">Novo Pedido</x-slot>
    <x-slot name="breadcrumbs">
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <a href="{{ route('orders.index') }}" class="hover:text-gray-700 dark:hover:text-zinc-200">Pedidos</a>
        </li>
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <span class="text-gray-700 dark:text-zinc-200">Novo</span>
        </li>
    </x-slot>

    <livewire:orders.order-form />
</x-app-layout>
