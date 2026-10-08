<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#4f46e5">
        <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
        <link rel="icon" href="{{ asset('icon.svg') }}" type="image/svg+xml">

        <title>{{ isset($title) ? $title.' · ' : '' }}{{ $companyName ?? config('app.name') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased text-gray-900">
        <div x-data="{ sidebar: false }" class="min-h-screen bg-gray-50">
            {{-- Barra lateral de escritorio --}}
            <aside class="fixed inset-y-0 left-0 z-30 hidden w-64 border-r border-gray-200 bg-white lg:block print:hidden">
                @include('layouts.sidebar')
            </aside>

            {{-- Cajón lateral en móvil --}}
            <div x-show="sidebar" x-cloak class="fixed inset-0 z-40 lg:hidden print:hidden">
                <div x-show="sidebar" x-transition.opacity class="absolute inset-0 bg-gray-900/50" @click="sidebar = false"></div>
                <aside x-show="sidebar"
                       x-transition:enter="transition ease-out duration-200" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
                       x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full"
                       class="absolute inset-y-0 left-0 w-72 max-w-[85%] bg-white shadow-xl">
                    @include('layouts.sidebar')
                </aside>
            </div>

            <div class="lg:pl-64">
                {{-- Barra superior --}}
                <header class="sticky top-0 z-20 border-b border-gray-200 bg-white/90 backdrop-blur print:hidden">
                    <div class="flex h-14 items-center gap-3 px-4 sm:px-6">
                        <button type="button" class="-ml-1 rounded-md p-2 text-gray-500 hover:bg-gray-100 lg:hidden" @click="sidebar = true" aria-label="Abrir menú">
                            <x-heroicon-o-bars-3 class="h-6 w-6" />
                        </button>
                        <a href="{{ route('dashboard') }}" class="flex items-center gap-2 lg:hidden">
                            <x-application-logo class="h-7 w-7 text-indigo-600" />
                        </a>
                        <div class="min-w-0 flex-1">
                            @isset($header)
                                <div class="truncate text-base font-semibold text-gray-900 sm:text-lg">{{ $header }}</div>
                            @endisset
                        </div>
                        @isset($actions)
                            <div class="hidden items-center gap-2 sm:flex">{{ $actions }}</div>
                        @endisset
                        <a href="{{ route('broadcasts.index') }}" class="relative rounded-full p-2 text-gray-500 hover:bg-gray-100" title="Difusiones">
                            <x-heroicon-o-bell class="h-6 w-6" />
                            @if (isset($unreadBroadcasts) && $unreadBroadcasts->count())
                                <span class="absolute right-1 top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-bold text-white">{{ $unreadBroadcasts->count() }}</span>
                            @endif
                        </a>
                    </div>
                </header>

                <main class="mx-auto w-full max-w-7xl px-4 pb-28 pt-4 sm:px-6 lg:px-8 lg:pb-10 print:max-w-none print:p-0">
                    @isset($actions)
                        <div class="mb-4 flex flex-wrap items-center gap-2 sm:hidden print:hidden">{{ $actions }}</div>
                    @endisset

                    {{-- Difusiones importantes sin leer --}}
                    @if (isset($unreadBroadcasts))
                        @foreach ($unreadBroadcasts->whereIn('priority', ['important', 'urgent'])->take(2) as $broadcast)
                            <div @class([
                                'mb-4 flex items-start gap-3 rounded-xl border p-4 print:hidden',
                                'border-rose-200 bg-rose-50 text-rose-900' => $broadcast->priority === 'urgent',
                                'border-amber-200 bg-amber-50 text-amber-900' => $broadcast->priority === 'important',
                            ])>
                                <x-heroicon-o-megaphone class="mt-0.5 h-5 w-5 shrink-0" />
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold">{{ $broadcast->title }}</p>
                                    <p class="mt-1 line-clamp-2 text-sm">{{ $broadcast->body }}</p>
                                </div>
                                <a href="{{ route('broadcasts.show', $broadcast) }}" class="shrink-0 text-sm font-semibold underline">Leer</a>
                            </div>
                        @endforeach
                    @endif

                    <x-flash />

                    {{ $slot }}
                </main>
            </div>

            {{-- Navegación inferior en móvil --}}
            <nav class="fixed inset-x-0 bottom-0 z-20 border-t border-gray-200 bg-white pb-[env(safe-area-inset-bottom)] lg:hidden print:hidden">
                <div class="grid grid-cols-5 text-[11px] font-medium text-gray-500">
                    <a href="{{ route('dashboard') }}" @class(['flex flex-col items-center gap-1 py-2', 'text-indigo-600' => request()->routeIs('dashboard')])>
                        <x-heroicon-o-home class="h-6 w-6" /> Inicio
                    </a>
                    <a href="{{ route('my.history') }}" @class(['flex flex-col items-center gap-1 py-2', 'text-indigo-600' => request()->routeIs('my.history')])>
                        <x-heroicon-o-calendar-days class="h-6 w-6" /> Historial
                    </a>
                    <a href="{{ route('my.tracker') }}" class="-mt-5 flex flex-col items-center gap-1">
                        <span class="flex h-14 w-14 items-center justify-center rounded-full bg-indigo-600 text-white shadow-lg ring-4 ring-white">
                            <x-heroicon-o-signal class="h-7 w-7" />
                        </span>
                        <span @class(['text-indigo-600' => request()->routeIs('my.tracker')])>Marcar</span>
                    </a>
                    @can('chat.use')
                        <a href="{{ route('chat.index') }}" @class(['relative flex flex-col items-center gap-1 py-2', 'text-indigo-600' => request()->routeIs('chat.*')])>
                            <x-heroicon-o-chat-bubble-left-right class="h-6 w-6" /> Chat
                            @if (($unreadChat ?? 0) > 0)
                                <span class="absolute right-4 top-1 h-2.5 w-2.5 rounded-full bg-rose-500"></span>
                            @endif
                        </a>
                    @else
                        <a href="{{ route('complaints.index') }}" @class(['flex flex-col items-center gap-1 py-2', 'text-indigo-600' => request()->routeIs('complaints.*')])>
                            <x-heroicon-o-flag class="h-6 w-6" /> Quejas
                        </a>
                    @endcan
                    <button type="button" @click="sidebar = true" class="flex flex-col items-center gap-1 py-2">
                        <x-heroicon-o-squares-2x2 class="h-6 w-6" /> Más
                    </button>
                </div>
            </nav>
        </div>

        @stack('scripts')
    </body>
</html>
