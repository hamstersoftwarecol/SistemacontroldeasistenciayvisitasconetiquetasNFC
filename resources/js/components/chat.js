import { getJson, postJson } from '../http';

/**
 * Chat del equipo: canal general + mensajes directos, con sondeo periódico.
 */
export default ({ contactsUrl, messagesUrl, sendUrl, contacts, initial = null }) => ({
    contacts,
    active: null,
    messages: [],
    body: '',
    sending: false,
    loading: false,
    showList: true,
    search: '',
    error: '',
    lastId: 0,
    pollTimer: null,
    contactsTimer: null,

    init() {
        const start = this.contacts.find((c) => c.id === String(initial)) ?? (window.innerWidth >= 768 ? this.contacts[0] : null);
        if (start) {
            this.open(start);
        }

        this.pollTimer = setInterval(() => this.poll(), 4000);
        this.contactsTimer = setInterval(() => this.refreshContacts(), 20000);
    },

    destroy() {
        clearInterval(this.pollTimer);
        clearInterval(this.contactsTimer);
    },

    get current() {
        return this.contacts.find((c) => c.id === this.active) ?? null;
    },

    get filteredContacts() {
        const term = this.search.trim().toLowerCase();

        return term ? this.contacts.filter((c) => c.name.toLowerCase().includes(term)) : this.contacts;
    },

    async open(contact) {
        this.active = contact.id;
        this.messages = [];
        this.lastId = 0;
        this.showList = false;
        contact.unread = 0;
        this.loading = true;

        try {
            const data = await getJson(`${messagesUrl}?canal=${encodeURIComponent(contact.id)}`);
            this.append(data.messages);
        } catch (error) {
            this.error = error.message;
        } finally {
            this.loading = false;
        }
    },

    append(messages) {
        const known = new Set(this.messages.map((m) => m.id));
        const fresh = messages.filter((m) => !known.has(m.id));

        if (!fresh.length) {
            return;
        }

        this.messages.push(...fresh);
        this.lastId = Math.max(this.lastId, ...fresh.map((m) => m.id));
        this.$nextTick(() => {
            const box = this.$refs.messages;
            if (box) {
                box.scrollTop = box.scrollHeight;
            }
        });
    },

    async poll() {
        if (!this.active || document.hidden || this.loading) {
            return;
        }

        try {
            const data = await getJson(`${messagesUrl}?canal=${encodeURIComponent(this.active)}&after=${this.lastId}`);
            this.append(data.messages);
        } catch {
            // Se reintenta en el siguiente ciclo.
        }
    },

    async refreshContacts() {
        if (document.hidden) {
            return;
        }

        try {
            const data = await getJson(contactsUrl);
            this.contacts = data.contacts.map((c) => (c.id === this.active ? { ...c, unread: 0 } : c));
        } catch {
            // Ignorar.
        }
    },

    async send() {
        const body = this.body.trim();
        if (!body || !this.active || this.sending) {
            return;
        }

        this.sending = true;
        this.error = '';

        try {
            const data = await postJson(sendUrl, { canal: this.active, body });
            this.body = '';
            this.append([data.message]);
        } catch (error) {
            this.error = error.message;
        } finally {
            this.sending = false;
            this.$refs.input?.focus();
        }
    },

    showDate(index) {
        return index === 0 || this.messages[index - 1].date !== this.messages[index].date;
    },
});
