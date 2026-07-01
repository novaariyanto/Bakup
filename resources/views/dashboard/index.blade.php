@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="mb-6">
        <h1 class="text-lg font-semibold text-zinc-100">Dashboard</h1>
        <p class="text-sm text-zinc-500">Ringkasan backup dan status sistem</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat-card
            label="Backup Profiles"
            :value="$stats['profiles']"
            :change="$stats['profiles_total'].' total ('.$stats['profiles'].' aktif)'"
        />
        <x-ui.stat-card label="Total Backup" :value="$stats['backups_total']" change="Semua waktu" />
        <x-ui.stat-card label="Sukses" :value="$stats['backups_success']" change="30 hari terakhir" />
        <x-ui.stat-card label="Gagal" :value="$stats['backups_failed']" change="30 hari terakhir" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <x-ui.card title="Backup Activity" description="Aktivitas 30 hari terakhir">
                @if (collect($activityChart)->sum('total') === 0)
                    <x-ui.empty-state
                        title="Belum ada data backup"
                        description="Grafik akan muncul setelah backup pertama dijalankan."
                    />
                @else
                    <x-ui.activity-chart :data="$activityChart" />
                @endif
            </x-ui.card>
        </div>

        <div>
            <x-ui.card title="Informasi Sistem" description="Status aplikasi">
                <dl class="space-y-4 text-sm">
                    <div>
                        <dt class="text-zinc-500">Backup berikutnya</dt>
                        <dd class="mt-1 font-medium text-zinc-200">
                            {{ $stats['next_backup'] ?? '—' }}
                        </dd>
                        @if ($stats['next_backup_profile'] ?? null)
                            <dd class="mt-0.5 text-xs text-zinc-500">{{ $stats['next_backup_profile'] }}</dd>
                        @endif
                    </div>
                    <div>
                        <dt class="text-zinc-500">Backup terakhir</dt>
                        <dd class="mt-1 font-medium text-zinc-200">
                            {{ $stats['last_backup'] ?? '—' }}
                        </dd>
                        @if ($stats['last_backup_profile'] ?? null)
                            <dd class="mt-0.5 text-xs text-zinc-500">{{ $stats['last_backup_profile'] }}</dd>
                        @endif
                    </div>
                    <div>
                        <dt class="text-zinc-500">Storage terpakai</dt>
                        <dd class="mt-1 font-medium text-zinc-200">{{ $stats['storage_used_label'] }}</dd>
                        <dd class="mt-0.5 text-xs text-zinc-500">Total backup sukses tercatat</dd>
                    </div>
                </dl>
            </x-ui.card>
        </div>
    </div>

    <div class="mt-6">
        <x-ui.card title="Recent Activity" description="Riwayat backup terbaru">
            @if (count($recentActivity) === 0)
                <x-ui.empty-state
                    title="Belum ada aktivitas"
                    description="Timeline aktivitas akan tercatat otomatis saat backup dijalankan."
                />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-zinc-800 text-zinc-500">
                                <th class="pb-3 font-medium">Profile</th>
                                <th class="pb-3 font-medium">Status</th>
                                <th class="pb-3 font-medium">Waktu</th>
                                <th class="pb-3 font-medium">Detail</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-800/80">
                            @foreach ($recentActivity as $activity)
                                <tr>
                                    <td class="py-3 font-medium text-zinc-200">{{ $activity['profile_name'] }}</td>
                                    <td class="py-3">
                                        <x-ui.badge :color="$activity['status_color']">{{ $activity['status_label'] }}</x-ui.badge>
                                    </td>
                                    <td class="py-3 text-zinc-400">
                                        <span>{{ $activity['occurred_at'] }}</span>
                                        <span class="block text-xs text-zinc-500">{{ $activity['relative_at'] }}</span>
                                    </td>
                                    <td class="py-3 text-zinc-400">
                                        @if ($activity['filename'])
                                            <span class="block truncate max-w-[200px]" title="{{ $activity['filename'] }}">{{ $activity['filename'] }}</span>
                                        @elseif ($activity['message'])
                                            <span class="block truncate max-w-[200px]" title="{{ $activity['message'] }}">{{ $activity['message'] }}</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-4 text-right">
                    <a href="{{ route('backup-history.index') }}" class="text-xs font-medium text-indigo-400 hover:text-indigo-300">
                        Lihat semua riwayat →
                    </a>
                </div>
            @endif
        </x-ui.card>
    </div>
@endsection
