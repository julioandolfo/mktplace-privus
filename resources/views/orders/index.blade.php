<x-app-layout>
    <x-slot name="header">Pedidos</x-slot>
    <x-slot name="subtitle">Gerencie seus pedidos de venda</x-slot>
    <x-slot name="actions">
        <a href="{{ route('orders.create') }}" class="btn-primary">
            <x-heroicon-s-plus class="w-4 h-4" />
            Novo Pedido
        </a>
    </x-slot>
    <x-slot name="breadcrumbs">
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <span class="text-gray-700 dark:text-zinc-200">Pedidos</span>
        </li>
    </x-slot>

    <livewire:orders.order-list />
</x-app-layout>
