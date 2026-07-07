@extends('layouts.app')

@section('title', 'MyDumper Export')

@section('content')
@php
    $q = $search ?? '';
    $status = $statusFilter ?? 'all';
    $connectionFilter = $connectionFilter ?? 'all';
@endphp

<div x-data="{ selected: [] }">
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-lg font-semibold text-zinc-100">MyDumper Export</h1>
            <p class="text-sm text-zinc-500">Export database besar menggunakan mydumper</p>
        </div>
        @can('create', \App\Models\MyDumperExport::class)
            <a href="{{ route('mydumper-exports.create') }}" class="btn-primary">New Export</a>
        @endcan
    </div>

    <form method="GET" class="card mb-6 p-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <div class="flex-1">
                <x-ui.search-input name="q" :value="$q" label="Cari nama atau database" />
            </div>
            <select name="connection" class="input-field sm:w-48">
                <option value="all" @selected($connectionFilter === 'all')>Semua Koneksi</option>
                @foreach ($connections as $connection)
                    <option value="{{ $connection->id }}" @selected((string) $connectionFilter === (string) $connection->id)>{{ $connection->name }}</option>
                @endforeach
            </select>
            <select name="status" class="input-field sm:w-44">
                <option value="all" @selected($status === 'all')>Semua Status</option>
                @foreach (\App\Enums\MyDumper\MyDumperExportStatus::cases() as $statusCase)
                    <option value="{{ $statusCase->value }}" @selected($status === $statusCase->value)>{{ $statusCase->label() }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn-secondary">Filter</button>
        </div>
    </form>

    @if ($exports->isEmpty())
        <x-ui.empty-state title="Belum ada export" description="Buat export pertama untuk memulai.">
            @can('create', \App\Models\MyDumperExport::class)
                <a href="{{ route('mydumper-exports.create') }}" class="btn-primary">New Export</a>
            @endcan
        </x-ui.empty-state>
    @else
        <form method="POST" action="{{ route('mydumper-exports.bulk') }}">
            @csrf
            <x-ui.card>
                <div class="mb-4 flex flex-wrap gap-2">
                    <button type="submit" name="action" value="retry" class="btn-secondary text-xs">Bulk Retry</button>
                    <button type="submit" name="action" value="delete" class="btn-secondary text-xs text-red-300">Bulk Delete</button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-zinc-800 text-zinc-500">
                                <th class="pb-3 pr-2"><input type="checkbox" @change="selected = $event.target.checked ? @js($exports->pluck('id')) : []"></th>
                                <th class="pb-3 font-medium">ID</th>
                                <th class="pb-3 font-medium">Database</th>
                                <th class="pb-3 font-medium">Connection</th>
                                <th class="pb-3 font-medium">Profile</th>
                                <th class="pb-3 font-medium">Type</th>
                                <th class="pb-3 font-medium">Thread</th>
                                <th class="pb-3 font-medium">Compression</th>
                                <th class="pb-3 font-medium">Size</th>
                                <th class="pb-3 font-medium">Status</th>
                                <th class="pb-3 font-medium">Progress</th>
                                <th class="pb-3 font-medium">Duration</th>
                                <th class="pb-3 font-medium text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-800/80">
                            @foreach ($exports as $export)
                                <tr>
                                    <td class="py-3 pr-2"><input type="checkbox" name="ids[]" value="{{ $export->id }}" x-model="selected"></td>
                                    <td class="py-3 text-zinc-400">#{{ $export->id }}</td>
                                    <td class="py-3 font-mono text-xs text-zinc-300">{{ $export->database }}</td>
                                    <td class="py-3 text-zinc-400">{{ $export->connection?->name ?? '—' }}</td>
                                    <td class="py-3 text-zinc-300">{{ $export->profile?->name ?? $export->name }}</td>
                                    <td class="py-3 text-zinc-400">{{ $export->type->label() }}</td>
                                    <td class="py-3 text-zinc-400">{{ $export->thread }}</td>
                                    <td class="py-3 text-zinc-400">{{ $export->compression ? 'Yes' : 'No' }}</td>
                                    <td class="py-3 text-zinc-400">{{ $export->formattedSize() ?? '—' }}</td>
                                    <td class="py-3"><x-ui.badge :label="$export->status->label()" :color="match($export->status->value){'success'=>'green','failed'=>'red','running'=>'blue','cancelled'=>'amber',default=>'zinc'}" /></td>
                                    <td class="py-3">
                                        <div class="h-1.5 w-20 rounded-full bg-zinc-800"><div class="h-1.5 rounded-full bg-indigo-500" style="width: {{ $export->progress_percent }}%"></div></div>
                                        <span class="text-xs text-zinc-500">{{ $export->progress_percent }}%</span>
                                    </td>
                                    <td class="py-3 text-zinc-400">{{ $export->formattedDuration() ?? '—' }}</td>
                                    <td class="py-3 text-right">
                                        <a href="{{ route('mydumper-exports.show', $export) }}" class="text-indigo-400 hover:text-indigo-300">Detail</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $exports->withQueryString()->links() }}</div>
            </x-ui.card>
        </form>
    @endif
</div>
@endsection
