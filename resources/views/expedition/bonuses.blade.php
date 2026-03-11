<x-app-layout>
    <x-slot name="header">Bonificação Expedição</x-slot>
    <x-slot name="subtitle">Relatório mensal de pontos e bonificação dos operadores</x-slot>
    <x-slot name="breadcrumbs">
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <a href="{{ route('expedition.index') }}" class="hover:text-gray-700 dark:hover:text-zinc-200">Expedição</a>
        </li>
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <span class="text-gray-700 dark:text-zinc-200">Bonificação</span>
        </li>
    </x-slot>

    <livewire:expedition.bonus-report />
</x-app-layout>
