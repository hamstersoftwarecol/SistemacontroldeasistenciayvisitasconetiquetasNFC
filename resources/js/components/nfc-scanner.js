import { postJson } from '../http';
import { extractToken, nfcErrorMessage, nfcSupported, readTextFromMessage, vibrate } from '../nfc';

/**
 * Escáner Web NFC del rastreador personal: registra el toque sin salir de la app.
 */
export default ({ endpoint }) => ({
    supported: nfcSupported(),
    scanning: false,
    busy: false,
    message: '',
    error: false,
    controller: null,

    async toggle() {
        if (this.scanning) {
            this.stop();
            return;
        }

        await this.start();
    },

    async start() {
        this.message = '';
        this.error = false;

        try {
            const reader = new NDEFReader();
            this.controller = new AbortController();
            await reader.scan({ signal: this.controller.signal });
            this.scanning = true;

            reader.onreadingerror = () => this.fail('No se pudo leer la etiqueta. Intenta de nuevo.');
            reader.onreading = (event) => this.handle(event);
        } catch (error) {
            this.fail(nfcErrorMessage(error));
        }
    },

    stop() {
        this.controller?.abort();
        this.controller = null;
        this.scanning = false;
    },

    async handle(event) {
        if (this.busy) {
            return;
        }

        const token = extractToken(readTextFromMessage(event.message));

        if (!token) {
            this.fail('Esta etiqueta no pertenece al sistema de asistencia.');
            return;
        }

        this.busy = true;
        this.message = 'Registrando…';
        this.error = false;

        try {
            const data = await postJson(`${endpoint}/${token}`, { source: 'web_nfc', tag_uid: event.serialNumber || null });
            this.stop();
            vibrate([100, 50, 100]);
            this.message = `${data.headline} · ${data.location} · ${data.time}`;
            window.location.href = data.receipt_url;
        } catch (error) {
            this.fail(error.message);
        } finally {
            this.busy = false;
        }
    },

    fail(message) {
        this.error = true;
        this.message = message;
        vibrate(400);
    },
});
