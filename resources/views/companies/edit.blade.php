<x-app-layout>
    <x-slot name="header">Editar Empresa</x-slot>
    <x-slot name="subtitle">{{ $company->name }}</x-slot>
    <x-slot name="breadcrumbs">
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <a href="{{ route('companies.index') }}" class="hover:text-gray-700 dark:hover:text-zinc-200">Empresas</a>
        </li>
        <li class="flex items-center gap-2">
            <x-heroicon-s-chevron-right class="w-4 h-4" />
            <span class="text-gray-700 dark:text-zinc-200">{{ $company->name }}</span>
        </li>
    </x-slot>

    <form
        method="POST"
        action="{{ route('companies.update', $company) }}"
        x-data="companyForm()"
        @submit.prevent="submitForm"
    >
        @csrf
        @method('PUT')
        @include('companies._form', ['company' => $company])
    </form>
</x-app-layout>
