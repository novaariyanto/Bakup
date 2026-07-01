@props(['progress'])

@php
    $percent = $progress['percent'] ?? 0;
    $isFinished = $progress['is_finished'] ?? false;
    $isRunning = $progress['is_running'] ?? false;
@endphp

<div class="space-y-5">
    <div class="flex items-center justify-between gap-3">
        <div>
            <p class="text-sm font-medium text-zinc-200">{{ $progress['profile_name'] ?? 'Backup' }}</p>
            <p class="text-xs text-zinc-500">
                @if ($progress['current_stage_label'] ?? null)
                    {{ $progress['current_stage_label'] }}
                @else
                    Menunggu worker...
                @endif
            </p>
        </div>
        <x-ui.badge :color="$progress['status_color'] ?? 'zinc'">
            {{ $progress['status_label'] ?? 'Unknown' }}
        </x-ui.badge>
    </div>

    <div>
        <div class="mb-2 flex items-center justify-between text-xs text-zinc-500">
            <span>Progress</span>
            <span>{{ $percent }}%</span>
        </div>
        <div class="h-2 overflow-hidden rounded-full bg-zinc-800">
            <div
                class="h-full rounded-full bg-indigo-500 transition-all duration-500 {{ $isRunning && ! $isFinished ? 'animate-pulse' : '' }}"
                style="width: {{ $percent }}%"
            ></div>
        </div>
    </div>

    <ol class="space-y-2">
        @foreach ($progress['stages'] ?? [] as $stage)
            <li class="flex items-center gap-3 text-sm">
                @if ($stage['state'] === 'completed')
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-500/15 text-emerald-400">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                    </span>
                @elseif ($stage['state'] === 'current')
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-indigo-500/15 text-indigo-400">
                        <span class="h-2 w-2 animate-pulse rounded-full bg-indigo-400"></span>
                    </span>
                @else
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-zinc-800 text-zinc-500">
                        <span class="h-2 w-2 rounded-full bg-zinc-600"></span>
                    </span>
                @endif
                <span @class([
                    'text-zinc-200' => $stage['state'] === 'current',
                    'text-zinc-400' => $stage['state'] === 'completed',
                    'text-zinc-500' => $stage['state'] === 'pending',
                ])>{{ $stage['label'] }}</span>
            </li>
        @endforeach
    </ol>

    @if ($isFinished)
        <div @class([
            'rounded-lg border p-4 text-sm',
            'border-emerald-500/20 bg-emerald-500/10 text-emerald-300' => ($progress['status'] ?? '') === 'success',
            'border-red-500/20 bg-red-500/10 text-red-300' => ($progress['status'] ?? '') === 'failed',
        ])>
            @if (($progress['status'] ?? '') === 'success')
                <p class="font-medium">Backup selesai</p>
                @if ($progress['filename'] ?? null)
                    <p class="mt-1 text-xs opacity-80">File: {{ $progress['filename'] }}</p>
                @endif
                @if ($progress['duration_seconds'] ?? null)
                    <p class="mt-1 text-xs opacity-80">Durasi: {{ $progress['duration_seconds'] }} detik</p>
                @endif
            @else
                <p class="font-medium">Backup gagal</p>
                <p class="mt-1 text-xs opacity-90">{{ $progress['message'] ?? 'Terjadi kesalahan saat backup.' }}</p>
            @endif
        </div>
    @endif
</div>
