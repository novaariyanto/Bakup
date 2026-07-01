<div class="space-y-5">
    <div class="flex items-center justify-between gap-3">
        <div>
            <p class="text-sm font-medium text-zinc-200" x-text="progress.profile_name || 'Backup'"></p>
            <p class="text-xs text-zinc-500" x-text="progress.current_stage_label || 'Menunggu worker...'"></p>
        </div>
        <span
            class="badge"
            :class="'badge-' + (progress.status_color || 'zinc')"
            x-text="progress.status_label || 'Unknown'"
        ></span>
    </div>

    <div>
        <div class="mb-2 flex items-center justify-between text-xs text-zinc-500">
            <span>Progress</span>
            <span x-text="(progress.percent ?? 0) + '%'"></span>
        </div>
        <div class="h-2 overflow-hidden rounded-full bg-zinc-800">
            <div
                class="h-full rounded-full bg-indigo-500 transition-all duration-500"
                :class="{ 'animate-pulse': progress.is_running && ! progress.is_finished }"
                :style="'width: ' + (progress.percent ?? 0) + '%'"
            ></div>
        </div>
    </div>

    <ol class="space-y-2">
        <template x-for="stage in progress.stages ?? []" :key="stage.key">
            <li class="flex items-center gap-3 text-sm">
                <template x-if="stage.state === 'completed'">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-500/15 text-emerald-400">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                    </span>
                </template>
                <template x-if="stage.state === 'current'">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-indigo-500/15 text-indigo-400">
                        <span class="h-2 w-2 animate-pulse rounded-full bg-indigo-400"></span>
                    </span>
                </template>
                <template x-if="stage.state === 'pending'">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-zinc-800 text-zinc-500">
                        <span class="h-2 w-2 rounded-full bg-zinc-600"></span>
                    </span>
                </template>
                <span
                    :class="{
                        'text-zinc-200': stage.state === 'current',
                        'text-zinc-400': stage.state === 'completed',
                        'text-zinc-500': stage.state === 'pending',
                    }"
                    x-text="stage.label"
                ></span>
            </li>
        </template>
    </ol>

    <template x-if="progress.is_finished && progress.status === 'success'">
        <div class="rounded-lg border border-emerald-500/20 bg-emerald-500/10 p-4 text-sm text-emerald-300">
            <p class="font-medium">Backup selesai</p>
            <template x-if="progress.filename">
                <p class="mt-1 text-xs opacity-80" x-text="'File: ' + progress.filename"></p>
            </template>
            <template x-if="progress.duration_seconds">
                <p class="mt-1 text-xs opacity-80" x-text="'Durasi: ' + progress.duration_seconds + ' detik'"></p>
            </template>
        </div>
    </template>

    <template x-if="progress.is_finished && progress.status === 'failed'">
        <div class="rounded-lg border border-red-500/20 bg-red-500/10 p-4 text-sm text-red-300">
            <p class="font-medium">Backup gagal</p>
            <p class="mt-1 text-xs opacity-90" x-text="progress.message || 'Terjadi kesalahan saat backup.'"></p>
        </div>
    </template>
</div>
