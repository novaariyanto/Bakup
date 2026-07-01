@extends('layouts.app')

@section('title', 'Storage Destinations')

@section('content')
@php
    $q = request('q', '');
    $status = request('status', 'all');
    $driverFilter = request('driver', 'all');
@endphp

<div x-data="{ deleteOpen: false, deleteUrl: '' }">
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-lg font-semibold text-zinc-100">Storage Destinations</h1>
            <p class="text-sm text-zinc-500">Kelola lokasi penyimpanan backup (Local, SFTP, S3)</p>
        </div>
        @can('create', \App\Models\BackupDestination::class)
            <a href="{{ route('storage-destinations.create') }}" class="btn-primary">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Tambah Destination
            </a>
        @endcan
    </div>

    <form method="GET" action="{{ route('storage-destinations.index') }}" class="card mb-6 p-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <div class="flex-1">
                <x-ui.search-input name="q" :value="$q" label="Cari nama destination" />
            </div>
            <select name="driver" class="input-field sm:w-44">
                <option value="all" @selected($driverFilter === 'all')>Semua Driver</option>
                @foreach ($drivers as $driverOption)
                    <option value="{{ $driverOption->value }}" @selected($driverFilter === $driverOption->value)>{{ $driverOption->label() }}</option>
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

    @if ($destinations->isEmpty())
        <x-ui.empty-state
            title="Belum ada storage destination"
            description="Tambahkan lokasi penyimpanan pertama untuk menerima file backup."
        >
            @can('create', \App\Models\BackupDestination::class)
                <a href="{{ route('storage-destinations.create') }}" class="btn-primary">Tambah Destination</a>
            @endcan
        </x-ui.empty-state>
    @else
        <x-ui.card>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-zinc-800 text-zinc-500">
                            <th class="pb-3 font-medium">Nama</th>
                            <th class="pb-3 font-medium">Driver</th>
                            <th class="pb-3 font-medium">Target</th>
                            <th class="pb-3 font-medium">Status</th>
                            <th class="pb-3 font-medium">Test Terakhir</th>
                            <th class="pb-3 font-medium text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800/80">
                        @foreach ($destinations as $destination)
                            <tr>
                                <td class="py-3">
                                    <p class="font-medium text-zinc-200">{{ $destination->name }}</p>
                                </td>
                                <td class="py-3">
                                    <x-ui.badge color="indigo">{{ $destination->driverLabel() }}</x-ui.badge>
                                </td>
                                <td class="py-3 text-zinc-400">{{ $destination->summary() }}</td>
                                <td class="py-3">
                                    <x-ui.badge :color="$destination->is_active ? 'emerald' : 'zinc'">
                                        {{ $destination->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </x-ui.badge>
                                </td>
                                <td class="py-3">
                                    @if ($destination->last_tested_at)
                                        <div class="flex items-center gap-2">
                                            <x-ui.badge :color="$destination->lastTestSucceeded() ? 'emerald' : 'red'">
                                                {{ $destination->lastTestSucceeded() ? 'OK' : 'Gagal' }}
                                            </x-ui.badge>
                                            <span class="text-xs text-zinc-500">{{ $destination->last_tested_at->diffForHumans() }}</span>
                                        </div>
                                    @else
                                        <span class="text-xs text-zinc-500">Belum ditest</span>
                                    @endif
                                </td>
                                <td class="py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        @can('test', $destination)
                                            <form method="POST" action="{{ route('storage-destinations.test', $destination) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-indigo-400 hover:bg-indigo-500/10">Test</button>
                                            </form>
                                        @endcan
                                        @can('update', $destination)
                                            <a href="{{ route('storage-destinations.edit', $destination) }}" class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-zinc-400 hover:bg-zinc-800">Edit</a>
                                        @endcan
                                        @can('delete', $destination)
                                            <button
                                                type="button"
                                                class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-red-400 hover:bg-red-500/10"
                                                @click="deleteOpen = true; deleteUrl = '{{ route('storage-destinations.destroy', $destination) }}'"
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

        <div class="mt-6">{{ $destinations->withQueryString()->links() }}</div>
    @endif

    @if (session('testResult'))
        @php $testResult = session('testResult'); @endphp
        <div x-data="{ open: true }">
            <x-ui.modal title="Hasil Test Storage" maxWidth="max-w-lg" show="open">
                @if ($testResult['success'])
                    <div class="mb-4 flex items-center gap-2">
                        <x-ui.badge color="emerald">Berhasil</x-ui.badge>
                        <span class="text-sm text-zinc-400">{{ $testResult['status'] }}</span>
                    </div>
                    <dl class="grid gap-3 text-sm sm:grid-cols-2">
                        @if ($testResult['resolvedPath'] ?? null)
                            <div class="rounded-lg border border-zinc-800 bg-zinc-900/50 p-3 sm:col-span-2">
                                <dt class="text-zinc-500">Path</dt>
                                <dd class="mt-1 break-all font-medium text-zinc-200">{{ $testResult['resolvedPath'] }}</dd>
                            </div>
                        @endif
                        @if ($testResult['host'] ?? null)
                            <div class="rounded-lg border border-zinc-800 bg-zinc-900/50 p-3">
                                <dt class="text-zinc-500">Host</dt>
                                <dd class="mt-1 font-medium text-zinc-200">{{ $testResult['host'] }}</dd>
                            </div>
                        @endif
                        @if ($testResult['bucket'] ?? null)
                            <div class="rounded-lg border border-zinc-800 bg-zinc-900/50 p-3">
                                <dt class="text-zinc-500">Bucket</dt>
                                <dd class="mt-1 font-medium text-zinc-200">{{ $testResult['bucket'] }}</dd>
                            </div>
                        @endif
                        @if ($testResult['region'] ?? null)
                            <div class="rounded-lg border border-zinc-800 bg-zinc-900/50 p-3">
                                <dt class="text-zinc-500">Region</dt>
                                <dd class="mt-1 font-medium text-zinc-200">{{ $testResult['region'] }}</dd>
                            </div>
                        @endif
                        @if ($testResult['endpoint'] ?? null)
                            <div class="rounded-lg border border-zinc-800 bg-zinc-900/50 p-3 sm:col-span-2">
                                <dt class="text-zinc-500">Endpoint</dt>
                                <dd class="mt-1 break-all font-medium text-zinc-200">{{ $testResult['endpoint'] }}</dd>
                            </div>
                        @endif
                    </dl>
                @else
                    <div class="mb-4 flex items-center gap-2">
                        <x-ui.badge color="red">Gagal</x-ui.badge>
                        <span class="text-sm text-zinc-400">Koneksi storage ditolak</span>
                    </div>
                    <div class="rounded-lg border border-red-500/20 bg-red-500/10 p-4 text-sm text-red-300">
                        {{ $testResult['errorMessage'] }}
                    </div>
                @endif
            </x-ui.modal>
        </div>
    @endif

    <x-ui.modal title="Hapus Destination" maxWidth="max-w-md" show="deleteOpen">
        <p class="text-sm text-zinc-400">Storage destination akan dihapus permanen. Tindakan ini tidak dapat dibatalkan.</p>
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
