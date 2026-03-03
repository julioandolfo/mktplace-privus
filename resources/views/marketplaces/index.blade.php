<x-app-layout>
    <x-slot name="header">Marketplaces</x-slot>
    <x-slot name="subtitle">Gerencie suas contas de marketplace</x-slot>
    <x-slot name="actions">
        <a href="{{ route('marketplaces.create') }}" class="btn-primary">
            <x-heroicon-s-plus class="w-4 h-4" />
            Nova Conta
        </a>
    </x-slot>
    <x-slot name="breadcrumbs">
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <span class="text-gray-700 dark:text-zinc-200">Marketplaces</span>
        </li>
    </x-slot>

    <livewire:marketplaces.marketplace-list />
</x-app-layout>
