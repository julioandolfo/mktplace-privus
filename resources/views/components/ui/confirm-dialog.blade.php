@props(['name', 'title' => 'Confirmar acao', 'message' => 'Tem certeza que deseja continuar?', 'confirmText' => 'Confirmar', 'cancelText' => 'Cancelar', 'destructive' => false])

<x-ui.modal :name="$name" :title="$title" maxWidth="sm">
    <p class="text-sm text-gray-600 dark:text-zinc-400">{{ $message }}</p>

    <x-slot:footer>
        <button @click="$dispatch('close-modal', '{{ $name }}')" class="btn-secondary">{{ $cancelText }}</button>
        <button @click="{{ $attributes->get('x-on:confirm', '') }}" class="{{ $destructive ? 'btn-danger' : 'btn-primary' }}">
            {{ $confirmText }}
        </button>
    </x-slot:footer>
</x-ui.modal>
