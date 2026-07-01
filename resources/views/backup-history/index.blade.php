@extends('layouts.app')

@section('title', 'Backup History')

@section('content')
@php
    $q = request('q', '');
    $status = request('status', 'all');
    $profileFilter = request('profile', 'all');
    $historyId = request('history');
    $detailOpen = filled($progressData ?? null);
@endphp

<div x-data="{ deleteOpen: false, deleteUrl: '', detailOpen: @js($detailOpen) }">
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-lg font-semibold text-zinc-100">Backup History</h1>
            <p class="text-sm text-zinc-500">Riwayat eksekusi backup, download file, retry, dan hapus record</p>
        </div>
    </div>

    <form method="GET" action="{{ route('backup-history.index') }}" class="card mb-6 p-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <div class="flex-1">
                <x-ui.search-input name="q" :value="$q" label="Cari profile, filename, atau pesan" />
            </div>
            <select name="profile" class="input-field sm:w-48">
                <option value="all" @selected($profileFilter === 'all')>Semua Profile</option>
                @foreach ($profiles as $profile)
                    <option value="{{ $profile->id }}" @selected((string) $profileFilter === (string) $profile->id)>{{ $profile->name }}</option>
                @endforeach
            </select>
            <select name="status" class="input-field sm:w-44">
                <option value="all" @selected($status === 'all')>Semua Status</option>
                @foreach ($statuses as $statusOption)
                    <option value="{{ $statusOption->value }}" @selected($status === $statusOption->value)>{{ $statusOption->label() }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn-secondary">Filter</button>
        </div>
    </form>

    @if ($histories->isEmpty())
        <x-ui.empty-state
            title="Belum ada riwayat backup"
            description="Jalankan backup manual dari Backup Profiles atau tunggu jadwal otomatis."
        />
    @else
        <x-ui.card>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-zinc-800 text-zinc-500">
                            <th class="pb-3 font-medium">Profile</th>
                            <th class="pb-3 font-medium">Status</th>
                            <th class="pb-3 font-medium">Dimulai</th>
                            <th class="pb-3 font-medium">Durasi</th>
                            <th class="pb-3 font-medium">Ukuran</th>
                            <th class="pb-3 font-medium">File</th>
                            <th class="pb-3 font-medium text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800/80">
                        @foreach ($histories as $history)
                            <tr>
                                <td class="py-3">
                                    <p class="font-medium text-zinc-200">{{ $history->backupProfile?->name ?? '—' }}</p>
                                    @if ($history->triggeredBy)
                                        <p class="text-xs text-zinc-500">oleh {{ $history->triggeredBy->name }}</p>
                                    @endif
                                </td>
                                <td class="py-3">
                                    <x-ui.badge :color="$history->status->color()">{{ $history->status->label() }}</x-ui.badge>
                                </td>
                                <td class="py-3 text-zinc-400">
                                    {{ $history->started_at?->format('d M Y H:i') ?? $history->created_at->format('d M Y H:i') }}
                                </td>
                                <td class="py-3 text-zinc-400">
                                    @if ($history->duration_seconds)
                                        {{ $history->duration_seconds }}s
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="py-3 text-zinc-400">{{ $history->formattedSize() ?? '—' }}</td>
                                <td class="py-3 text-zinc-400">
                                    <span class="block max-w-[160px] truncate" title="{{ $history->filename }}">
                                        {{ $history->filename ?? '—' }}
                                    </span>
                                </td>
                                <td class="py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        @can('view', $history)
                                            <a
                                                href="{{ route('backup-history.index', array_merge(request()->query(), ['history' => $history->id])) }}"
                                                class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-zinc-400 hover:bg-zinc-800"
                                            >
                                                Detail
                                            </a>
                                        @endcan
                                        @can('download', $history)
                                            <a
                                                href="{{ route('backup-history.download', $history) }}"
                                                class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-indigo-400 hover:bg-indigo-500/10"
                                            >
                                                Download
                                            </a>
                                        @endcan
                                        @can('retry', $history)
                                            <form method="POST" action="{{ route('backup-history.retry', $history) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-amber-400 hover:bg-amber-500/10">
                                                    Retry
                                                </button>
                                            </form>
                                        @endcan
                                        @can('delete', $history)
                                            <button
                                                type="button"
                                                class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-red-400 hover:bg-red-500/10"
                                                @click="deleteOpen = true; deleteUrl = '{{ route('backup-history.destroy', $history) }}'"
                                            >
                                                Hapus
                                            </button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-ui.card>

        <div class="mt-6">{{ $histories->withQueryString()->links() }}</div>
    @endif

    @if ($detailOpen && ($progressData ?? null))
        <x-ui.modal title="Detail Backup" maxWidth="max-w-lg" show="detailOpen">
            <div
                x-data="backupProgressPoll({
                    initialProgress: @js($progressData),
                    progressUrl: @js(route('backup-history.progress', $historyId)),
                })"
                x-init="init()"
            >
                <x-ui.backup-progress-live />

                <template x-if="! progress.is_finished">
                    <div class="mt-4">
                        <div
                            x-show="showQueueWarning"
                            x-cloak
                            class="mb-3 rounded-lg border border-amber-500/20 bg-amber-500/10 px-3 py-2 text-xs text-amber-200"
                        >
                            Backup masih menunggu queue worker. Jalankan
                            <code class="rounded bg-zinc-800 px-1">php artisan queue:work</code>
                            di terminal agar job diproses.
                        </div>
                        <button
                            type="button"
                            class="btn-secondary text-xs"
                            :disabled="refreshing"
                            @click="refresh()"
                            x-text="refreshing ? 'Memperbarui...' : 'Refresh Progress'"
                        ></button>
                        <p class="mt-2 text-xs text-zinc-500">Status diperbarui otomatis setiap 2 detik.</p>
                    </div>
                </template>

                <template x-if="(progress.logs ?? []).length > 0">
                    <div class="mt-5 border-t border-zinc-800 pt-4">
                        <p class="mb-3 text-xs font-medium uppercase tracking-wide text-zinc-500">Log</p>
                        <div class="max-h-48 space-y-2 overflow-y-auto">
                            <template x-for="(log, index) in progress.logs" :key="index">
                                <div class="rounded-lg border border-zinc-800 bg-zinc-900/50 px-3 py-2 text-xs">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="font-medium text-zinc-300" x-text="log.stage"></span>
                                        <span
                                            :class="{
                                                'text-emerald-400': log.level === 'info',
                                                'text-red-400': log.level === 'error',
                                                'text-zinc-500': log.level !== 'info' && log.level !== 'error',
                                            }"
                                            x-text="log.level.toUpperCase()"
                                        ></span>
                                    </div>
                                    <p class="mt-1 text-zinc-400" x-text="log.message"></p>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>

                <div class="mt-5 flex justify-end">
                    <a href="{{ route('backup-history.index', request()->except('history', 'refresh')) }}" class="btn-primary">Tutup</a>
                </div>
            </div>
        </x-ui.modal>
    @endif

    <x-ui.modal title="Hapus Riwayat Backup" maxWidth="max-w-md" show="deleteOpen">
        <p class="text-sm text-zinc-400">Record riwayat akan dihapus. File backup di storage juga akan dihapus jika ditemukan.</p>
        <div class="mt-5 flex justify-end gap-2">
            <button type="button" @click="deleteOpen = false" class="btn-secondary">Batal</button>
            <form :action="deleteUrl" method="POST" class="inline">
                @csrf
                @method('DELETE')
                <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-red-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-red-500">Hapus</button>
            </form>
        </div>
    </x-ui.modal>
</div>
@endsection
