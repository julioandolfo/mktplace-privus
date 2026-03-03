<x-app-layout>
    <x-slot name="header">Nova Nota Fiscal</x-slot>
    <x-slot name="breadcrumbs">
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <a href="{{ route('invoices.index') }}" class="hover:text-gray-700 dark:hover:text-zinc-200">Notas Fiscais</a>
        </li>
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <span class="text-gray-700 dark:text-zinc-200">Nova</span>
        </li>
    </x-slot>

    <livewire:invoices.invoice-form />
</x-app-layout>
