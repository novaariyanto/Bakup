export function registerBackupProfileForm(Alpine) {
    Alpine.data('backupProfileForm', (config) => ({
        scheduleType: config.scheduleType,
        backupDatabase: config.backupDatabase,
        backupFolders: config.backupFolders,
        includeFolders: config.includeFolders ?? [],
        excludeFolders: config.excludeFolders ?? [],
        tableSearch: '',
        availableTables: config.availableTables ?? [],
        tableModes: config.tableModes ?? {},
        manualTableInput: '',
        manualTableMode: 'structure_only',
        manualTableError: null,
        loadingTables: false,
        tablesError: null,
        tablesEndpoint: config.tablesEndpoint,
        autoLoadTables: config.autoLoadTables ?? false,

        init() {
            if (this.autoLoadTables && this.$refs.connectionSelect?.value) {
                this.loadTables();
            }
        },

        addIncludeFolder() {
            this.includeFolders.push('');
        },

        removeIncludeFolder(index) {
            this.includeFolders.splice(index, 1);
        },

        addExcludeFolder() {
            this.excludeFolders.push('');
        },

        removeExcludeFolder(index) {
            this.excludeFolders.splice(index, 1);
        },

        filteredTables() {
            const query = this.tableSearch.trim().toLowerCase();

            if (!query) {
                return this.availableTables;
            }

            return this.availableTables.filter((table) => table.name.toLowerCase().includes(query));
        },

        tableMode(name) {
            return this.tableModes[name] ?? 'with_data';
        },

        tableModeLabel(mode) {
            return {
                with_data: 'With Data',
                structure_only: 'Structure Only',
                exclude: 'Exclude',
            }[mode] ?? mode;
        },

        tableModeStats() {
            let structureOnly = 0;
            let exclude = 0;

            this.availableTables.forEach((table) => {
                const mode = this.tableMode(table.name);
                if (mode === 'structure_only') structureOnly++;
                if (mode === 'exclude') exclude++;
            });

            Object.entries(this.tableModes).forEach(([name, mode]) => {
                if (this.loadedTableNames().has(name)) return;
                if (mode === 'structure_only') structureOnly++;
                if (mode === 'exclude') exclude++;
            });

            const total = this.availableTables.length + this.manualConfiguredTables().length;
            const withData = total - structureOnly - exclude;

            return { withData, structureOnly, exclude };
        },

        modeRowClass(name) {
            const mode = this.tableMode(name);
            if (mode === 'exclude') return 'bg-red-500/5';
            if (mode === 'structure_only') return 'bg-amber-500/5';
            return 'hover:bg-zinc-900/40';
        },

        modeSelectClass(name) {
            return 'table-mode-select-' + this.tableMode(name);
        },

        modeTagClass(mode) {
            return {
                with_data: 'border-zinc-700 bg-zinc-900/60 text-zinc-200',
                structure_only: 'border-amber-500/30 bg-amber-500/10 text-amber-200',
                exclude: 'border-red-500/30 bg-red-500/10 text-red-200',
            }[mode] ?? 'border-zinc-700 bg-zinc-900/60 text-zinc-200';
        },

        setTableMode(name, mode) {
            if (mode === 'with_data') {
                delete this.tableModes[name];
            } else {
                this.tableModes[name] = mode;
            }
        },

        setAllStructureOnly() {
            const modes = {};

            this.availableTables.forEach((table) => {
                modes[table.name] = 'structure_only';
            });

            this.manualConfiguredTables().forEach((name) => {
                modes[name] = 'structure_only';
            });

            this.tableModes = modes;
        },

        setAllExclude() {
            const modes = {};

            this.availableTables.forEach((table) => {
                modes[table.name] = 'exclude';
            });

            this.manualConfiguredTables().forEach((name) => {
                modes[name] = 'exclude';
            });

            this.tableModes = modes;
        },

        resetTableModes() {
            this.tableModes = {};
        },

        loadedTableNames() {
            return new Set(this.availableTables.map((table) => table.name));
        },

        manualConfiguredTables() {
            const loaded = this.loadedTableNames();

            return Object.keys(this.tableModes).filter((name) => !loaded.has(name));
        },

        normalizeTableName(name) {
            return name.trim().replace(/^['"`]+|['"`]+$/g, '');
        },

        isValidTableName(name) {
            return /^[A-Za-z0-9_]+$/.test(name);
        },

        addManualTables() {
            this.manualTableError = null;
            const raw = this.manualTableInput.trim();

            if (!raw) {
                this.manualTableError = 'Masukkan nama tabel.';

                return;
            }

            const candidates = raw
                .split(/[\s,;]+/)
                .map((part) => this.normalizeTableName(part))
                .filter(Boolean);
            const invalid = candidates.filter((name) => !this.isValidTableName(name));

            if (invalid.length > 0) {
                this.manualTableError = 'Nama tabel tidak valid: ' + invalid.join(', ');

                return;
            }

            candidates.forEach((name) => {
                if (this.manualTableMode === 'with_data') {
                    delete this.tableModes[name];
                } else {
                    this.tableModes[name] = this.manualTableMode;
                }
            });

            this.manualTableInput = '';
        },

        removeConfiguredTable(name) {
            delete this.tableModes[name];
        },

        tableMeta(table) {
            return (table.engine ?? '-') + ' · ' + (table.rows ?? 0) + ' rows · ' + (table.size ?? '-');
        },

        async loadTables() {
            const connectionId = this.$refs.connectionSelect?.value;

            if (!connectionId) {
                this.tablesError = 'Pilih koneksi database terlebih dahulu.';

                return;
            }

            this.loadingTables = true;
            this.tablesError = null;

            try {
                const response = await fetch(this.tablesEndpoint + '/' + connectionId, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });
                const payload = await response.json();

                if (!response.ok) {
                    throw new Error(payload.message || 'Gagal memuat daftar tabel.');
                }

                this.availableTables = payload.tables ?? [];

                if (this.availableTables.length === 0) {
                    this.tablesError = 'Tidak ada tabel ditemukan di database ini.';
                }
            } catch (error) {
                this.availableTables = [];
                this.tablesError = error.message || 'Gagal memuat daftar tabel.';
            } finally {
                this.loadingTables = false;
            }
        },
    }));
}
