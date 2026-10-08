<x-app-layout>
    <x-slot name="title">Panel</x-slot>
    <x-slot name="header">Hola, {{ \Illuminate\Support\Str::of($user->name)->before(' ') }}</x-slot>

    {{-- Tarjeta personal (todos los roles) --}}
    <section class="mb-6 flex flex-col gap-4 rounded-2xl bg-gradient-to-br from-indigo-600 to-violet-600 p-5 text-white shadow-lg sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-sm text-indigo-100">{{ ucfirst(now()->translatedFormat('l d \d\e F \d\e Y')) }}</p>
            @if ($today)
                <p class="mt-1 text-xl font-bold">Entrada a las {{ $today->check_in_at->format('H:i') }}
                    @if ($today->is_late)<span class="ml-1 rounded-full bg-white/20 px-2 py-0.5 text-xs font-semibold">{{ $today->late_minutes }} min tarde</span>@endif
                </p>
                <p class="text-sm text-indigo-100">{{ $today->scans_count }} toques · último en {{ $today->scans->last()?->location->name }} a las {{ $today->scans->last()?->scanned_at->format('H:i') }}</p>
            @else
                <p class="mt-1 text-xl font-bold">Aún no registras tu entrada hoy</p>
                <p class="text-sm text-indigo-100">Acerca tu teléfono a una etiqueta NFC para marcar.</p>
            @endif
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('my.tracker') }}" class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-indigo-700 shadow hover:bg-indigo-50">
                <x-heroicon-o-signal class="h-5 w-5" /> Marcar / Mi rastreador
            </a>
            <a href="{{ route('complaints.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-white/15 px-4 py-2.5 text-sm font-semibold hover:bg-white/25">
                <x-heroicon-o-flag class="h-5 w-5" /> Reportar queja
            </a>
        </div>
    </section>

    @can('team.view')
        <div x-data="dashboardLive({ url: @js(route('dashboard.data')), initial: @js(['summary' => $summary, 'feed' => $feed]) })">
            <div class="mb-6 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
                <x-stat label="Personal" :value="$summary['total']" bind="summary.total" icon="users" color="gray" />
                <x-stat label="Presentes" :value="$summary['present']" bind="summary.present" icon="check-circle" color="emerald" />
                <x-stat label="En turno ahora" :value="$summary['active']" bind="summary.active" icon="signal" color="indigo" />
                <x-stat label="Tarde" :value="$summary['late']" bind="summary.late" icon="clock" color="amber" />
                <x-stat label="Sin registrar" :value="$summary['absent']" bind="summary.absent" icon="x-circle" color="rose" />
                <x-stat label="Visitas hoy" :value="$summary['visits']" bind="summary.visits" icon="map-pin" color="violet" />
            </div>

            <div class="grid gap-4 lg:grid-cols-3">
                <div class="space-y-4 lg:col-span-2">
                    <x-card title="Asistencia de los últimos 7 días">
                        <x-slot name="headerActions">
                            @can('reports.view')
                                <a href="{{ route('reports.attendance') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">Ver informe</a>
                            @endcan
                        </x-slot>
                        <x-attendance-chart :days="$chart" />
                    </x-card>

                    <x-card title="Actividad en tiempo real" subtitle="Se actualiza automáticamente">
                        <x-slot name="headerActions">
                            <a href="{{ route('team.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">Equipo en vivo →</a>
                        </x-slot>
                        <ul class="divide-y divide-gray-100">
                            <template x-for="item in feed" :key="item.id">
                                <li class="flex items-center gap-3 py-2.5">
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700" x-text="item.initials"></span>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm"><span class="font-medium text-gray-900" x-text="item.user"></span> <span class="text-gray-500">en</span> <span class="text-gray-700" x-text="item.location"></span></p>
                                        <p class="truncate text-xs text-gray-500" x-text="item.comment ? '“' + item.comment + '”' : item.human"></p>
                                    </div>
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset"
                                          :class="{ 'bg-emerald-50 text-emerald-700 ring-emerald-600/20': item.type === 'check_in', 'bg-rose-50 text-rose-700 ring-rose-600/20': item.type === 'check_out', 'bg-sky-50 text-sky-700 ring-sky-600/20': item.type === 'visit' }"
                                          x-text="item.type_label"></span>
                                    <span class="w-11 text-right text-xs tabular-nums text-gray-500" x-text="item.time"></span>
                                </li>
                            </template>
                        </ul>
                        <p x-show="!feed.length" class="py-6 text-center text-sm text-gray-500">Todavía no hay toques registrados.</p>
                    </x-card>
                </div>

                <div class="space-y-4">
                    <x-card title="Llegadas tarde hoy">
                        <ul class="divide-y divide-gray-100">
                            @forelse ($lateToday as $row)
                                <li class="flex items-center justify-between gap-2 py-2 text-sm">
                                    <span class="truncate font-medium text-gray-900">{{ $row['name'] }}</span>
                                    <span class="shrink-0 tabular-nums text-gray-500">{{ $row['check_in'] }} · <span class="text-amber-700">{{ $row['late_minutes'] }} min</span></span>
                                </li>
                            @empty
                                <li class="py-2 text-sm text-gray-500">Nadie ha llegado tarde hoy. 🎉</li>
                            @endforelse
                        </ul>
                    </x-card>

                    <x-card title="Quejas abiertas">
                        <x-slot name="headerActions">
                            <span class="text-sm font-semibold text-gray-700" x-text="summary.open_complaints">{{ $summary['open_complaints'] }}</span>
                        </x-slot>
                        <ul class="divide-y divide-gray-100">
                            @forelse ($recentComplaints as $complaint)
                                <li class="py-2">
                                    <a href="{{ route('complaints.show', $complaint) }}" class="block text-sm">
                                        <span class="font-medium text-gray-900 hover:text-indigo-600">{{ $complaint->subject }}</span>
                                        <span class="mt-0.5 flex items-center gap-2 text-xs text-gray-500">
                                            <x-badge :color="$complaint->priorityColor()">{{ $complaint->priorityLabel() }}</x-badge>
                                            {{ $complaint->location?->name }} · {{ $complaint->created_at->diffForHumans() }}
                                        </span>
                                    </a>
                                </li>
                            @empty
                                <li class="py-2 text-sm text-gray-500">No hay quejas abiertas.</li>
                            @endforelse
                        </ul>
                    </x-card>
                </div>
            </div>
        </div>
    @else
        <div class="grid gap-4 md:grid-cols-2">
            <x-card title="Mi día">
                @if ($today && $today->scans->count())
                    <ul class="divide-y divide-gray-100">
                        @foreach ($today->scans as $scan)
                            <li class="flex items-center justify-between gap-3 py-2 text-sm">
                                <span class="tabular-nums font-semibold">{{ $scan->scanned_at->format('H:i') }}</span>
                                <span class="flex-1 truncate text-gray-700">{{ $scan->location->name }}</span>
                                <x-badge :color="$scan->typeColor()">{{ $scan->typeLabel() }}</x-badge>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <x-empty-state icon="signal" title="Sin toques hoy" description="Tu primer toque NFC del día será tu entrada y el último, tu salida." />
                @endif
            </x-card>
            <div class="grid content-start gap-3 sm:grid-cols-2">
                <a href="{{ route('my.history') }}" class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm hover:border-indigo-300">
                    <x-heroicon-o-calendar-days class="h-6 w-6 text-indigo-600" />
                    <p class="mt-2 font-semibold">Mi historial</p>
                    <p class="text-sm text-gray-500">Asistencia y visitas por mes</p>
                </a>
                <a href="{{ route('complaints.index') }}" class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm hover:border-indigo-300">
                    <x-heroicon-o-flag class="h-6 w-6 text-indigo-600" />
                    <p class="mt-2 font-semibold">Mis quejas</p>
                    <p class="text-sm text-gray-500">{{ $myComplaints }} abiertas</p>
                </a>
                @can('chat.use')
                    <a href="{{ route('chat.index') }}" class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm hover:border-indigo-300">
                        <x-heroicon-o-chat-bubble-left-right class="h-6 w-6 text-indigo-600" />
                        <p class="mt-2 font-semibold">Chat del equipo</p>
                        <p class="text-sm text-gray-500">Mensajes y canal general</p>
                    </a>
                @endcan
                @can('ai.use')
                    <a href="{{ route('ai.index') }}" class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm hover:border-indigo-300">
                        <x-heroicon-o-sparkles class="h-6 w-6 text-indigo-600" />
                        <p class="mt-2 font-semibold">Asistente IA</p>
                        <p class="text-sm text-gray-500">Pregunta por tus horas y visitas</p>
                    </a>
                @endcan
            </div>
        </div>
    @endcan
</x-app-layout>
