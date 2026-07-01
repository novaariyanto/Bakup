document.addEventListener('alpine:init', () => {
    Alpine.data('backupProgressPoll', (config) => ({
        progress: config.initialProgress,
        progressUrl: config.progressUrl,
        polling: null,
        pollCount: 0,
        refreshing: false,

        init() {
            if (! this.progress?.is_finished) {
                this.startPolling();
            }
        },

        get showQueueWarning() {
            return this.progress?.status === 'pending' && this.pollCount >= 3;
        },

        startPolling() {
            this.stopPolling();
            this.polling = setInterval(() => this.refresh(), 2000);
        },

        stopPolling() {
            if (this.polling) {
                clearInterval(this.polling);
                this.polling = null;
            }
        },

        async refresh() {
            if (this.refreshing) {
                return;
            }

            this.refreshing = true;
            this.pollCount++;

            try {
                const response = await fetch(this.progressUrl, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });

                if (! response.ok) {
                    return;
                }

                this.progress = await response.json();

                if (this.progress.is_finished) {
                    this.stopPolling();
                }
            } catch {
                // Abaikan error jaringan sementara.
            } finally {
                this.refreshing = false;
            }
        },

        destroy() {
            this.stopPolling();
        },
    }));
});
