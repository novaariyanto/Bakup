@extends('layouts.app')

@section('title', 'Edit Koneksi Database')

@section('content')
    <div class="mb-6">
        <a href="{{ route('database-connections.index') }}" class="text-sm text-zinc-500 hover:text-zinc-300">← Kembali</a>
        <h1 class="mt-2 text-lg font-semibold text-zinc-100">Edit Koneksi</h1>
        <p class="text-sm text-zinc-500">{{ $connection->name }}</p>
    </div>

    <x-ui.card class="max-w-xl">
        <form method="POST" action="{{ route('database-connections.update', $connection) }}" class="space-y-4">
            @csrf
            @method('PUT')
            @include('database-connections._form', ['connection' => $connection])

            <div class="flex flex-col-reverse gap-2 border-t border-zinc-800 pt-4 sm:flex-row sm:justify-end">
                <button type="submit" formaction="{{ route('database-connections.test-form.edit', $connection) }}" formmethod="POST" class="btn-secondary">
                    Test Connection
                </button>
                <button type="submit" class="btn-primary">Simpan</button>
            </div>
        </form>
    </x-ui.card>
@endsection
