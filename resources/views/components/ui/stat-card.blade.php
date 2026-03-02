@props(['title', 'value', 'icon' => null, 'change' => null, 'changeType' => 'up', 'color' => 'primary'])

<div class="stat-card">
    <div class="flex items-center justify-between">
        <div class="min-w-0">
            <p class="text-sm font-medium text-gray-500 dark:text-zinc-400 truncate">{{ $title }}</p>
            <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ $value }}</p>
            @if($change)
            <p class="mt-1 text-sm flex items-center gap-1 {{ $changeType === 'up' ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                @if($changeType === 'up')
                    <x-heroicon-s-arrow-trending-up class="w-4 h-4" />
                @else
                    <x-heroicon-s-arrow-trending-down class="w-4 h-4" />
                @endif
                {{ $change }}
            </p>
            @endif
        </div>
        @if($icon)
        <div class="p-3 rounded-lg bg-{{ $color }}-100 dark:bg-{{ $color }}-500/20 flex-shrink-0">
            {{ $icon }}
        </div>
        @endif
    </div>
</div>
