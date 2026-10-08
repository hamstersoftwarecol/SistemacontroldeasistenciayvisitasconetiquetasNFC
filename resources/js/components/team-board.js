import { getJson } from '../http';

/**
 * Panel del equipo en tiempo real (consulta periódica al servidor).
 */
export default ({ url, interval = 15000, initial = null }) => ({
    rows: initial?.rows ?? [],
    summary: initial?.summary ?? {},
    search: '',
    status: 'all',
    loading: false,
    failed: false,
    timer: null,

    init() {
        if (!initial) {
            this.refresh();
        }
        this.timer = setInterval(() => this.refresh(), interval);
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                this.refresh();
            }
        });
    },

    destroy() {
        clearInterval(this.timer);
    },

    async refresh() {
        if (document.hidden || this.loading) {
            return;
        }

        this.loading = true;
        try {
            const data = await getJson(url);
            this.rows = data.rows;
            this.summary = data.summary;
            this.failed = false;
        } catch {
            this.failed = true;
        } finally {
            this.loading = false;
        }
    },

    get filtered() {
        const term = this.search.trim().toLowerCase();

        return this.rows.filter((row) => {
            if (this.status === 'late' && !row.is_late) {
                return false;
            }
            if (!['all', 'late'].includes(this.status) && row.status !== this.status) {
                return false;
            }
            if (!term) {
                return true;
            }

            return `${row.name} ${row.department ?? ''} ${row.position ?? ''} ${row.last_location ?? ''}`.toLowerCase().includes(term);
        });
    },
});
