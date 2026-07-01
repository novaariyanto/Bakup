<div class="animate-pulse space-y-3">
    @for ($i = 0; $i < 5; $i++)
        <div class="flex gap-4 rounded-lg border border-zinc-800 bg-zinc-900/40 p-4">
            <div class="h-10 w-10 rounded-lg bg-zinc-800"></div>
            <div class="flex-1 space-y-2">
                <div class="h-4 w-1/3 rounded bg-zinc-800"></div>
                <div class="h-3 w-1/2 rounded bg-zinc-800"></div>
            </div>
        </div>
    @endfor
</div>
