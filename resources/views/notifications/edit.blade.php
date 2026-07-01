@extends('layouts.app')

@section('title', 'Edit Notification Channel')

@section('content')
    <div class="mb-6">
        <a href="{{ route('notifications.index') }}" class="text-sm text-zinc-500 hover:text-zinc-300">← Kembali</a>
        <h1 class="mt-2 text-lg font-semibold text-zinc-100">Edit Channel</h1>
        <p class="text-sm text-zinc-500">{{ $channel->name }}</p>
    </div>

    <x-ui.card class="max-w-xl">
        <form method="POST" action="{{ route('notifications.update', $channel) }}" class="space-y-4">
            @csrf
            @method('PUT')
            @include('notifications._form', ['channel' => $channel, 'drivers' => $drivers])

            <div class="flex flex-col-reverse gap-2 border-t border-zinc-800 pt-4 sm:flex-row sm:justify-end">
                <button type="submit" formaction="{{ route('notifications.test-form.edit', $channel) }}" formmethod="POST" class="btn-secondary">
                    Test Channel
                </button>
                <button type="submit" class="btn-primary">Simpan</button>
            </div>
        </form>
    </x-ui.card>
@endsection
