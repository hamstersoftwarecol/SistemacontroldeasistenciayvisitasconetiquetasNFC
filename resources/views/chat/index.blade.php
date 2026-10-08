<x-app-layout>
    <x-slot name="title">Chat del equipo</x-slot>
    <x-slot name="header">Chat del equipo</x-slot>

    <div x-data="chat({
            contactsUrl: @js(route('chat.contacts')),
            messagesUrl: @js(route('chat.messages')),
            sendUrl: @js(route('chat.send')),
            contacts: @js($contacts),
            initial: @js($initial),
         })"
         class="flex h-[calc(100vh-11rem)] min-h-[28rem] overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm lg:h-[calc(100vh-7rem)]">

        {{-- Lista de contactos --}}
        <aside class="w-full shrink-0 flex-col border-r border-gray-200 md:flex md:w-72" :class="showList ? 'flex' : 'hidden'">
            <div class="border-b border-gray-100 p-3">
                <input type="search" x-model="search" placeholder="Buscar persona…" class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <ul class="flex-1 overflow-y-auto">
                <template x-for="contact in filteredContacts" :key="contact.id">
                    <li>
                        <button type="button" @click="open(contact)" class="flex w-full items-center gap-3 px-3 py-2.5 text-left hover:bg-gray-50" :class="active === contact.id ? 'bg-indigo-50' : ''">
                            <span class="relative">
                                <span class="flex h-10 w-10 items-center justify-center rounded-full text-sm font-semibold"
                                      :class="contact.id === 'general' ? 'bg-violet-100 text-violet-700' : 'bg-indigo-100 text-indigo-700'" x-text="contact.initials"></span>
                                <span x-show="contact.online" class="absolute -bottom-0.5 -right-0.5 h-3 w-3 rounded-full border-2 border-white bg-emerald-500"></span>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium text-gray-900" x-text="contact.name"></span>
                                <span class="block truncate text-xs text-gray-500" x-text="contact.last ?? contact.subtitle"></span>
                            </span>
                            <span x-show="contact.unread > 0" class="rounded-full bg-indigo-600 px-2 py-0.5 text-xs font-semibold text-white" x-text="contact.unread"></span>
                        </button>
                    </li>
                </template>
            </ul>
        </aside>

        {{-- Conversación --}}
        <section class="min-w-0 flex-1 flex-col md:flex" :class="showList ? 'hidden' : 'flex'">
            <template x-if="current">
                <div class="flex items-center gap-3 border-b border-gray-100 px-4 py-3">
                    <button type="button" class="-ml-2 rounded-md p-1.5 text-gray-500 hover:bg-gray-100 md:hidden" @click="showList = true" aria-label="Volver">
                        <x-heroicon-o-chevron-left class="h-5 w-5" />
                    </button>
                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-indigo-100 text-sm font-semibold text-indigo-700" x-text="current.initials"></span>
                    <div class="min-w-0">
                        <p class="truncate font-semibold" x-text="current.name"></p>
                        <p class="truncate text-xs text-gray-500" x-text="current.id === 'general' ? 'Mensajes visibles para todo el equipo' : (current.online ? 'En línea' : current.subtitle)"></p>
                    </div>
                </div>
            </template>

            <div x-ref="messages" class="flex-1 space-y-2 overflow-y-auto bg-gray-50 px-4 py-4">
                <template x-if="!active">
                    <div class="flex h-full items-center justify-center text-sm text-gray-500">Elige una conversación.</div>
                </template>
                <template x-if="active && !loading && !messages.length">
                    <div class="flex h-full items-center justify-center text-sm text-gray-500">Aún no hay mensajes. ¡Escribe el primero!</div>
                </template>
                <template x-for="(message, index) in messages" :key="message.id">
                    <div>
                        <p x-show="showDate(index)" class="my-3 text-center text-xs font-medium text-gray-400" x-text="message.date"></p>
                        <div class="flex" :class="message.mine ? 'justify-end' : 'justify-start'">
                            <div class="max-w-[80%] rounded-2xl px-3.5 py-2 text-sm shadow-sm"
                                 :class="message.mine ? 'rounded-br-md bg-indigo-600 text-white' : 'rounded-bl-md bg-white text-gray-800'">
                                <p x-show="!message.mine && active === 'general'" class="mb-0.5 text-xs font-semibold text-indigo-600" x-text="message.sender"></p>
                                <p class="whitespace-pre-line break-words" x-text="message.body"></p>
                                <p class="mt-0.5 text-right text-[10px]" :class="message.mine ? 'text-indigo-200' : 'text-gray-400'">
                                    <span x-text="message.time"></span>
                                    <span x-show="message.mine && active !== 'general'" x-text="message.read ? ' · Leído' : ''"></span>
                                </p>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <form x-show="active" @submit.prevent="send()" class="flex items-end gap-2 border-t border-gray-100 p-3">
                <textarea x-ref="input" x-model="body" rows="1" @keydown.enter.prevent="if (!$event.shiftKey) send(); else body += '\n'" placeholder="Escribe un mensaje…" class="max-h-32 min-h-[42px] flex-1 resize-none rounded-xl border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                <button type="submit" :disabled="sending || !body.trim()" class="flex h-[42px] w-[42px] items-center justify-center rounded-xl bg-indigo-600 text-white hover:bg-indigo-500 disabled:opacity-50" aria-label="Enviar">
                    <x-heroicon-o-paper-airplane class="h-5 w-5" />
                </button>
            </form>
            <p x-show="error" x-text="error" class="px-3 pb-2 text-xs text-rose-600" x-cloak></p>
        </section>
    </div>
</x-app-layout>
