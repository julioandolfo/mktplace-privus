@props(['title', 'description' => null, 'icon' => null])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center py-12 px-4']) }}>
    @if($icon)
    <div class="w-16 h-16 rounded-full bg-gray-100 dark:bg-zinc-800 flex items-center justify-center mb-4">
        {{ $icon }}
    </div>
    @endif
    <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ $title }}</h3>
    @if($description)
    <p class="mt-1 text-sm text-gray-500 dark:text-zinc-400 text-center max-w-sm">{{ $description }}</p>
    @endif
    @isset($action)
    <div class="mt-4">{{ $action }}</div>
    @endisset
</div>
