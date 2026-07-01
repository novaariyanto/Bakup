@props(['type' => 'info', 'message'])

@php
    $styles = match ($type) {
        'success' => 'border-emerald-500/20 bg-emerald-500/10 text-emerald-300',
        'warning' => 'border-amber-500/20 bg-amber-500/10 text-amber-300',
        'error' => 'border-red-500/20 bg-red-500/10 text-red-300',
        default => 'border-blue-500/20 bg-blue-500/10 text-blue-300',
    };
@endphp

<div {{ $attributes->merge(['class' => "rounded-lg border px-4 py-3 text-sm {$styles}"]) }}>
    {{ $message }}
</div>
