<x-app-layout>
    <x-slot name="header">Editar Conta</x-slot>
    <x-slot name="subtitle">{{ $marketplace->account_name }}</x-slot>
    <x-slot name="breadcrumbs">
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <a href="{{ route('marketplaces.index') }}" class="hover:text-gray-700 dark:hover:text-zinc-200">Marketplaces</a>
        </li>
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <span class="text-gray-700 dark:text-zinc-200">{{ $marketplace->account_name }}</span>
        </li>
    </x-slot>

    @livewire('marketplaces.marketplace-form', ['marketplaceId' => $marketplace->id])
</x-app-layout>
