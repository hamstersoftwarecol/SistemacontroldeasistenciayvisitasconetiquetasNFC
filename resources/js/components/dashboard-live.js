import { getJson } from '../http';

/**
 * Indicadores y actividad del panel principal con refresco automático.
 */
export default ({ url, interval = 20000, initial = {} }) => ({
    summary: initial.summary ?? {},
    feed: initial.feed ?? [],
    timer: null,

    init() {
        this.timer = setInterval(() => this.refresh(), interval);
    },

    destroy() {
        clearInterval(this.timer);
    },

    async refresh() {
        if (document.hidden) {
            return;
        }

        try {
            const data = await getJson(url);
            this.summary = data.summary;
            this.feed = data.feed;
        } catch {
            // Se reintenta en el siguiente ciclo.
        }
    },
});
