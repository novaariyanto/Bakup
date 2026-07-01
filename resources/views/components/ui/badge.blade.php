@props(['color' => 'zinc'])

<span {{ $attributes->merge(['class' => "badge badge-{$color}"]) }}>
    {{ $slot }}
</span>
