@extends('layouts.app')

@section('title', 'Database Connections')

@section('content')
@php
    $q = request('q', '');
    $status = request('status', 'all');
@endphp

<div x-data="{ deleteOpen: false, deleteUrl: '' }">
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-lg font-semibold text-zinc-100">Database Connections</h1>
            <p class="text-sm text-zinc-500">Kelola koneksi MySQL untuk backup profile</p>
        </div>
        @can('create', \App\Models\DatabaseConnection::class)
            <a href="{{ route('database-connections.create') }}" class="btn-primary">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Tambah Koneksi
            </a>
        @endcan
    </div>

    <form method="GET" action="{{ route('database-connections.index') }}" class="card mb-6 p-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <div class="flex-1">
                <x-ui.search-input name="q" :value="$q" label="Cari nama, host, atau database" />
            </div>
            <select name="status" class="input-field sm:w-44">
                <option value="all" @selected($status === 'all')>Semua Status</option>
                <option value="active" @selected($status === 'active')>Aktif</option>
                <option value="inactive" @selected($status === 'inactive')>Nonaktif</option>
            </select>
            <button type="submit" class="btn-secondary sm:w-auto">Filter</button>
        </div>
    </form>

    @if ($connections->isEmpty())
        <x-ui.empty-state
            title="Belum ada koneksi database"
            description="Tambahkan koneksi MySQL pertama untuk memulai konfigurasi backup."
        >
            @can('create', \App\Models\DatabaseConnection::class)
                <a href="{{ route('database-connections.create') }}" class="btn-primary">Tambah Koneksi</a>
            @endcan
        </x-ui.empty-state>
    @else
        <x-ui.card>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-zinc-800 text-zinc-500">
                            <th class="pb-3 font-medium">Nama</th>
                            <th class="pb-3 font-medium">Host</th>
                            <th class="pb-3 font-medium">Database</th>
                            <th class="pb-3 font-medium">Status</th>
                            <th class="pb-3 font-medium">Test Terakhir</th>
                            <th class="pb-3 font-medium text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800/80">
                        @foreach ($connections as $connection)
                            <tr>
                                <td class="py-3">
                                    <p class="font-medium text-zinc-200">{{ $connection->name }}</p>
                                    <p class="text-xs text-zinc-500">{{ $connection->username }}</p>
                                </td>
                                <td class="py-3 text-zinc-400">{{ $connection->host }}:{{ $connection->port }}</td>
                                <td class="py-3 text-zinc-400">{{ $connection->database_name }}</td>
                                <td class="py-3">
                                    <x-ui.badge :color="$connection->is_active ? 'emerald' : 'zinc'">
                                        {{ $connection->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </x-ui.badge>
                                </td>
                                <td class="py-3">
                                    @if ($connection->last_tested_at)
                                        <div class="flex items-center gap-2">
                                            <x-ui.badge :color="$connection->lastTestSucceeded() ? 'emerald' : 'red'">
                                                {{ $connection->lastTestSucceeded() ? 'OK' : 'Gagal' }}
                                            </x-ui.badge>
                                            <span class="text-xs text-zinc-500">{{ $connection->last_tested_at->diffForHumans() }}</span>
                                        </div>
                                    @else
                                        <span class="text-xs text-zinc-500">Belum ditest</span>
                                    @endif
                                </td>
                                <td class="py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        @can('test', $connection)
                                            <form method="POST" action="{{ route('database-connections.test', $connection) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-indigo-400 hover:bg-indigo-500/10">
                                                    Test
                                                </button>
                                            </form>
                                        @endcan
                                        @can('update', $connection)
                                            <a href="{{ route('database-connections.edit', $connection) }}" class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-zinc-400 hover:bg-zinc-800">Edit</a>
                                        @endcan
                                        @can('delete', $connection)
                                            <button
                                                type="button"
                                                class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-red-400 hover:bg-red-500/10"
                                                @click="deleteOpen = true; deleteUrl = '{{ route('database-connections.destroy', $connection) }}'"
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

        <div class="mt-6">{{ $connections->withQueryString()->links() }}</div>
    @endif

    @if (session('testResult'))
        @php $testResult = session('testResult'); @endphp
        <div x-data="{ open: true }">
            <x-ui.modal title="Hasil Test Koneksi" maxWidth="max-w-lg" show="open">
                @if ($testResult['success'])
                    <div class="mb-4 flex items-center gap-2">
                        <x-ui.badge color="emerald">Berhasil</x-ui.badge>
                        <span class="text-sm text-zinc-400">{{ $testResult['status'] }}</span>
                    </div>
                    <dl class="grid gap-3 text-sm sm:grid-cols-2">
                        <div class="rounded-lg border border-zinc-800 bg-zinc-900/50 p-3">
                            <dt class="text-zinc-500">MySQL Version</dt>
                            <dd class="mt-1 font-medium text-zinc-200">{{ $testResult['mysqlVersion'] }}</dd>
                        </div>
                        <div class="rounded-lg border border-zinc-800 bg-zinc-900/50 p-3">
                            <dt class="text-zinc-500">Database Size</dt>
                            <dd class="mt-1 font-medium text-zinc-200">{{ $testResult['databaseSize'] }}</dd>
                        </div>
                        <div class="rounded-lg border border-zinc-800 bg-zinc-900/50 p-3">
                            <dt class="text-zinc-500">Total Tables</dt>
                            <dd class="mt-1 font-medium text-zinc-200">{{ $testResult['totalTables'] }}</dd>
                        </div>
                        <div class="rounded-lg border border-zinc-800 bg-zinc-900/50 p-3">
                            <dt class="text-zinc-500">Character Set</dt>
                            <dd class="mt-1 font-medium text-zinc-200">{{ $testResult['characterSet'] }}</dd>
                        </div>
                        <div class="rounded-lg border border-zinc-800 bg-zinc-900/50 p-3">
                            <dt class="text-zinc-500">Collation</dt>
                            <dd class="mt-1 font-medium text-zinc-200">{{ $testResult['collation'] }}</dd>
                        </div>
                        <div class="rounded-lg border border-zinc-800 bg-zinc-900/50 p-3">
                            <dt class="text-zinc-500">Storage Engine</dt>
                            <dd class="mt-1 font-medium text-zinc-200">{{ $testResult['storageEngine'] }}</dd>
                        </div>
                    </dl>
                @else
                    <div class="mb-4 flex items-center gap-2">
                        <x-ui.badge color="red">Gagal</x-ui.badge>
                        <span class="text-sm text-zinc-400">Koneksi ditolak</span>
                    </div>
                    <div class="rounded-lg border border-red-500/20 bg-red-500/10 p-4 text-sm text-red-300">
                        {{ $testResult['errorMessage'] }}
                    </div>
                @endif
            </x-ui.modal>
        </div>
    @endif

    <x-ui.modal title="Hapus Koneksi" maxWidth="max-w-md" show="deleteOpen">
        <p class="text-sm text-zinc-400">Koneksi database akan dihapus permanen. Tindakan ini tidak dapat dibatalkan.</p>
        <div class="mt-5 flex justify-end gap-2">
            <button type="button" @click="deleteOpen = false" class="btn-secondary">Batal</button>
            <form :action="deleteUrl" method="POST" class="inline">
                @csrf
                @method('DELETE')
                <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-red-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-red-500">
                    Hapus
                </button>
            </form>
        </div>
    </x-ui.modal>
</div>
@endsection
