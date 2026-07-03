@php
    $isEdit = isset($profile);
    $showFormSteps = $showFormSteps ?? false;
    $formStep = 0;
    $defaults = array_merge([
        'name' => '',
        'description' => '',
        'database_connection_id' => '',
        'backup_database' => true,
        'backup_folders' => false,
        'include_stored_procedures' => false,
        'include_views' => false,
        'compression' => 'zip',
        'schedule_type' => 'manual',
        'schedule_cron' => '',
        'schedule_time' => '02:00',
        'schedule_day_of_week' => 1,
        'schedule_day_of_month' => 1,
        'retention_type' => 'keep_last',
        'retention_value' => 7,
        'is_active' => true,
        'selected_destination_ids' => [],
        'include_folders' => [],
        'exclude_folders' => [],
        'table_dump_modes' => [],
    ], $formDefaults ?? []);

    $scheduleType = old('schedule_type', $isEdit ? $profile->schedule_type->value : $defaults['schedule_type']);
    $backupDatabase = (bool) old('backup_database', $isEdit ? $profile->backup_database : $defaults['backup_database']);
    $backupFolders = (bool) old('backup_folders', $isEdit ? $profile->backup_folders : $defaults['backup_folders']);
    $includeStoredProcedures = (bool) old('include_stored_procedures', $isEdit ? $profile->include_stored_procedures : $defaults['include_stored_procedures']);
    $includeViews = (bool) old('include_views', $isEdit ? $profile->include_views : $defaults['include_views']);
    $includeFolders = old('include_folders', $isEdit ? $profile->includeFolders->pluck('path')->all() : $defaults['include_folders']);
    $excludeFolders = old('exclude_folders', $isEdit ? $profile->excludeFolders->pluck('path')->all() : $defaults['exclude_folders']);
    $tableDumpModes = old('table_dump_modes', $isEdit
        ? $profile->excludedTables->mapWithKeys(fn ($table) => [$table->table_name => $table->dump_mode->value])->all()
        : $defaults['table_dump_modes']);
    $selectedDestinationIds = old('selected_destination_ids', $isEdit
        ? $profile->destinations->pluck('id')->map(fn ($id) => (int) $id)->all()
        : $defaults['selected_destination_ids']);
    $initialAvailableTables = $availableTables ?? [];
    $initialConnectionId = old('database_connection_id', $isEdit ? $profile->database_connection_id : $defaults['database_connection_id']);

    if ($backupFolders && $includeFolders === []) {
        $includeFolders = [''];
    }

    $alpineConfig = [
        'scheduleType' => $scheduleType,
        'backupDatabase' => $backupDatabase,
        'backupFolders' => $backupFolders,
        'includeFolders' => array_values($includeFolders),
        'excludeFolders' => array_values($excludeFolders),
        'availableTables' => $initialAvailableTables,
        'tableModes' => $tableDumpModes,
        'tablesEndpoint' => url('/backup-profiles/tables'),
        'autoLoadTables' => filled($initialConnectionId) && empty($initialAvailableTables),
    ];
@endphp

<div class="space-y-8" x-data="backupProfileForm(@js($alpineConfig))">
    {{-- 1. Informasi dasar --}}
    <section class="form-section">
        <div class="form-section-header">
            @if ($showFormSteps)
                <span class="form-section-step">{{ ++$formStep }}</span>
            @endif
            <div>
                <h2 class="text-sm font-semibold text-zinc-100">Informasi Dasar</h2>
                <p class="mt-0.5 text-xs text-zinc-500">Nama dan koneksi database sumber backup</p>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="mb-1.5 block text-sm font-medium text-zinc-300">Nama Profile <span class="text-red-400">*</span></label>
                <input
                    name="name"
                    type="text"
                    value="{{ old('name', $isEdit ? $profile->name : $defaults['name']) }}"
                    class="input-field"
                    placeholder="Contoh: Production Daily Backup"
                    required
                />
                @error('name') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label class="mb-1.5 block text-sm font-medium text-zinc-300">Deskripsi <span class="text-zinc-600">(opsional)</span></label>
                <textarea
                    name="description"
                    rows="2"
                    class="input-field"
                    placeholder="Catatan singkat tentang profile ini..."
                >{{ old('description', $isEdit ? ($profile->description ?? '') : $defaults['description']) }}</textarea>
                @error('description') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label class="mb-1.5 block text-sm font-medium text-zinc-300">Koneksi Database <span class="text-red-400">*</span></label>
                @if ($connections->isEmpty())
                    <div class="rounded-lg border border-amber-500/20 bg-amber-500/5 px-4 py-3 text-sm text-amber-300">
                        Belum ada koneksi aktif.
                        <a href="{{ route('database-connections.create') }}" class="font-medium text-amber-200 underline">Tambah koneksi</a>
                    </div>
                @else
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <select
                            name="database_connection_id"
                            x-ref="connectionSelect"
                            class="input-field flex-1"
                            required
                            @change="onConnectionChange()"
                        >
                            <option value="">Pilih koneksi database...</option>
                            @foreach ($connections as $connection)
                                <option value="{{ $connection->id }}" @selected((string) old('database_connection_id', $isEdit ? $profile->database_connection_id : $defaults['database_connection_id']) === (string) $connection->id)>
                                    {{ $connection->name }} — {{ $connection->database_name }}
                                </option>
                            @endforeach
                        </select>
                        <button
                            type="button"
                            class="btn-secondary shrink-0"
                            :disabled="loadingTables || ! $refs.connectionSelect?.value"
                            @click="loadTables()"
                        >
                            <span x-show="!loadingTables">Muat Daftar Tabel</span>
                            <span x-show="loadingTables" x-cloak class="inline-flex items-center gap-2">
                                <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                Memuat...
                            </span>
                        </button>
                    </div>
                    <p class="mt-1.5 text-xs text-zinc-500">Pilih koneksi lalu muat tabel untuk mengatur mode backup per tabel.</p>
                @endif
                @error('database_connection_id') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>
        </div>
    </section>

    {{-- 2. Sumber backup --}}
    <section class="form-section">
        <div class="form-section-header">
            @if ($showFormSteps)
                <span class="form-section-step">{{ ++$formStep }}</span>
            @endif
            <div>
                <h2 class="text-sm font-semibold text-zinc-100">Sumber Backup</h2>
                <p class="mt-0.5 text-xs text-zinc-500">Pilih apa yang akan di-backup dari server</p>
            </div>
        </div>

        <input type="hidden" name="backup_database" value="0">
        <input type="hidden" name="backup_folders" value="0">

        <div class="grid gap-3 sm:grid-cols-2">
            <label class="backup-type-card">
                <input
                    type="checkbox"
                    name="backup_database"
                    value="1"
                    class="sr-only"
                    @checked($backupDatabase)
                    @change="backupDatabase = $event.target.checked"
                >
                <span class="flex items-center gap-2 text-sm font-medium text-zinc-100">
                    <svg class="h-5 w-5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.375c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375" /></svg>
                    Backup Database
                </span>
                <span class="text-xs text-zinc-500">Dump tabel MySQL ke file SQL/ZIP</span>
            </label>

            <label class="backup-type-card">
                <input
                    type="checkbox"
                    name="backup_folders"
                    value="1"
                    class="sr-only"
                    @checked($backupFolders)
                    @change="backupFolders = $event.target.checked"
                >
                <span class="flex items-center gap-2 text-sm font-medium text-zinc-100">
                    <svg class="h-5 w-5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z" /></svg>
                    Backup Folder
                </span>
                <span class="text-xs text-zinc-500">Arsipkan folder tertentu ke dalam backup</span>
            </label>
        </div>
        @error('backup_database') <p class="mt-3 text-xs text-red-400">{{ $message }}</p> @enderror

        <div x-show="backupDatabase" x-cloak class="mt-5 space-y-4 border-t border-zinc-800/80 pt-5">
            <div>
                <p class="mb-2 text-xs font-medium uppercase tracking-wide text-zinc-500">Objek database tambahan</p>
                <div class="flex flex-wrap gap-4">
                    <label class="flex items-center gap-2 rounded-lg border border-zinc-800 px-3 py-2 text-sm text-zinc-300 hover:bg-zinc-900/50">
                        <input type="hidden" name="include_stored_procedures" value="0">
                        <input type="checkbox" name="include_stored_procedures" value="1" @checked($includeStoredProcedures) class="rounded border-zinc-600 bg-zinc-800 text-indigo-600 focus:ring-indigo-500/30">
                        Stored procedure &amp; function
                    </label>
                    <label class="flex items-center gap-2 rounded-lg border border-zinc-800 px-3 py-2 text-sm text-zinc-300 hover:bg-zinc-900/50">
                        <input type="hidden" name="include_views" value="0">
                        <input type="checkbox" name="include_views" value="1" @checked($includeViews) class="rounded border-zinc-600 bg-zinc-800 text-indigo-600 focus:ring-indigo-500/30">
                        View
                    </label>
                </div>
            </div>

            {{-- Mode tabel --}}
            <div class="rounded-lg border border-zinc-800 bg-zinc-950/40 p-4">
                <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="text-sm font-medium text-zinc-200">Mode Backup Tabel</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <span class="badge badge-zinc">With Data — schema + data</span>
                            <span class="badge badge-amber">Structure Only — schema saja</span>
                            <span class="badge badge-red">Exclude — dilewati</span>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" @click="setAllStructureOnly()" class="btn-secondary text-xs" :disabled="availableTables.length === 0">Semua Structure Only</button>
                        <button type="button" @click="setAllExclude()" class="btn-secondary text-xs" :disabled="availableTables.length === 0">Semua Exclude</button>
                        <button type="button" @click="resetTableModes()" class="btn-secondary text-xs">Reset</button>
                    </div>
                </div>

                <div x-show="availableTables.length > 0" x-cloak class="mb-4 flex flex-wrap gap-2 text-xs">
                    <span class="rounded-full bg-zinc-800 px-2.5 py-1 text-zinc-400"><span x-text="availableTables.length"></span> tabel</span>
                    <span class="rounded-full bg-zinc-800 px-2.5 py-1 text-zinc-400"><span x-text="tableModeStats().withData"></span> with data</span>
                    <span class="rounded-full bg-amber-500/10 px-2.5 py-1 text-amber-300"><span x-text="tableModeStats().structureOnly"></span> structure only</span>
                    <span class="rounded-full bg-red-500/10 px-2.5 py-1 text-red-300"><span x-text="tableModeStats().exclude"></span> exclude</span>
                </div>

                <div x-show="availableTables.length === 0 && !tablesError && !loadingTables" class="rounded-lg border border-dashed border-zinc-700 bg-zinc-900/30 px-4 py-8 text-center">
                    <svg class="mx-auto h-8 w-8 text-zinc-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.375c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375" /></svg>
                    <p class="mt-3 text-sm text-zinc-400">Belum ada daftar tabel</p>
                    <p class="mt-1 text-xs text-zinc-500">Pilih koneksi database di atas, lalu klik <strong class="text-zinc-400">Muat Daftar Tabel</strong></p>
                </div>

                <div x-show="loadingTables" x-cloak class="flex items-center justify-center gap-2 py-6 text-sm text-zinc-400">
                    <svg class="h-5 w-5 animate-spin text-indigo-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    Memuat daftar tabel...
                </div>

                <div x-show="tablesError" x-cloak class="rounded-lg border border-red-500/20 bg-red-500/10 p-3 text-sm text-red-300" x-text="tablesError"></div>

                <div x-show="availableTables.length > 0" x-cloak>
                    <div class="mb-3">
                        <input x-model="tableSearch" type="text" class="input-field" placeholder="Cari nama tabel..." />
                    </div>
                    <div class="overflow-hidden rounded-lg border border-zinc-800">
                        <div class="hidden grid-cols-[1fr_auto_auto] gap-3 border-b border-zinc-800 bg-zinc-900/80 px-3 py-2 text-xs font-medium uppercase tracking-wide text-zinc-500 sm:grid">
                            <span>Tabel</span>
                            <span class="hidden sm:inline">Info</span>
                            <span>Mode</span>
                        </div>
                        <div class="max-h-72 overflow-y-auto">
                            <template x-for="table in filteredTables()" :key="table.name">
                                <div
                                    class="grid grid-cols-1 items-center gap-2 border-b border-zinc-800/60 px-3 py-2.5 text-sm last:border-0 sm:grid-cols-[1fr_auto_auto] sm:gap-3"
                                    :class="modeRowClass(table.name)"
                                >
                                    <span class="font-mono font-medium text-zinc-200" x-text="table.name"></span>
                                    <span class="text-xs text-zinc-500 sm:text-right" x-text="tableMeta(table)"></span>
                                    <select
                                        class="input-field w-full py-1.5 text-xs sm:w-44"
                                        :class="modeSelectClass(table.name)"
                                        :value="tableMode(table.name)"
                                        @change="setTableMode(table.name, $event.target.value)"
                                    >
                                        <option value="with_data">With Data</option>
                                        <option value="structure_only">Structure Only</option>
                                        <option value="exclude">Exclude</option>
                                    </select>
                                </div>
                            </template>
                            <p x-show="filteredTables().length === 0" x-cloak class="px-3 py-6 text-center text-sm text-zinc-500">Tidak ada tabel cocok dengan pencarian.</p>
                        </div>
                    </div>
                </div>

                <div class="mt-4 border-t border-zinc-800/80 pt-4">
                    <label class="mb-2 block text-sm font-medium text-zinc-300">Tambah tabel manual</label>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <input
                            x-model="manualTableInput"
                            type="text"
                            class="input-field flex-1 font-mono text-sm"
                            placeholder="activity_log, sessions, cache"
                            @keydown.enter.prevent="addManualTables()"
                        >
                        <select x-model="manualTableMode" class="input-field w-full shrink-0 text-sm sm:w-44">
                            <option value="with_data">With Data</option>
                            <option value="structure_only">Structure Only</option>
                            <option value="exclude">Exclude</option>
                        </select>
                        <button type="button" class="btn-secondary shrink-0" @click="addManualTables()">Tambah</button>
                    </div>
                    <p x-show="manualTableError" x-cloak class="mt-2 text-xs text-red-400" x-text="manualTableError"></p>

                    <div x-show="configuredTableEntries.length > 0" x-cloak class="mt-3">
                        <p class="mb-2 text-xs font-medium text-zinc-500">Tabel dikonfigurasi (<span x-text="configuredTableEntries.length"></span>)</p>
                        <div class="flex flex-wrap gap-2">
                            <template x-for="entry in configuredTableEntries" :key="'tag-' + entry[0]">
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1 font-mono text-xs"
                                    :class="modeTagClass(entry[1])"
                                >
                                    <span x-text="entry[0]"></span>
                                    <span class="opacity-70" x-text="tableModeLabel(entry[1])"></span>
                                    <button type="button" class="rounded p-0.5 opacity-60 hover:opacity-100" @click="removeConfiguredTable(entry[0])">&times;</button>
                                </span>
                            </template>
                        </div>
                    </div>
                </div>

                <template x-for="entry in configuredTableEntries" :key="'mode-' + entry[0]">
                    <input type="hidden" :name="'table_dump_modes[' + entry[0] + ']'" :value="entry[1]">
                </template>
                @error('table_dump_modes') <p class="mt-2 text-xs text-red-400">{{ $message }}</p> @enderror
                @error('table_dump_modes.*') <p class="mt-2 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>
        </div>

        <div x-show="backupFolders" x-cloak class="mt-5 space-y-4 border-t border-zinc-800/80 pt-5">
            <div>
                <div class="mb-2 flex items-center justify-between">
                    <p class="text-sm font-medium text-zinc-300">Folder yang di-backup</p>
                    <button type="button" @click="addIncludeFolder()" class="text-xs font-medium text-indigo-400 hover:text-indigo-300">+ Tambah folder</button>
                </div>
                <template x-for="(folder, index) in includeFolders" :key="'include-' + index">
                    <div class="mb-2 flex gap-2">
                        <input :name="'include_folders[' + index + ']'" x-model="includeFolders[index]" type="text" class="input-field font-mono text-sm" placeholder="storage/app" />
                        <button type="button" @click="removeIncludeFolder(index)" class="rounded-lg px-3 text-zinc-500 hover:bg-zinc-800 hover:text-red-400" title="Hapus">&times;</button>
                    </div>
                </template>
                <button type="button" x-show="includeFolders.length === 0" @click="addIncludeFolder()" class="btn-secondary text-xs">Tambah folder</button>
                @error('include_folders') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                @error('include_folders.*') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>
            <div>
                <div class="mb-2 flex items-center justify-between">
                    <p class="text-sm font-medium text-zinc-300">Folder dikecualikan</p>
                    <button type="button" @click="addExcludeFolder()" class="text-xs font-medium text-indigo-400 hover:text-indigo-300">+ Tambah exclude</button>
                </div>
                <template x-for="(folder, index) in excludeFolders" :key="'exclude-' + index">
                    <div class="mb-2 flex gap-2">
                        <input :name="'exclude_folders[' + index + ']'" x-model="excludeFolders[index]" type="text" class="input-field font-mono text-sm" placeholder="storage/logs" />
                        <button type="button" @click="removeExcludeFolder(index)" class="rounded-lg px-3 text-zinc-500 hover:bg-zinc-800 hover:text-red-400" title="Hapus">&times;</button>
                    </div>
                </template>
            </div>
        </div>
    </section>

    {{-- 3. Penyimpanan --}}
    <section class="form-section">
        <div class="form-section-header">
            @if ($showFormSteps)
                <span class="form-section-step">{{ ++$formStep }}</span>
            @endif
            <div>
                <h2 class="text-sm font-semibold text-zinc-100">Penyimpanan</h2>
                <p class="mt-0.5 text-xs text-zinc-500">Pilih lokasi tujuan file backup <span class="text-red-400">*</span></p>
            </div>
        </div>

        @if ($destinations->isEmpty())
            <div class="rounded-lg border border-amber-500/20 bg-amber-500/5 px-4 py-3 text-sm text-amber-300">
                Belum ada destination aktif.
                <a href="{{ route('storage-destinations.create') }}" class="font-medium text-amber-200 underline">Tambah destination</a>
            </div>
        @else
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($destinations as $destination)
                    <label class="backup-type-card cursor-pointer">
                        <input
                            type="checkbox"
                            name="selected_destination_ids[]"
                            value="{{ $destination->id }}"
                            class="sr-only"
                            @checked(in_array($destination->id, $selectedDestinationIds))
                        >
                        <span class="flex items-center justify-between gap-2">
                            <span class="text-sm font-medium text-zinc-100">{{ $destination->name }}</span>
                            <x-ui.badge color="zinc">{{ $destination->driverLabel() }}</x-ui.badge>
                        </span>
                        <span class="text-xs text-zinc-500">{{ $destination->summary() }}</span>
                    </label>
                @endforeach
            </div>
        @endif
        @error('selected_destination_ids') <p class="mt-2 text-xs text-red-400">{{ $message }}</p> @enderror
    </section>

    {{-- 4. Jadwal & retention --}}
    <section class="form-section">
        <div class="form-section-header">
            @if ($showFormSteps)
                <span class="form-section-step">{{ ++$formStep }}</span>
            @endif
            <div>
                <h2 class="text-sm font-semibold text-zinc-100">Jadwal &amp; Retention</h2>
                <p class="mt-0.5 text-xs text-zinc-500">Kompresi, penjadwalan otomatis, dan kebijakan penyimpanan</p>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-zinc-300">Kompresi</label>
                <select name="compression" class="input-field">
                    @foreach ($compressionTypes as $type)
                        <option value="{{ $type->value }}" @selected(old('compression', $isEdit ? $profile->compression->value : $defaults['compression']) === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </select>
                <p class="mt-1.5 text-xs text-zinc-500">Disarankan: ZIP (dump SQL + arsip ZIP)</p>
                @if (! config('backup.gzip_enabled'))
                    <p class="mt-1 text-xs text-amber-400/90">GZIP dump dinonaktifkan di server.</p>
                @endif
                @error('compression') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-zinc-300">Jadwal</label>
                <select name="schedule_type" x-model="scheduleType" class="input-field">
                    @foreach ($scheduleTypes as $type)
                        <option value="{{ $type->value }}" @selected(old('schedule_type', $scheduleType) === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </select>
                @error('schedule_type') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>
            <div x-show="scheduleType === 'custom_cron'" x-cloak class="sm:col-span-2">
                <label class="mb-1.5 block text-sm font-medium text-zinc-300">Cron Expression</label>
                <input name="schedule_cron" type="text" value="{{ old('schedule_cron', $isEdit ? ($profile->schedule_cron ?? '') : $defaults['schedule_cron']) }}" class="input-field font-mono" placeholder="0 2 * * *" />
                @error('schedule_cron') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>
            <div x-show="['daily', 'weekly', 'monthly'].includes(scheduleType)" x-cloak>
                <label class="mb-1.5 block text-sm font-medium text-zinc-300">Waktu</label>
                <input name="schedule_time" type="time" value="{{ old('schedule_time', $isEdit && $profile->schedule_time ? substr((string) $profile->schedule_time, 0, 5) : $defaults['schedule_time']) }}" class="input-field" />
                @error('schedule_time') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>
            <div x-show="scheduleType === 'weekly'" x-cloak>
                <label class="mb-1.5 block text-sm font-medium text-zinc-300">Hari</label>
                <select name="schedule_day_of_week" class="input-field">
                    @foreach (['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'] as $dayIndex => $dayLabel)
                        <option value="{{ $dayIndex }}" @selected((int) old('schedule_day_of_week', $isEdit ? ($profile->schedule_day_of_week ?? 1) : $defaults['schedule_day_of_week']) === $dayIndex)>{{ $dayLabel }}</option>
                    @endforeach
                </select>
                @error('schedule_day_of_week') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>
            <div x-show="scheduleType === 'monthly'" x-cloak>
                <label class="mb-1.5 block text-sm font-medium text-zinc-300">Tanggal</label>
                <input name="schedule_day_of_month" type="number" min="1" max="31" value="{{ old('schedule_day_of_month', $isEdit ? ($profile->schedule_day_of_month ?? 1) : $defaults['schedule_day_of_month']) }}" class="input-field" />
                @error('schedule_day_of_month') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-zinc-300">Retention</label>
                <select name="retention_type" class="input-field">
                    @foreach ($retentionTypes as $type)
                        <option value="{{ $type->value }}" @selected(old('retention_type', $isEdit ? $profile->retention_type->value : $defaults['retention_type']) === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </select>
                @error('retention_type') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-zinc-300">Nilai retention</label>
                <input name="retention_value" type="number" min="1" value="{{ old('retention_value', $isEdit ? $profile->retention_value : $defaults['retention_value']) }}" class="input-field" />
                @error('retention_value') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>
        </div>

        <label class="mt-5 flex items-center gap-2 rounded-lg border border-zinc-800 px-3 py-2.5 text-sm text-zinc-300 w-fit">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $isEdit ? $profile->is_active : $defaults['is_active'])) class="rounded border-zinc-600 bg-zinc-800 text-indigo-600 focus:ring-indigo-500/30">
            Aktifkan profile setelah disimpan
        </label>
    </section>
</div>
