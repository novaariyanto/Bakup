export function registerMydumperExportForm(Alpine) {
    Alpine.data('mydumperExportForm', (config) => ({
        tablesEndpoint: config.tablesEndpoint,
        previewEndpoint: config.previewEndpoint,
        exportType: config.exportType,
        selectedTables: config.selectedTables ?? [],
        excludeTables: config.excludeTables ?? [],
        selectedConnectionId: config.selectedConnectionId != null && config.selectedConnectionId !== ''
            ? String(config.selectedConnectionId)
            : '',
        threads: config.threads ?? 4,
        compression: config.compression ?? false,
        scheduleType: config.scheduleType ?? 'manual',
        availableTables: [],
        loadingTables: false,
        commandPreview: '',

        async loadTables() {
            if (!this.selectedConnectionId) return;
            this.loadingTables = true;
            try {
                const response = await fetch(this.tablesEndpoint + '/' + this.selectedConnectionId, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                const payload = await response.json();
                if (response.ok) this.availableTables = payload.tables ?? [];
            } finally {
                this.loadingTables = false;
            }
        },

        async previewCommand() {
            const form = this.$root.closest('form');
            if (!form) return;
            const formData = new FormData(form);
            const body = Object.fromEntries(formData.entries());
            try {
                const response = await fetch(this.previewEndpoint, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(body),
                });
                const payload = await response.json();
                if (response.ok) this.commandPreview = payload.command ?? '';
            } catch {
                this.commandPreview = 'Gagal memuat preview command.';
            }
        },
    }));
}
