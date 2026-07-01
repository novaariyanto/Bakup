@props(['title' => null, 'description' => null])

<div {{ $attributes->merge(['class' => 'card']) }}>
    @if ($title || $description)
        <div class="border-b border-zinc-800 px-5 py-4">
            @if ($title)
                <h3 class="text-sm font-semibold text-zinc-100">{{ $title }}</h3>
            @endif
            @if ($description)
                <p class="mt-0.5 text-xs text-zinc-500">{{ $description }}</p>
            @endif
        </div>
    @endif
    <div class="p-5">
        {{ $slot }}
    </div>
</div>
