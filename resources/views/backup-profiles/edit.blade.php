@extends('layouts.app')

@section('title', 'Edit Backup Profile')

@section('content')
    <div class="mb-6">
        <a href="{{ route('backup-profiles.index') }}" class="text-sm text-zinc-500 hover:text-zinc-300">← Kembali</a>
        <h1 class="mt-2 text-lg font-semibold text-zinc-100">Edit Backup Profile</h1>
        <p class="text-sm text-zinc-500">{{ $profile->name }}</p>
    </div>

    <x-ui.card class="max-w-4xl">
        <form method="POST" action="{{ route('backup-profiles.update', $profile) }}">
            @csrf
            @method('PUT')
            @include('backup-profiles._form', [
                'profile' => $profile,
                'formDefaults' => $formDefaults ?? [],
                'connections' => $connections,
                'destinations' => $destinations,
                'compressionTypes' => $compressionTypes,
                'scheduleTypes' => $scheduleTypes,
                'retentionTypes' => $retentionTypes,
                'availableTables' => $availableTables ?? [],
            ])

            <div class="mt-8 flex justify-end gap-2 border-t border-zinc-800 pt-4">
                <a href="{{ route('backup-profiles.index') }}" class="btn-secondary">Batal</a>
                <button type="submit" class="btn-primary">Simpan Perubahan</button>
            </div>
        </form>
    </x-ui.card>
@endsection
