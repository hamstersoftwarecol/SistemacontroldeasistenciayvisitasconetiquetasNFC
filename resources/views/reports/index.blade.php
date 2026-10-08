<x-app-layout>
    <x-slot name="title">Informes</x-slot>
    <x-slot name="header">Informes</x-slot>

    <div class="grid gap-4 md:grid-cols-3">
        @foreach ([
            ['route' => 'reports.attendance', 'icon' => 'clipboard-document-check', 'title' => 'Asistencia', 'text' => 'Entradas, salidas, horas trabajadas, tardanzas y ausencias por empleado.'],
            ['route' => 'reports.visits', 'icon' => 'map', 'title' => 'Visitas', 'text' => 'Toques por ubicación y por empleado, con comentarios y evidencias.'],
            ['route' => 'reports.complaints', 'icon' => 'flag', 'title' => 'Quejas', 'text' => 'Quejas por estado, categoría y prioridad, con tiempos de resolución.'],
        ] as $card)
            <a href="{{ route($card['route']) }}" class="group rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-indigo-300 hover:shadow">
                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                    <x-dynamic-component :component="'heroicon-o-'.$card['icon']" class="h-6 w-6" />
                </span>
                <p class="mt-3 text-lg font-semibold group-hover:text-indigo-700">{{ $card['title'] }}</p>
                <p class="mt-1 text-sm text-gray-500">{{ $card['text'] }}</p>
                <p class="mt-3 text-sm font-medium text-indigo-600">Abrir informe →</p>
            </a>
        @endforeach
    </div>

    <x-card class="mt-4" title="Imprimir y exportar">
        <ul class="list-disc space-y-1 pl-5 text-sm text-gray-600">
            <li><strong>Imprimir / PDF:</strong> usa el diálogo de impresión del navegador y elige «Guardar como PDF».</li>
            <li><strong>Excel (.xlsx):</strong> un archivo con varias hojas (resumen y detalle).</li>
            <li><strong>CSV:</strong> separado por punto y coma, listo para Excel en español.</li>
        </ul>
    </x-card>
</x-app-layout>
