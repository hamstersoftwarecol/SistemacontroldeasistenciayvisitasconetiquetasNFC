<x-app-layout>
    <x-slot name="title">Asistente IA</x-slot>
    <x-slot name="header">Asistente de IA</x-slot>

    <div class="flex h-[calc(100vh-11rem)] min-h-[30rem] gap-4 lg:h-[calc(100vh-7rem)]"
         x-data="{ conversations: @js($conversations->map(fn ($c) => ['id' => $c->id, 'title' => $c->title, 'url' => route('ai.index', $c)])) }"
         @conversation-created.window="conversations.unshift($event.detail)">

        {{-- Historial de conversaciones --}}
        <aside class="hidden w-64 shrink-0 flex-col rounded-2xl border border-gray-200 bg-white shadow-sm md:flex">
            <div class="border-b border-gray-100 p-3">
                <x-button-link :href="route('ai.index')" icon="plus" class="w-full">Nueva conversación</x-button-link>
            </div>
            <ul class="flex-1 space-y-0.5 overflow-y-auto p-2">
                <template x-for="item in conversations" :key="item.id">
                    <li>
                        <a :href="item.url" class="block truncate rounded-lg px-3 py-2 text-sm hover:bg-gray-100"
                           :class="item.id === {{ $conversation?->id ?? 'null' }} ? 'bg-indigo-50 font-medium text-indigo-700' : 'text-gray-700'" x-text="item.title"></a>
                    </li>
                </template>
                <li x-show="!conversations.length" class="px-3 py-2 text-xs text-gray-500">Sin conversaciones.</li>
            </ul>
        </aside>

        {{-- Conversación --}}
        <section class="flex min-w-0 flex-1 flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm"
                 x-data="aiChat({
                    askUrl: @js(route('ai.ask')),
                    conversationId: @js($conversation?->id),
                    messages: @js($conversation ? $conversation->messages->map(fn ($m) => \App\Http\Controllers\AiAssistantController::present($m))->values() : []),
                 })">
            <div class="flex items-center justify-between gap-2 border-b border-gray-100 px-4 py-3">
                <div class="flex min-w-0 items-center gap-2">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-indigo-500 to-violet-500 text-white"><x-heroicon-o-sparkles class="h-5 w-5" /></span>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold">{{ $conversation?->title ?? 'Pregunta sobre asistencia, visitas y quejas' }}</p>
                        <p class="text-xs text-gray-500">Conectado a la base de datos · solo lectura</p>
                    </div>
                </div>
                <div class="flex items-center gap-1">
                    <a href="{{ route('ai.index') }}" class="rounded-md p-1.5 text-gray-500 hover:bg-gray-100 md:hidden" title="Nueva conversación"><x-heroicon-o-plus class="h-5 w-5" /></a>
                    @if ($conversation)
                        <form method="POST" action="{{ route('ai.destroy', $conversation) }}" onsubmit="return confirm('¿Eliminar esta conversación?')">
                            @csrf
                            @method('DELETE')
                            <button class="rounded-md p-1.5 text-gray-500 hover:bg-rose-50 hover:text-rose-600" title="Eliminar conversación"><x-heroicon-o-trash class="h-5 w-5" /></button>
                        </form>
                    @endif
                </div>
            </div>

            <div x-ref="messages" class="flex-1 space-y-4 overflow-y-auto bg-gray-50 px-4 py-5">
                @unless ($configured)
                    <div class="mx-auto max-w-lg rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                        <p class="font-semibold">El asistente aún no está configurado.</p>
                        <p class="mt-1">Un administrador debe agregar la clave de API de Claude (Anthropic) en
                            @can('settings.manage')<a href="{{ route('settings.edit', ['seccion' => 'ia']) }}" class="underline">Ajustes → Asistente IA</a>@else<span>Ajustes → Asistente IA</span>@endcan</p>
                    </div>
                @endunless

                <template x-if="!messages.length">
                    <div class="mx-auto max-w-xl pt-6 text-center">
                        <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-500 text-white shadow-lg"><x-heroicon-o-sparkles class="h-7 w-7" /></span>
                        <p class="mt-4 text-lg font-semibold">¿En qué te ayudo?</p>
                        <p class="mt-1 text-sm text-gray-500">Pregunta en lenguaje natural. El asistente consulta los datos reales de asistencia, visitas y quejas.</p>
                        <div class="mt-5 flex flex-wrap justify-center gap-2">
                            @foreach ($suggestions as $suggestion)
                                <button type="button" @click="use(@js($suggestion))" class="rounded-full border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-700 shadow-sm hover:border-indigo-300 hover:text-indigo-700">{{ $suggestion }}</button>
                            @endforeach
                        </div>
                    </div>
                </template>

                <template x-for="message in messages" :key="message.id">
                    <div class="flex" :class="message.role === 'user' ? 'justify-end' : 'justify-start'">
                        <div class="max-w-[90%] rounded-2xl px-4 py-2.5 text-sm shadow-sm sm:max-w-[80%]"
                             :class="message.role === 'user' ? 'rounded-br-md bg-indigo-600 text-white' : 'rounded-bl-md bg-white text-gray-800'">
                            <template x-if="message.role === 'user'"><p class="whitespace-pre-line" x-text="message.text"></p></template>
                            <template x-if="message.role !== 'user'">
                                <div>
                                    <div class="prose-chat overflow-x-auto" x-html="message.html"></div>
                                    <p x-show="message.tools && message.tools.length" class="mt-2 border-t border-gray-100 pt-1.5 text-[11px] text-gray-400">
                                        Consultó: <span x-text="[...new Set(message.tools.map(t => toolLabel(t)))].join(', ')"></span>
                                    </p>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>

                <div x-show="loading" x-cloak class="flex justify-start">
                    <div class="flex items-center gap-2 rounded-2xl rounded-bl-md bg-white px-4 py-3 text-sm text-gray-500 shadow-sm">
                        <span class="flex gap-1">
                            <span class="h-2 w-2 animate-bounce rounded-full bg-indigo-400"></span>
                            <span class="h-2 w-2 animate-bounce rounded-full bg-indigo-400 [animation-delay:150ms]"></span>
                            <span class="h-2 w-2 animate-bounce rounded-full bg-indigo-400 [animation-delay:300ms]"></span>
                        </span>
                        Consultando los datos…
                    </div>
                </div>
            </div>

            <form @submit.prevent="ask()" class="border-t border-gray-100 p-3">
                <p x-show="error" x-text="error" x-cloak class="mb-2 text-xs text-rose-600"></p>
                <div class="flex items-end gap-2">
                    <textarea x-ref="input" x-model="question" rows="1" maxlength="2000" @keydown.enter.prevent="if (!$event.shiftKey) ask(); else question += '\n'"
                              placeholder="Ej.: ¿Quién visitó la Habitación 101 ayer?" class="max-h-32 min-h-[44px] flex-1 resize-none rounded-xl border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" @disabled(! $configured)></textarea>
                    <button type="submit" :disabled="loading || !question.trim()" class="flex h-[44px] w-[44px] items-center justify-center rounded-xl bg-indigo-600 text-white hover:bg-indigo-500 disabled:opacity-50" aria-label="Enviar">
                        <x-heroicon-o-paper-airplane class="h-5 w-5" />
                    </button>
                </div>
                <p class="mt-1.5 text-center text-[11px] text-gray-400">La IA puede equivocarse. Verifica los datos importantes en los informes.</p>
            </form>
        </section>
    </div>
</x-app-layout>
