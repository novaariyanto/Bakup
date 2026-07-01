@extends('layouts.app')

@section('title', 'Tambah Backup Profile')

@section('content')
    <div class="mb-6">
        <a href="{{ route('backup-profiles.index') }}" class="text-sm text-zinc-500 hover:text-zinc-300">← Kembali</a>
        <h1 class="mt-2 text-lg font-semibold text-zinc-100">Tambah Backup Profile</h1>
        <p class="text-sm text-zinc-500">Konfigurasi backup database, folder, schedule, dan retention</p>
    </div>

    <x-ui.card class="max-w-3xl">
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
            ])

            <div class="mt-6 flex justify-end gap-2 border-t border-zinc-800 pt-4">
                <a href="{{ route('backup-profiles.index') }}" class="btn-secondary">Batal</a>
                <button type="submit" class="btn-primary" @disabled($connections->isEmpty() || $destinations->isEmpty())>Simpan</button>
            </div>
        </form>
    </x-ui.card>
@endsection
