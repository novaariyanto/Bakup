@php
    $isEdit = isset($profile);
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
        'excluded_table_names' => [],
    ], $formDefaults ?? []);

    $scheduleType = old('schedule_type', $isEdit ? $profile->schedule_type->value : $defaults['schedule_type']);
    $backupDatabase = (bool) old('backup_database', $isEdit ? $profile->backup_database : $defaults['backup_database']);
    $backupFolders = (bool) old('backup_folders', $isEdit ? $profile->backup_folders : $defaults['backup_folders']);
    $includeStoredProcedures = (bool) old('include_stored_procedures', $isEdit ? $profile->include_stored_procedures : $defaults['include_stored_procedures']);
    $includeViews = (bool) old('include_views', $isEdit ? $profile->include_views : $defaults['include_views']);
    $includeFolders = old('include_folders', $isEdit ? $profile->includeFolders->pluck('path')->all() : $defaults['include_folders']);
    $excludeFolders = old('exclude_folders', $isEdit ? $profile->excludeFolders->pluck('path')->all() : $defaults['exclude_folders']);
    $excludedTableNames = old('excluded_table_names', $isEdit ? $profile->excludedTables->pluck('table_name')->all() : $defaults['excluded_table_names']);
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
        'excludedTableNames' => array_values($excludedTableNames),
        'tablesEndpoint' => url('/backup-profiles/tables'),
        'autoLoadTables' => filled($initialConnectionId) && empty($initialAvailableTables),
    ];
@endphp

<div class="space-y-6" x-data="backupProfileForm(@js($alpineConfig))">
    <div class="grid gap-4 sm:grid-cols-2">
        <div class="sm:col-span-2">
            <label class="mb-1.5 block text-sm font-medium text-zinc-300">Nama</label>
            <input name="name" type="text" value="{{ old('name', $isEdit ? $profile->name : $defaults['name']) }}" class="input-field" required />
            @error('name') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
        </div>
        <div class="sm:col-span-2">
            <label class="mb-1.5 block text-sm font-medium text-zinc-300">Deskripsi</label>
            <textarea name="description" rows="2" class="input-field">{{ old('description', $isEdit ? ($profile->description ?? '') : $defaults['description']) }}</textarea>
            @error('description') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
        </div>
        <div class="sm:col-span-2">
            <label class="mb-1.5 block text-sm font-medium text-zinc-300">Database Connection</label>
            @if ($connections->isEmpty())
                <p class="text-sm text-amber-400">
                    Belum ada koneksi database aktif.
                    <a href="{{ route('database-connections.create') }}" class="text-indigo-400 hover:underline">Tambah koneksi</a>
                </p>
            @else
                <div class="flex gap-2">
                    <select name="database_connection_id" x-ref="connectionSelect" class="input-field flex-1" required>
                        <option value="">Pilih koneksi...</option>
                        @foreach ($connections as $connection)
                            <option value="{{ $connection->id }}" @selected((string) old('database_connection_id', $isEdit ? $profile->database_connection_id : $defaults['database_connection_id']) === (string) $connection->id)>
                                {{ $connection->name }} ({{ $connection->database_name }})
                            </option>
                        @endforeach
                    </select>
                    <button
                        type="button"
                        class="btn-secondary shrink-0"
                        :disabled="loadingTables"
                        @click="loadTables()"
                    >
                        <span x-show="!loadingTables">Muat Tabel</span>
                        <span x-show="loadingTables" x-cloak>Memuat...</span>
                    </button>
                </div>
            @endif
            @error('database_connection_id') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="rounded-lg border border-zinc-800 p-4">
        <p class="mb-3 text-sm font-medium text-zinc-300">Tipe Backup</p>
        <div class="flex flex-wrap gap-4">
            <label class="flex items-center gap-2 text-sm text-zinc-300">
                <input type="hidden" name="backup_database" value="0">
                <input
                    name="backup_database"
                    type="checkbox"
                    value="1"
                    @checked($backupDatabase)
                    @change="backupDatabase = $event.target.checked"
                    class="rounded border-zinc-600 bg-zinc-800 text-indigo-600 focus:ring-indigo-500/30"
                >
                Backup Database
            </label>
            <label class="flex items-center gap-2 text-sm text-zinc-300">
                <input type="hidden" name="backup_folders" value="0">
                <input
                    name="backup_folders"
                    type="checkbox"
                    value="1"
                    @checked($backupFolders)
                    @change="backupFolders = $event.target.checked"
                    class="rounded border-zinc-600 bg-zinc-800 text-indigo-600 focus:ring-indigo-500/30"
                >
                Backup Folder
            </label>
        </div>
        @error('backup_database') <p class="mt-2 text-xs text-red-400">{{ $message }}</p> @enderror

        <div x-show="backupDatabase" x-cloak class="mt-4 space-y-2 border-t border-zinc-800 pt-4">
            <p class="text-xs font-medium uppercase tracking-wide text-zinc-500">Objek Database (opsional)</p>
            <label class="flex items-center gap-2 text-sm text-zinc-300">
                <input type="hidden" name="include_stored_procedures" value="0">
                <input
                    name="include_stored_procedures"
                    type="checkbox"
                    value="1"
                    @checked($includeStoredProcedures)
                    class="rounded border-zinc-600 bg-zinc-800 text-indigo-600 focus:ring-indigo-500/30"
                >
                Sertakan stored procedure &amp; function
            </label>
            <label class="flex items-center gap-2 text-sm text-zinc-300">
                <input type="hidden" name="include_views" value="0">
                <input
                    name="include_views"
                    type="checkbox"
                    value="1"
                    @checked($includeViews)
                    class="rounded border-zinc-600 bg-zinc-800 text-indigo-600 focus:ring-indigo-500/30"
                >
                Sertakan view
            </label>
            <p class="text-xs text-zinc-500">Default: hanya tabel. Centang untuk ikut backup saat diperlukan.</p>
        </div>
    </div>

    <div x-show="backupDatabase" x-cloak class="rounded-lg border border-zinc-800 p-4">
        <div class="mb-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-medium text-zinc-300">Excluded Tables</p>
                <p class="text-xs text-zinc-500">Centang dari daftar atau tambah manual (pisahkan dengan koma)</p>
            </div>
            <div class="flex gap-2">
                <button type="button" @click="selectAllTables()" class="btn-secondary text-xs" :disabled="availableTables.length === 0">Pilih Semua</button>
                <button type="button" @click="clearExcludedTables()" class="btn-secondary text-xs">Hapus Semua</button>
            </div>
        </div>

        <div x-show="availableTables.length === 0 && !tablesError && !loadingTables" class="text-sm text-zinc-500">
            Pilih koneksi database lalu klik Muat Tabel untuk melihat daftar tabel.
        </div>

        <div x-show="loadingTables" x-cloak class="text-sm text-zinc-400">Memuat daftar tabel...</div>

        <div x-show="tablesError" x-cloak class="rounded-lg border border-red-500/20 bg-red-500/10 p-3 text-sm text-red-300" x-text="tablesError"></div>

        <div x-show="availableTables.length > 0" x-cloak>
            <div class="mb-3">
                <input x-model="tableSearch" type="text" class="input-field" placeholder="Cari tabel..." />
            </div>
            <div class="max-h-48 overflow-y-auto rounded-lg border border-zinc-800">
                <template x-for="table in filteredTables()" :key="table.name">
                    <label class="flex items-center gap-3 border-b border-zinc-800/60 px-3 py-2 text-sm last:border-0 hover:bg-zinc-900/50">
                        <input
                            type="checkbox"
                            :checked="isExcluded(table.name)"
                            @change="toggleExcluded(table.name)"
                            data-excluded-table
                            class="rounded border-zinc-600 bg-zinc-800 text-indigo-600 focus:ring-indigo-500/30"
                        >
                        <span class="flex-1 font-medium text-zinc-200" x-text="table.name"></span>
                        <span class="text-xs text-zinc-500" x-text="tableMeta(table)"></span>
                    </label>
                </template>
            </div>
        </div>

        <div class="mt-4 border-t border-zinc-800 pt-4">
            <label class="mb-1.5 block text-sm font-medium text-zinc-300">Tambah Manual</label>
            <div class="flex flex-col gap-2 sm:flex-row">
                <input
                    x-model="manualTableInput"
                    type="text"
                    class="input-field flex-1 font-mono text-sm"
                    placeholder="activity_log, backup_destinations, backup_histories"
                    @keydown.enter.prevent="addManualTables()"
                >
                <button type="button" class="btn-secondary shrink-0" @click="addManualTables()">Tambah</button>
            </div>
            <p class="mt-1 text-xs text-zinc-500">Gunakan nama tabel persis seperti di database. Beberapa nama bisa dipisah koma.</p>
            <p x-show="manualTableError" x-cloak class="mt-2 text-xs text-red-400" x-text="manualTableError"></p>

            <div x-show="excludedTableNames.length > 0" x-cloak class="mt-3">
                <p class="mb-2 text-xs font-medium uppercase tracking-wide text-zinc-500">Tabel di-exclude (<span x-text="excludedTableNames.length"></span>)</p>
                <div class="flex flex-wrap gap-2">
                    <template x-for="name in excludedTableNames" :key="'tag-' + name">
                        <span class="inline-flex items-center gap-1 rounded-lg border border-zinc-700 bg-zinc-900/60 px-2.5 py-1 font-mono text-xs text-zinc-200">
                            <span x-text="name"></span>
                            <button
                                type="button"
                                class="rounded p-0.5 text-zinc-500 hover:bg-zinc-800 hover:text-red-400"
                                @click="removeExcluded(name)"
                                title="Hapus"
                            >&times;</button>
                        </span>
                    </template>
                </div>
            </div>
        </div>

        <template x-for="name in excludedTableNames" :key="'excluded-' + name">
            <input type="hidden" name="excluded_table_names[]" :value="name">
        </template>
        @error('excluded_table_names') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
        @error('excluded_table_names.*') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
    </div>

    <div x-show="backupFolders" x-cloak class="rounded-lg border border-zinc-800 p-4 space-y-4">
        <div>
            <div class="mb-2 flex items-center justify-between">
                <p class="text-sm font-medium text-zinc-300">Include Folders</p>
                <button type="button" @click="addIncludeFolder()" class="text-xs text-indigo-400 hover:text-indigo-300">+ Tambah</button>
            </div>
            <template x-for="(folder, index) in includeFolders" :key="'include-' + index">
                <div class="mb-2 flex gap-2">
                    <input :name="'include_folders[' + index + ']'" x-model="includeFolders[index]" type="text" class="input-field" placeholder="storage/app" />
                    <button type="button" @click="removeIncludeFolder(index)" class="rounded-lg px-3 text-zinc-500 hover:bg-zinc-800 hover:text-red-400">&times;</button>
                </div>
            </template>
            <button type="button" x-show="includeFolders.length === 0" @click="addIncludeFolder()" class="btn-secondary text-xs">Tambah Folder</button>
            @error('include_folders') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            @error('include_folders.*') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
        </div>
        <div>
            <div class="mb-2 flex items-center justify-between">
                <p class="text-sm font-medium text-zinc-300">Exclude Folders</p>
                <button type="button" @click="addExcludeFolder()" class="text-xs text-indigo-400 hover:text-indigo-300">+ Tambah</button>
            </div>
            <template x-for="(folder, index) in excludeFolders" :key="'exclude-' + index">
                <div class="mb-2 flex gap-2">
                    <input :name="'exclude_folders[' + index + ']'" x-model="excludeFolders[index]" type="text" class="input-field" placeholder="storage/logs" />
                    <button type="button" @click="removeExcludeFolder(index)" class="rounded-lg px-3 text-zinc-500 hover:bg-zinc-800 hover:text-red-400">&times;</button>
                </div>
            </template>
        </div>
    </div>

    <div class="rounded-lg border border-zinc-800 p-4">
        <p class="mb-3 text-sm font-medium text-zinc-300">Storage Destinations</p>
        @if ($destinations->isEmpty())
            <p class="text-sm text-amber-400">
                Belum ada destination aktif.
                <a href="{{ route('storage-destinations.create') }}" class="text-indigo-400 hover:underline">Tambah destination</a>
            </p>
        @else
            <div class="grid gap-2 sm:grid-cols-2">
                @foreach ($destinations as $destination)
                    <label class="flex items-center gap-2 rounded-lg border border-zinc-800 px-3 py-2 text-sm hover:bg-zinc-900/50">
                        <input
                            type="checkbox"
                            name="selected_destination_ids[]"
                            value="{{ $destination->id }}"
                            @checked(in_array($destination->id, $selectedDestinationIds))
                            class="rounded border-zinc-600 bg-zinc-800 text-indigo-600 focus:ring-indigo-500/30"
                        >
                        <span class="text-zinc-200">{{ $destination->name }}</span>
                        <x-ui.badge color="zinc">{{ $destination->driverLabel() }}</x-ui.badge>
                    </label>
                @endforeach
            </div>
        @endif
        @error('selected_destination_ids') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="mb-1.5 block text-sm font-medium text-zinc-300">Compression</label>
            <select name="compression" class="input-field">
                @foreach ($compressionTypes as $type)
                    <option value="{{ $type->value }}" @selected(old('compression', $isEdit ? $profile->compression->value : $defaults['compression']) === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </select>
            <p class="mt-1.5 text-xs text-zinc-500">
                ZIP = dump SQL + arsip ZIP (disarankan). GZIP Dump DB = butuh binary gzip di server.
            </p>
            @if (! config('backup.gzip_enabled'))
                <p class="mt-1 text-xs text-amber-400/90">GZIP dump DB dinonaktifkan di server (BACKUP_GZIP_ENABLED=false).</p>
            @endif
            @error('compression') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="mb-1.5 block text-sm font-medium text-zinc-300">Schedule</label>
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
            <input
                name="schedule_time"
                type="time"
                value="{{ old('schedule_time', $isEdit && $profile->schedule_time ? substr((string) $profile->schedule_time, 0, 5) : $defaults['schedule_time']) }}"
                class="input-field"
            />
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
            <label class="mb-1.5 block text-sm font-medium text-zinc-300">Nilai Retention</label>
            <input name="retention_value" type="number" min="1" value="{{ old('retention_value', $isEdit ? $profile->retention_value : $defaults['retention_value']) }}" class="input-field" />
            @error('retention_value') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
        </div>
    </div>

    <label class="flex items-center gap-2 text-sm text-zinc-300">
        <input type="hidden" name="is_active" value="0">
        <input
            name="is_active"
            type="checkbox"
            value="1"
            @checked(old('is_active', $isEdit ? $profile->is_active : $defaults['is_active']))
            class="rounded border-zinc-600 bg-zinc-800 text-indigo-600 focus:ring-indigo-500/30"
        >
        Profile aktif
    </label>
</div>
