@extends('layouts.app')

@section('title', 'Tambah Storage Destination')

@section('content')
    <div class="mb-6">
        <a href="{{ route('storage-destinations.index') }}" class="text-sm text-zinc-500 hover:text-zinc-300">← Kembali</a>
        <h1 class="mt-2 text-lg font-semibold text-zinc-100">Tambah Destination</h1>
        <p class="text-sm text-zinc-500">Konfigurasi lokasi penyimpanan backup</p>
    </div>

    <x-ui.card class="max-w-xl">
        <form method="POST" action="{{ route('storage-destinations.store') }}" class="space-y-4">
            @csrf
            @include('storage-destinations._form', ['drivers' => $drivers])

            <div class="flex flex-col-reverse gap-2 border-t border-zinc-800 pt-4 sm:flex-row sm:justify-end">
                <button type="submit" formaction="{{ route('storage-destinations.test-form') }}" formmethod="POST" class="btn-secondary">
                    Test Connection
                </button>
                <button type="submit" class="btn-primary">Simpan</button>
            </div>
        </form>
    </x-ui.card>
@endsection
