<x-app-layout>
    <x-slot name="header">Nova Empresa</x-slot>
    <x-slot name="breadcrumbs">
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <a href="{{ route('companies.index') }}" class="hover:text-gray-700 dark:hover:text-zinc-200">Empresas</a>
        </li>
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <span class="text-gray-700 dark:text-zinc-200">Nova</span>
        </li>
    </x-slot>

    <form method="POST" action="{{ route('companies.store') }}" enctype="multipart/form-data" x-data="{ submitting: false }" @submit="submitting = true">
        @csrf
        @include('companies._form')
    </form>
</x-app-layout>
