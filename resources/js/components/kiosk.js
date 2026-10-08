import { postJson } from '../http';
import { nfcErrorMessage, nfcSupported, vibrate } from '../nfc';

/**
 * Modo kiosco: un dispositivo fijo lee las tarjetas NFC de los empleados.
 * Soporta Web NFC (Chrome Android) y lectores USB que escriben el UID como teclado.
 */
export default ({ storeUrl, locationId, timeZone = undefined }) => ({
    supported: nfcSupported(),
    locationId,
    scanning: false,
    busy: false,
    clock: '',
    date: '',
    result: null,
    error: '',
    history: [],
    manual: '',
    wedge: '',
    lastUid: null,
    lastAt: 0,
    controller: null,
    resetTimer: null,

    init() {
        const tick = () => {
            const now = new Date();
            this.clock = now.toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit', second: '2-digit', timeZone });
            this.date = now.toLocaleDateString('es', { weekday: 'long', day: 'numeric', month: 'long', timeZone });
        };
        tick();
        setInterval(tick, 1000);
        this.focusWedge();
    },

    focusWedge() {
        this.$nextTick(() => this.$refs.wedge?.focus({ preventScroll: true }));
    },

    async startNfc() {
        try {
            const reader = new NDEFReader();
            this.controller = new AbortController();
            await reader.scan({ signal: this.controller.signal });
            this.scanning = true;
            reader.onreading = (event) => this.register({ uid: event.serialNumber });
            reader.onreadingerror = () => this.showError('No se pudo leer la tarjeta. Intenta de nuevo.');
        } catch (error) {
            this.showError(nfcErrorMessage(error));
        }
    },

    stopNfc() {
        this.controller?.abort();
        this.scanning = false;
    },

    submitWedge() {
        const value = this.wedge.trim();
        this.wedge = '';
        if (value) {
            this.register({ uid: value });
        }
    },

    submitManual() {
        const value = this.manual.trim();
        this.manual = '';
        if (value) {
            this.register({ employee_code: value });
        }
        this.focusWedge();
    },

    async register(payload) {
        const key = payload.uid || payload.employee_code;
        const now = Date.now();

        // Evita lecturas dobles de la misma tarjeta en pocos segundos.
        if (this.busy || (key === this.lastUid && now - this.lastAt < 4000)) {
            return;
        }

        this.busy = true;
        this.lastUid = key;
        this.lastAt = now;
        this.error = '';

        try {
            const data = await postJson(storeUrl, { location_id: this.locationId, ...payload });
            this.result = data;
            this.history.unshift({ ...data, key: `${now}` });
            this.history = this.history.slice(0, 8);
            vibrate([100, 50, 100]);
        } catch (error) {
            this.showError(error.message);
        } finally {
            this.busy = false;
            clearTimeout(this.resetTimer);
            this.resetTimer = setTimeout(() => {
                this.result = null;
                this.error = '';
            }, 5000);
            this.focusWedge();
        }
    },

    showError(message) {
        this.result = null;
        this.error = message;
        vibrate(400);
    },
});
