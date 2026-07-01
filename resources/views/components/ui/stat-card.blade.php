@props(['label', 'value', 'change' => null, 'icon' => null])

<div {{ $attributes->merge(['class' => 'card p-5']) }}>
    <div class="flex items-start justify-between">
        <div>
            <p class="text-sm font-medium text-zinc-400">{{ $label }}</p>
            <p class="mt-2 text-2xl font-semibold tracking-tight text-zinc-100">{{ $value }}</p>
            @if ($change)
                <p class="mt-1 text-xs text-zinc-500">{{ $change }}</p>
            @endif
        </div>
        @if ($icon)
            <div class="rounded-lg bg-zinc-800/80 p-2.5 text-zinc-400">
                {!! $icon !!}
            </div>
        @endif
    </div>
</div>
