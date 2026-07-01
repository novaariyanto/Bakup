document.addEventListener('alpine:init', () => {
    Alpine.data('backupProfileForm', (config) => ({
        scheduleType: config.scheduleType,
        backupDatabase: config.backupDatabase,
        backupFolders: config.backupFolders,
        includeFolders: config.includeFolders ?? [],
        excludeFolders: config.excludeFolders ?? [],
        tableSearch: '',
        availableTables: config.availableTables ?? [],
        excludedTableNames: config.excludedTableNames ?? [],
        manualTableInput: '',
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

        isExcluded(name) {
            return this.excludedTableNames.includes(name);
        },

        toggleExcluded(name) {
            const index = this.excludedTableNames.indexOf(name);

            if (index >= 0) {
                this.excludedTableNames.splice(index, 1);
            } else {
                this.excludedTableNames.push(name);
            }
        },

        selectAllTables() {
            const loaded = this.availableTables.map((table) => table.name);
            const manual = this.manualExcludedTables();
            this.excludedTableNames = [...new Set([...manual, ...loaded])];
        },

        clearExcludedTables() {
            this.excludedTableNames = [];
        },

        loadedTableNames() {
            return new Set(this.availableTables.map((table) => table.name));
        },

        manualExcludedTables() {
            const loaded = this.loadedTableNames();

            return this.excludedTableNames.filter((name) => !loaded.has(name));
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
                if (!this.excludedTableNames.includes(name)) {
                    this.excludedTableNames.push(name);
                }
            });

            this.manualTableInput = '';
        },

        removeExcluded(name) {
            const index = this.excludedTableNames.indexOf(name);

            if (index >= 0) {
                this.excludedTableNames.splice(index, 1);
            }
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
});
