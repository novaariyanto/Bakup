@extends('layouts.app')

@section('title', 'Backup Profiles')

@section('content')
@php
    $q = request('q', '');
    $status = request('status', 'all');
    $connectionFilter = request('connection', 'all');
    $progressOpen = filled($progressData ?? null);
@endphp

<div x-data="{ deleteOpen: false, deleteUrl: '', progressOpen: @js($progressOpen) }">
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-lg font-semibold text-zinc-100">Backup Profiles</h1>
            <p class="text-sm text-zinc-500">Konfigurasi backup database, folder, schedule, dan retention</p>
        </div>
        @can('create', \App\Models\BackupProfile::class)
            <a href="{{ route('backup-profiles.create') }}" class="btn-primary">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Tambah Profile
            </a>
        @endcan
    </div>

    <form method="GET" action="{{ route('backup-profiles.index') }}" class="card mb-6 p-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <div class="flex-1">
                <x-ui.search-input name="q" :value="$q" label="Cari nama atau deskripsi" />
            </div>
            <select name="connection" class="input-field sm:w-48">
                <option value="all" @selected($connectionFilter === 'all')>Semua Koneksi</option>
                @foreach ($connections as $connection)
                    <option value="{{ $connection->id }}" @selected((string) $connectionFilter === (string) $connection->id)>{{ $connection->name }}</option>
                @endforeach
            </select>
            <select name="status" class="input-field sm:w-44">
                <option value="all" @selected($status === 'all')>Semua Status</option>
                <option value="active" @selected($status === 'active')>Aktif</option>
                <option value="inactive" @selected($status === 'inactive')>Nonaktif</option>
            </select>
            <button type="submit" class="btn-secondary">Filter</button>
        </div>
    </form>

    @if ($profiles->isEmpty())
        <x-ui.empty-state
            title="Belum ada backup profile"
            description="Buat profile pertama untuk mengatur backup database dan folder."
        >
            @can('create', \App\Models\BackupProfile::class)
                <a href="{{ route('backup-profiles.create') }}" class="btn-primary">Tambah Profile</a>
            @endcan
        </x-ui.empty-state>
    @else
        <x-ui.card>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-zinc-800 text-zinc-500">
                            <th class="pb-3 font-medium">Nama</th>
                            <th class="pb-3 font-medium">Koneksi</th>
                            <th class="pb-3 font-medium">Tipe</th>
                            <th class="pb-3 font-medium">Schedule</th>
                            <th class="pb-3 font-medium">Destinations</th>
                            <th class="pb-3 font-medium">Status</th>
                            <th class="pb-3 font-medium text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800/80">
                        @foreach ($profiles as $profile)
                            <tr>
                                <td class="py-3">
                                    <p class="font-medium text-zinc-200">{{ $profile->name }}</p>
                                    @if ($profile->description)
                                        <p class="text-xs text-zinc-500">{{ Str::limit($profile->description, 50) }}</p>
                                    @endif
                                </td>
                                <td class="py-3 text-zinc-400">{{ $profile->databaseConnection?->name ?? '-' }}</td>
                                <td class="py-3">
                                    <div class="flex flex-wrap gap-1">
                                        @if ($profile->backup_database)
                                            <x-ui.badge color="indigo">DB</x-ui.badge>
                                        @endif
                                        @if ($profile->backup_folders)
                                            <x-ui.badge color="amber">Folder</x-ui.badge>
                                        @endif
                                    </div>
                                </td>
                                <td class="py-3 text-zinc-400">{{ $profile->scheduleLabel() }}</td>
                                <td class="py-3 text-zinc-400">{{ $profile->destinations->count() }}</td>
                                <td class="py-3">
                                    <x-ui.badge :color="$profile->is_active ? 'emerald' : 'zinc'">
                                        {{ $profile->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </x-ui.badge>
                                </td>
                                <td class="py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        @can('run', $profile)
                                            <form method="POST" action="{{ route('backup-profiles.run', $profile) }}" class="inline">
                                                @csrf
                                                <button
                                                    type="submit"
                                                    @disabled($profile->hasRunningBackup())
                                                    class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-indigo-400 hover:bg-indigo-500/10 disabled:cursor-not-allowed disabled:opacity-40"
                                                >
                                                    Run Backup
                                                </button>
                                            </form>
                                        @endcan
                                        @can('update', $profile)
                                            <a href="{{ route('backup-profiles.edit', $profile) }}" class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-zinc-400 hover:bg-zinc-800">Edit</a>
                                        @endcan
                                        @can('delete', $profile)
                                            <button
                                                type="button"
                                                class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-red-400 hover:bg-red-500/10"
                                                @click="deleteOpen = true; deleteUrl = '{{ route('backup-profiles.destroy', $profile) }}'"
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

        <div class="mt-6">{{ $profiles->withQueryString()->links() }}</div>
    @endif

    @if ($progressOpen && ($progressData ?? null))
        @php
            $progressHistoryId = request('progress') ?: ($progressData['history_id'] ?? null);
        @endphp
        <x-ui.modal title="Backup Progress" maxWidth="max-w-lg" show="progressOpen">
            <div
                x-data="backupProgressPoll({
                    initialProgress: @js($progressData),
                    progressUrl: @js(route('backup-profiles.progress', $progressHistoryId)),
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

                <template x-if="progress.is_finished">
                    <div class="mt-5 flex justify-end">
                        <a href="{{ route('backup-profiles.index', request()->except('progress', 'refresh')) }}" class="btn-primary">Tutup</a>
                    </div>
                </template>
            </div>
        </x-ui.modal>
    @endif

    <x-ui.modal title="Hapus Backup Profile" maxWidth="max-w-md" show="deleteOpen">
        <p class="text-sm text-zinc-400">Backup profile akan dihapus permanen. Tindakan ini tidak dapat dibatalkan.</p>
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
