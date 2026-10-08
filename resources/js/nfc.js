export const nfcSupported = () => typeof window !== 'undefined' && 'NDEFReader' in window;

/**
 * Devuelve el primer texto/URL contenido en un mensaje NDEF.
 */
export function readTextFromMessage(message) {
    for (const record of message?.records ?? []) {
        try {
            if (record.recordType === 'url' || record.recordType === 'absolute-url') {
                return new TextDecoder().decode(record.data);
            }
            if (record.recordType === 'text') {
                return new TextDecoder(record.encoding || 'utf-8').decode(record.data);
            }
        } catch {
            // Registro ilegible: se ignora.
        }
    }

    return null;
}

/**
 * Extrae el token de ubicación de una URL del tipo https://dominio/t/{token}.
 */
export function extractToken(text) {
    if (!text) {
        return null;
    }

    const match = String(text).match(/\/t\/([A-Za-z0-9]{16,64})/);
    if (match) {
        return match[1];
    }

    return /^[A-Za-z0-9]{16,64}$/.test(text.trim()) ? text.trim() : null;
}

export function nfcErrorMessage(error) {
    switch (error?.name) {
        case 'NotAllowedError':
            return 'Permiso de NFC denegado. Activa el permiso NFC para este sitio en Chrome.';
        case 'NotSupportedError':
            return 'Este dispositivo no tiene NFC o está desactivado. Actívalo en los ajustes del teléfono.';
        case 'NotReadableError':
            return 'No se pudo usar el lector NFC. Cierra otras apps que lo usen e intenta de nuevo.';
        case 'NetworkError':
            return 'Se perdió la conexión con la etiqueta. Mantén el teléfono quieto sobre ella.';
        case 'AbortError':
            return 'Operación cancelada.';
        case 'InvalidStateError':
            return 'La etiqueta está bloqueada (solo lectura) y no se puede modificar.';
        default:
            return error?.message || 'Error de NFC desconocido.';
    }
}

export const vibrate = (pattern = 150) => {
    if (navigator.vibrate) {
        navigator.vibrate(pattern);
    }
};
