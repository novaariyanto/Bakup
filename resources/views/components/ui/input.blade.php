@props(['label', 'name', 'type' => 'text', 'value' => null])

<div>
    <label for="{{ $name }}" class="mb-1.5 block text-sm font-medium text-zinc-300">{{ $label }}</label>
    <input
        type="{{ $type }}"
        name="{{ $name }}"
        id="{{ $name }}"
        value="{{ old($name, $value) }}"
        {{ $attributes->merge(['class' => 'input-field']) }}
    />
    @error($name)
        <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p>
    @enderror
</div>
