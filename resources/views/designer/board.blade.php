<x-app-layout>
    <x-slot name="header">Designer — Board</x-slot>
    <x-slot name="breadcrumbs">
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <span class="text-gray-700 dark:text-zinc-200">Designer</span>
        </li>
    </x-slot>
    <x-slot name="actions">
        @if(auth()->user()->isAdmin())
        <a href="{{ route('settings.designers.index') }}" class="btn-secondary">
            <x-heroicon-o-cog-6-tooth class="w-4 h-4" />
            Configurar
        </a>
        @endif
    </x-slot>

    <livewire:designer.designer-board />
</x-app-layout>
