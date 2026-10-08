import { postJson } from '../http';
import { nfcErrorMessage, nfcSupported, readTextFromMessage, vibrate } from '../nfc';

/**
 * Escritor de etiquetas NFC: graba la URL de la ubicación y opcionalmente la bloquea (solo lectura).
 */
export default ({ locations, identifyUrl }) => ({
    supported: nfcSupported(),
    locations,
    search: '',
    selected: null,
    lockAfterWrite: false,
    working: false,
    status: '',
    error: false,
    readResult: null,
    controller: null,

    get filtered() {
        const term = this.search.trim().toLowerCase();
        if (!term) {
            return this.locations;
        }

        return this.locations.filter((l) => `${l.name} ${l.code} ${l.area ?? ''}`.toLowerCase().includes(term));
    },

    select(location) {
        this.cancel();
        this.selected = location;
        this.readResult = null;
        this.setStatus('');
    },

    setStatus(message, error = false) {
        this.status = message;
        this.error = error;
    },

    cancel() {
        this.controller?.abort();
        this.controller = null;
        this.working = false;
    },

    async copyUrl(location) {
        try {
            await navigator.clipboard.writeText(location.url);
            this.setStatus('URL copiada. Puedes grabarla con cualquier app de NFC (por ejemplo, NFC Tools en iPhone).');
        } catch {
            this.setStatus(location.url);
        }
    },

    async write() {
        if (!this.selected) {
            return;
        }

        if (this.lockAfterWrite && !confirm('La etiqueta quedará BLOQUEADA de forma permanente y no podrá volver a escribirse. ¿Continuar?')) {
            return;
        }

        const location = this.selected;
        this.working = true;
        this.setStatus('Acerca la etiqueta NFC a la parte trasera del teléfono…');

        try {
            const reader = new NDEFReader();
            this.controller = new AbortController();
            let serial = null;

            // Escanear en paralelo para conocer el número de serie (UID) de la etiqueta.
            reader.onreading = (event) => {
                serial = event.serialNumber || serial;
            };
            try {
                await reader.scan({ signal: this.controller.signal });
            } catch {
                // Si no se puede escanear, se escribe igualmente sin UID.
            }

            await reader.write(
                { records: [{ recordType: 'url', data: location.url }] },
                { overwrite: true, signal: this.controller.signal },
            );

            const written = await postJson(location.written_url, { tag_uid: serial });
            Object.assign(location, written.location);
            vibrate([100, 50, 100]);
            this.setStatus(`Etiqueta grabada para «${location.name}».${serial ? ` UID: ${serial}` : ''}`);

            if (this.lockAfterWrite) {
                await this.lockWith(reader, location);
            }
        } catch (error) {
            this.setStatus(nfcErrorMessage(error), true);
            vibrate(400);
        } finally {
            this.cancel();
        }
    },

    async lock() {
        if (!this.selected) {
            return;
        }

        if (!confirm('Bloquear la etiqueta es PERMANENTE: nadie podrá reescribirla. Verifica antes que esté grabada correctamente. ¿Continuar?')) {
            return;
        }

        this.working = true;
        this.setStatus('Acerca la etiqueta que quieres bloquear…');

        try {
            const reader = new NDEFReader();
            this.controller = new AbortController();
            await this.lockWith(reader, this.selected);
        } catch (error) {
            this.setStatus(nfcErrorMessage(error), true);
            vibrate(400);
        } finally {
            this.cancel();
        }
    },

    async lockWith(reader, location) {
        if (typeof reader.makeReadOnly !== 'function') {
            throw new Error('Tu navegador no permite bloquear etiquetas. Actualiza Chrome para Android.');
        }

        this.setStatus('Bloqueando etiqueta… mantén el teléfono sobre ella.');
        await reader.makeReadOnly({ signal: this.controller?.signal });
        const locked = await postJson(location.locked_url);
        Object.assign(location, locked.location);
        vibrate([100, 50, 100, 50, 100]);
        this.setStatus(`Etiqueta de «${location.name}» bloqueada (solo lectura).`);
    },

    async read() {
        this.working = true;
        this.readResult = null;
        this.setStatus('Acerca la etiqueta para leerla…');

        try {
            const reader = new NDEFReader();
            this.controller = new AbortController();
            await reader.scan({ signal: this.controller.signal });

            await new Promise((resolve, reject) => {
                reader.onreadingerror = () => reject(new Error('No se pudo leer la etiqueta.'));
                reader.onreading = async (event) => {
                    try {
                        const text = readTextFromMessage(event.message);
                        const result = await postJson(identifyUrl, { text, uid: event.serialNumber || null });
                        this.readResult = { ...result, text, serial: event.serialNumber };
                        this.setStatus(result.found ? `Etiqueta de «${result.location.name}».` : 'La etiqueta no corresponde a ninguna ubicación.', !result.found);
                        vibrate(120);
                        resolve();
                    } catch (error) {
                        reject(error);
                    }
                };
            });
        } catch (error) {
            this.setStatus(nfcErrorMessage(error), true);
        } finally {
            this.cancel();
        }
    },
});
