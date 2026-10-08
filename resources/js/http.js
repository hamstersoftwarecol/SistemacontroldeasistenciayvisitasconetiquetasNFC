const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

async function request(method, url, body) {
    const options = {
        method,
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrf(),
        },
        credentials: 'same-origin',
    };

    if (body !== undefined) {
        options.headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(body);
    }

    const response = await fetch(url, options);

    if (response.status === 419 || response.status === 401) {
        window.location.reload();
        throw new Error('Tu sesión expiró. Recargando…');
    }

    let data = {};
    try {
        data = await response.json();
    } catch {
        data = {};
    }

    if (!response.ok) {
        const firstError = data.errors ? Object.values(data.errors)[0]?.[0] : null;
        const error = new Error(firstError || data.message || 'Ocurrió un error. Intenta de nuevo.');
        error.status = response.status;
        error.data = data;
        throw error;
    }

    return data;
}

export const getJson = (url) => request('GET', url);
export const postJson = (url, body = {}) => request('POST', url, body);
export const deleteJson = (url) => request('DELETE', url);
