<x-app-layout>
    <x-slot name="header">Notas Fiscais</x-slot>
    <x-slot name="subtitle">Gerencie suas notas fiscais eletronicas</x-slot>
    <x-slot name="actions">
        <a href="{{ route('invoices.create') }}" class="btn-primary">
            <x-heroicon-s-plus class="w-4 h-4" />
            Nova Nota Fiscal
        </a>
    </x-slot>
    <x-slot name="breadcrumbs">
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <span class="text-gray-700 dark:text-zinc-200">Notas Fiscais</span>
        </li>
    </x-slot>

    <livewire:invoices.invoice-list />
</x-app-layout>
