@extends('layouts.app')

@section('title', 'Export Detail')

@section('content')
<div x-data="mydumperProgressPoll({ initialProgress: @js($progressData), progressUrl: @js($progressUrl) })">
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <a href="{{ route('mydumper-exports.index') }}" class="text-sm text-zinc-500 hover:text-zinc-300">← Kembali</a>
            <h1 class="mt-2 text-lg font-semibold text-zinc-100">{{ $export->name }}</h1>
            <p class="text-sm text-zinc-500">#{{ $export->id }} · {{ $export->database }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @can('cancel', $export)
                <form method="POST" action="{{ route('mydumper-exports.cancel', $export) }}">@csrf<button class="btn-secondary text-amber-300">Cancel</button></form>
            @endcan
            @can('retry', $export)
                <form method="POST" action="{{ route('mydumper-exports.retry', $export) }}">@csrf<button class="btn-secondary">Retry</button></form>
            @endcan
            <a href="{{ route('mydumper-exports.download-log', $export) }}" class="btn-secondary">Download Log</a>
            @can('download', $export)
                <a href="{{ route('mydumper-exports.download-metadata', $export) }}" class="btn-secondary">Download Metadata</a>
            @endcan
            @can('delete', $export)
                <form method="POST" action="{{ route('mydumper-exports.destroy', $export) }}" onsubmit="return confirm('Hapus export ini?')">@csrf @method('DELETE')<button class="btn-secondary text-red-300">Delete</button></form>
            @endcan
        </div>
    </div>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat-card label="Status" :value="$export->status->label()" />
        <div class="card p-5">
            <p class="text-sm font-medium text-zinc-400">Progress</p>
            <p class="mt-2 text-2xl font-semibold text-zinc-100" x-text="progress.progress_percent + '%'"></p>
        </div>
        <x-ui.stat-card label="Duration" :value="$export->formattedDuration() ?? '—'" />
        <x-ui.stat-card label="Size" :value="$export->formattedSize() ?? '—'" />
    </div>

    <div x-show="!progress.is_finished" x-cloak class="mb-6">
        <x-ui.card title="Progress Monitoring">
            <div class="mb-3 h-2 rounded-full bg-zinc-800">
                <div class="h-2 rounded-full bg-indigo-500 transition-all" :style="'width:' + progress.progress_percent + '%'"></div>
            </div>
            <dl class="grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                <div><dt class="text-zinc-500">Current Table</dt><dd class="text-zinc-200" x-text="progress.current_table || '—'"></dd></div>
                <div><dt class="text-zinc-500">Rows Exported</dt><dd class="text-zinc-200" x-text="progress.rows_exported ?? '—'"></dd></div>
                <div><dt class="text-zinc-500">Elapsed</dt><dd class="text-zinc-200" x-text="progress.elapsed_seconds + 's'"></dd></div>
                <div><dt class="text-zinc-500">ETA</dt><dd class="text-zinc-200" x-text="progress.eta_seconds ? progress.eta_seconds + 's' : '—'"></dd></div>
            </dl>
        </x-ui.card>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.card title="General">
            <dl class="space-y-3 text-sm">
                <div><dt class="text-zinc-500">Connection</dt><dd class="text-zinc-200">{{ $export->connection?->name }} ({{ $export->connection?->host }}:{{ $export->connection?->port }})</dd></div>
                <div><dt class="text-zinc-500">Started</dt><dd class="text-zinc-200">{{ $export->started_at?->format('d M Y H:i:s') ?? '—' }}</dd></div>
                <div><dt class="text-zinc-500">Ended</dt><dd class="text-zinc-200">{{ $export->finished_at?->format('d M Y H:i:s') ?? '—' }}</dd></div>
                <div><dt class="text-zinc-500">Exit Code</dt><dd class="text-zinc-200">{{ $export->exit_code ?? '—' }}</dd></div>
                <div><dt class="text-zinc-500">Threads</dt><dd class="text-zinc-200">{{ $export->thread }}</dd></div>
                <div><dt class="text-zinc-500">Compression</dt><dd class="text-zinc-200">{{ $export->compression ? 'Yes' : 'No' }}</dd></div>
            </dl>
        </x-ui.card>

        <x-ui.card title="Command">
            <pre class="overflow-x-auto rounded-lg bg-zinc-950 p-3 font-mono text-xs text-zinc-300">{{ $export->command ?? '—' }}</pre>
        </x-ui.card>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <x-ui.card title="File Explorer">
            @if ($export->files->isEmpty())
                <p class="text-sm text-zinc-500">Belum ada file terindeks.</p>
            @else
                <ul class="max-h-64 space-y-1 overflow-y-auto font-mono text-xs text-zinc-300">
                    @foreach ($export->files as $file)
                        <li class="flex items-center justify-between rounded px-2 py-1 hover:bg-zinc-900/50">
                            <span>{{ $file->relative_path }} ({{ $file->formattedSize() }})</span>
                            @can('download', $export)
                                <a href="{{ route('mydumper-exports.download-file', [$export, $file->id]) }}" class="text-indigo-400">Download</a>
                            @endcan
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>

        <x-ui.card title="Log">
            <div class="max-h-64 overflow-y-auto font-mono text-xs text-zinc-400">
                @forelse ($export->logs->take(100) as $log)
                    <div class="border-b border-zinc-800/50 py-1">[{{ $log->stream }}] {{ $log->message }}</div>
                @empty
                    <p class="text-sm text-zinc-500">Belum ada log.</p>
                @endforelse
            </div>
        </x-ui.card>
    </div>
</div>
@endsection
