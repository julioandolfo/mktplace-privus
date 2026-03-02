@props(['title' => null, 'padding' => true])

<div {{ $attributes->merge(['class' => 'card']) }}>
    @if($title)
    <div class="px-5 py-4 border-b border-gray-200 dark:border-zinc-700 flex items-center justify-between">
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ $title }}</h3>
        @isset($headerActions)
        <div class="flex items-center gap-2">{{ $headerActions }}</div>
        @endisset
    </div>
    @endif
    <div @class([$padding ? 'p-5' : ''])>
        {{ $slot }}
    </div>
    @isset($footer)
    <div class="px-5 py-3 border-t border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800/50 rounded-b-xl">
        {{ $footer }}
    </div>
    @endisset
</div>
