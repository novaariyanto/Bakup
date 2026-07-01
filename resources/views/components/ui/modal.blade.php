@props(['title', 'maxWidth' => 'max-w-lg', 'show' => 'open'])

<div
    x-show="{{ $show }}"
    x-cloak
    {{ $attributes->merge(['class' => 'fixed inset-0 z-[100] flex items-center justify-center p-4']) }}
    role="dialog"
    aria-modal="true"
>
    <div class="absolute inset-0 bg-black/60" @click="{{ $show }} = false"></div>

    <div class="relative z-10 w-full {{ $maxWidth }} shadow-2xl shadow-black/50" @click.stop>
        <div class="card">
            <div class="flex items-center justify-between border-b border-zinc-800 px-5 py-4">
                <h3 class="text-sm font-semibold text-zinc-100">{{ $title }}</h3>
                <button type="button" class="rounded-lg p-1 text-zinc-400 hover:bg-zinc-800 hover:text-zinc-200" @click="{{ $show }} = false">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="p-5">
                {{ $slot }}
            </div>
        </div>
    </div>
</div>
