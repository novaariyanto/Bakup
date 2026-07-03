@extends('layouts.app')

@section('title', 'Tambah Backup Profile')

@section('content')
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <a href="{{ route('backup-profiles.index') }}" class="inline-flex items-center gap-1 text-sm text-zinc-500 transition hover:text-zinc-300">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
                Kembali ke daftar
            </a>
            <h1 class="mt-3 text-xl font-semibold text-zinc-100">Tambah Backup Profile</h1>
            <p class="mt-1 max-w-2xl text-sm text-zinc-500">Buat konfigurasi backup baru — database, folder, jadwal, dan retention dalam beberapa langkah.</p>
        </div>
    </div>

    @if ($connections->isEmpty() || $destinations->isEmpty())
        <div class="mb-6 rounded-lg border border-amber-500/20 bg-amber-500/10 px-4 py-3 text-sm text-amber-300">
            @if ($connections->isEmpty() && $destinations->isEmpty())
                Anda perlu menambahkan <a href="{{ route('database-connections.create') }}" class="font-medium text-amber-200 underline">koneksi database</a> dan
                <a href="{{ route('storage-destinations.create') }}" class="font-medium text-amber-200 underline">storage destination</a> terlebih dahulu.
            @elseif ($connections->isEmpty())
                Belum ada koneksi database aktif. <a href="{{ route('database-connections.create') }}" class="font-medium text-amber-200 underline">Tambah koneksi</a>
            @else
                Belum ada storage destination aktif. <a href="{{ route('storage-destinations.create') }}" class="font-medium text-amber-200 underline">Tambah destination</a>
            @endif
        </div>
    @endif

    <x-ui.card class="max-w-4xl">
        <form method="POST" action="{{ route('backup-profiles.store') }}">
            @csrf
            @include('backup-profiles._form', [
                'formDefaults' => $defaults,
                'connections' => $connections,
                'destinations' => $destinations,
                'compressionTypes' => $compressionTypes,
                'scheduleTypes' => $scheduleTypes,
                'retentionTypes' => $retentionTypes,
                'availableTables' => $availableTables ?? [],
                'showFormSteps' => true,
            ])

            <div class="sticky bottom-0 -mx-5 -mb-5 mt-8 flex flex-col-reverse gap-3 border-t border-zinc-800 bg-zinc-900/95 px-5 py-4 backdrop-blur sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-zinc-500">Pastikan minimal satu tipe backup dan satu destination dipilih.</p>
                <div class="flex gap-2">
                    <a href="{{ route('backup-profiles.index') }}" class="btn-secondary flex-1 sm:flex-none">Batal</a>
                    <button type="submit" class="btn-primary flex-1 sm:flex-none" @disabled($connections->isEmpty() || $destinations->isEmpty())>
                        Simpan Profile
                    </button>
                </div>
            </div>
        </form>
    </x-ui.card>
@endsection
