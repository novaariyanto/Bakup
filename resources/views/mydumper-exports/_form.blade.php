@php
    $alpineConfig = [
        'tablesEndpoint' => url('/mydumper-exports/tables'),
        'previewEndpoint' => route('mydumper-exports.preview-command'),
        'exportType' => old('export_type', $defaults['export_type'] ?? 'full'),
        'selectedTables' => old('selected_tables', []),
        'excludeTables' => old('exclude_tables', []),
        'selectedConnectionId' => old('database_connection_id', ''),
        'options' => old('options', $defaults['options'] ?? []),
        'threads' => old('threads', $defaults['threads'] ?? 4),
        'compression' => (bool) old('compression', $defaults['compression'] ?? false),
        'scheduleType' => old('schedule_type', $defaults['schedule_type'] ?? 'manual'),
    ];
@endphp

<div class="space-y-6" x-data="mydumperExportForm(@js($alpineConfig))">
    <section class="form-section">
        <h2 class="mb-4 text-sm font-semibold text-zinc-100">General</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="mb-1.5 block text-sm text-zinc-300">Nama Job <span class="text-red-400">*</span></label>
                <input name="name" type="text" value="{{ old('name') }}" class="input-field" required placeholder="Production Full Export">
                @error('name') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1.5 block text-sm text-zinc-300">Database Connection <span class="text-red-400">*</span></label>
                <select name="database_connection_id" x-model="selectedConnectionId" class="input-field" required>
                    <option value="">Pilih koneksi...</option>
                    @foreach ($connections as $connection)
                        <option value="{{ $connection->id }}" data-database="{{ $connection->database_name }}">{{ $connection->name }} — {{ $connection->database_name }}</option>
                    @endforeach
                </select>
                @error('database_connection_id') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1.5 block text-sm text-zinc-300">Database</label>
                <input name="database" type="text" class="input-field" placeholder="Kosongkan untuk default koneksi">
                @error('database') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1.5 block text-sm text-zinc-300">Output Folder</label>
                <input name="output_folder" type="text" value="{{ old('output_folder') }}" class="input-field" placeholder="exports/production">
            </div>
            <div>
                <label class="mb-1.5 block text-sm text-zinc-300">Storage Destination <span class="text-red-400">*</span></label>
                <select name="storage_destination_id" class="input-field" required>
                    <option value="">Pilih destination...</option>
                    @foreach ($destinations as $destination)
                        <option value="{{ $destination->id }}" @selected((string) old('storage_destination_id') === (string) $destination->id)>{{ $destination->name }}</option>
                    @endforeach
                </select>
                @error('storage_destination_id') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label class="mb-1.5 block text-sm text-zinc-300">Description</label>
                <textarea name="description" rows="2" class="input-field">{{ old('description') }}</textarea>
            </div>
        </div>
    </section>

    <section class="form-section">
        <h2 class="mb-4 text-sm font-semibold text-zinc-100">Export Type</h2>
        <div class="grid gap-2 sm:grid-cols-2">
            @foreach ($exportTypes as $type)
                <label class="flex items-center gap-2 rounded-lg border border-zinc-800 px-3 py-2 text-sm">
                    <input type="radio" name="export_type" value="{{ $type->value }}" x-model="exportType">
                    <span>{{ $type->label() }}</span>
                </label>
            @endforeach
        </div>
        @error('export_type') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror

        <div x-show="exportType === 'selected_tables'" x-cloak class="mt-4">
            <div class="mb-2 flex gap-2">
                <button type="button" class="btn-secondary text-xs" @click="loadTables()" :disabled="!selectedConnectionId || loadingTables">Muat Tabel</button>
            </div>
            <select name="selected_tables[]" multiple class="input-field h-40">
                <template x-for="table in availableTables" :key="table.name">
                    <option :value="table.name" x-text="table.name" :selected="selectedTables.includes(table.name)"></option>
                </template>
            </select>
            @error('selected_tables') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
        </div>

        <div x-show="exportType === 'exclude_tables'" x-cloak class="mt-4">
            <div class="mb-2 flex gap-2">
                <button type="button" class="btn-secondary text-xs" @click="loadTables()" :disabled="!selectedConnectionId || loadingTables">Muat Tabel</button>
            </div>
            <select name="exclude_tables[]" multiple class="input-field h-40">
                <template x-for="table in availableTables" :key="'ex-' + table.name">
                    <option :value="table.name" x-text="table.name"></option>
                </template>
            </select>
            @error('exclude_tables') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
        </div>
    </section>

    <section class="form-section">
        <h2 class="mb-4 text-sm font-semibold text-zinc-100">Options</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1.5 block text-sm text-zinc-300">Threads</label>
                <input name="threads" type="number" min="1" max="32" x-model.number="threads" class="input-field" value="{{ old('threads', $defaults['threads'] ?? 4) }}">
            </div>
            <div class="flex items-center gap-2 pt-6">
                <input type="hidden" name="compression" value="0">
                <input type="checkbox" name="compression" value="1" x-model="compression" @checked(old('compression'))>
                <span class="text-sm text-zinc-300">Use Compression</span>
            </div>
            <div class="flex items-center gap-2">
                <input type="hidden" name="options[build_empty_files]" value="0">
                <input type="checkbox" name="options[build_empty_files]" value="1">
                <span class="text-sm text-zinc-300">Build Empty Files</span>
            </div>
            <div>
                <label class="mb-1.5 block text-sm text-zinc-300">Chunk Filesize</label>
                <input name="options[chunk_filesize]" type="number" class="input-field" placeholder="64">
            </div>
            <div>
                <label class="mb-1.5 block text-sm text-zinc-300">Rows</label>
                <input name="options[rows]" type="number" class="input-field">
            </div>
            <div>
                <label class="mb-1.5 block text-sm text-zinc-300">Lock Mode</label>
                <select name="options[lock_mode]" class="input-field">
                    @foreach (\App\Enums\MyDumper\MyDumperLockMode::cases() as $lockMode)
                        <option value="{{ $lockMode->value }}">{{ $lockMode->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center gap-2">
                <input type="hidden" name="options[trx_consistency_only]" value="0">
                <input type="checkbox" name="options[trx_consistency_only]" value="1">
                <span class="text-sm text-zinc-300">Trx Consistency Only</span>
            </div>
            <div class="flex items-center gap-2">
                <input type="hidden" name="options[build_metadata]" value="0">
                <input type="checkbox" name="options[build_metadata]" value="1" checked>
                <span class="text-sm text-zinc-300">Build Metadata</span>
            </div>
        </div>
    </section>

    <section class="form-section">
        <h2 class="mb-4 text-sm font-semibold text-zinc-100">Scheduler</h2>
        <select name="schedule_type" x-model="scheduleType" class="input-field sm:max-w-xs">
            @foreach ($scheduleTypes as $scheduleType)
                <option value="{{ $scheduleType->value }}">{{ $scheduleType->label() }}</option>
            @endforeach
        </select>
        <input type="hidden" name="run_immediately" value="1">
    </section>

    <section class="form-section">
        <div class="mb-2 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-zinc-100">Command Preview</h2>
            <button type="button" class="btn-secondary text-xs" @click="previewCommand()">Refresh Preview</button>
        </div>
        <pre class="overflow-x-auto rounded-lg border border-zinc-800 bg-zinc-950 p-4 font-mono text-xs text-zinc-300" x-text="commandPreview || 'Klik Refresh Preview...'"></pre>
    </section>
</div>
