import { postJson } from '../http';

const TOOL_LABELS = {
    team_status_now: 'estado del equipo',
    attendance_records: 'registros de asistencia',
    attendance_summary: 'resumen de asistencia',
    visits: 'visitas',
    location_stats: 'ubicaciones',
    complaints: 'quejas',
    list_employees: 'personal',
    list_locations: 'ubicaciones',
};

/**
 * Chat con el asistente de IA conectado a la base de datos.
 */
export default ({ askUrl, conversationId = null, messages = [] }) => ({
    conversationId,
    messages,
    question: '',
    loading: false,
    error: '',

    init() {
        this.scroll();
    },

    toolLabel(name) {
        return TOOL_LABELS[name] ?? name;
    },

    use(suggestion) {
        this.question = suggestion;
        this.ask();
    },

    async ask() {
        const question = this.question.trim();
        if (!question || this.loading) {
            return;
        }

        this.error = '';
        this.loading = true;
        this.question = '';
        this.messages.push({ id: `tmp-${Date.now()}`, role: 'user', text: question, tools: [] });
        this.scroll();

        try {
            const data = await postJson(askUrl, { question, conversation_id: this.conversationId });
            const isNew = !this.conversationId;
            this.conversationId = data.conversation.id;
            this.messages.push(data.message);

            if (isNew) {
                window.history.replaceState({}, '', data.conversation.url);
                this.$dispatch('conversation-created', data.conversation);
            }
        } catch (error) {
            this.error = error.message;
            this.question = question;
        } finally {
            this.loading = false;
            this.scroll();
            this.$nextTick(() => this.$refs.input?.focus());
        }
    },

    scroll() {
        this.$nextTick(() => {
            const box = this.$refs.messages;
            if (box) {
                box.scrollTop = box.scrollHeight;
            }
        });
    },
});
