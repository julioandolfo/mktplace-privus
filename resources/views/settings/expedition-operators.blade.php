<x-app-layout>
    <x-slot name="header">Operadores de Expedição</x-slot>
    <x-slot name="subtitle">Cadastre as pessoas que conferem, embalam e despacham pedidos</x-slot>
    <x-slot name="breadcrumbs">
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <a href="{{ route('settings.index') }}" class="hover:text-gray-700 dark:hover:text-zinc-200">Configurações</a>
        </li>
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <span class="text-gray-700 dark:text-zinc-200">Operadores de Expedição</span>
        </li>
    </x-slot>

    <livewire:settings.expedition-operators />
</x-app-layout>
