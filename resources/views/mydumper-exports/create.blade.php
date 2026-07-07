@extends('layouts.app')

@section('title', 'New MyDumper Export')

@section('content')
    <div class="mb-6">
        <a href="{{ route('mydumper-exports.index') }}" class="text-sm text-zinc-500 hover:text-zinc-300">← Kembali</a>
        <h1 class="mt-2 text-lg font-semibold text-zinc-100">New Export</h1>
    </div>

    <x-ui.card class="max-w-4xl">
        <form method="POST" action="{{ route('mydumper-exports.store') }}">
            @csrf
            @include('mydumper-exports._form', [
                'connections' => $connections,
                'destinations' => $destinations,
                'exportTypes' => $exportTypes,
                'scheduleTypes' => $scheduleTypes,
                'defaults' => $defaults,
            ])
            <div class="mt-6 flex gap-2">
                <a href="{{ route('mydumper-exports.index') }}" class="btn-secondary">Batal</a>
                <button type="submit" class="btn-primary">Buat & Jalankan Export</button>
            </div>
        </form>
    </x-ui.card>
@endsection
