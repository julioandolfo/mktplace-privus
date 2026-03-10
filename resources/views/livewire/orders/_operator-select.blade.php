{{-- Seletor de operador de expedição (reutilizável nos modais) --}}
@if($expeditionOperators->isNotEmpty())
<div class="flex items-center gap-3">
    <label class="text-xs font-semibold text-gray-500 dark:text-zinc-400 uppercase tracking-wide whitespace-nowrap">
        <x-heroicon-o-user class="w-3.5 h-3.5 inline -mt-0.5" />
        Operador
    </label>
    <select wire:model="selectedOperatorId" class="form-input text-sm py-1.5 flex-1">
        <option value="">— Sem operador —</option>
        @foreach($expeditionOperators as $op)
            <option value="{{ $op->id }}">
                {{ $op->name }}{{ $op->role ? " ({$op->role})" : '' }}{{ $op->is_default ? ' ★' : '' }}
            </option>
        @endforeach
    </select>
</div>
@endif
