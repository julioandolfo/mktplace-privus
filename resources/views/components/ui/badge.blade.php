@props(['color' => 'neutral'])

@php
$classes = match($color) {
    'success', 'green' => 'badge-success',
    'warning', 'yellow' => 'badge-warning',
    'danger', 'red' => 'badge-danger',
    'info', 'blue' => 'badge-info',
    default => 'badge-neutral',
};
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</span>
