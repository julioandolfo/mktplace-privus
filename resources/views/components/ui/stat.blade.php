@props(['label', 'value'])

<div class="flex flex-col">
    <dt class="text-sm font-medium text-gray-500 dark:text-zinc-400">{{ $label }}</dt>
    <dd class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ $value }}</dd>
</div>
