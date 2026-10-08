{{-- Botones de impresión/exportación y encabezado visible solo al imprimir --}}
<div class="mb-4 flex flex-wrap items-center gap-2 print:hidden">
    <x-button type="button" variant="secondary" icon="printer" onclick="window.print()">Imprimir / PDF</x-button>
    <x-button-link :href="request()->fullUrlWithQuery(['exportar' => 'xlsx'])" variant="secondary" icon="table-cells">Excel</x-button-link>
    @foreach ($csvTables as $table)
        <x-button-link :href="request()->fullUrlWithQuery(['exportar' => 'csv', 'tabla' => $table])" variant="secondary" icon="document-arrow-down">CSV {{ strtolower($table) }}</x-button-link>
    @endforeach
</div>

<div class="mb-4 hidden border-b border-gray-300 pb-3 print:block">
    <p class="text-lg font-bold">{{ $companyName }}</p>
    <p class="text-base">{{ $reportTitle }}</p>
    <p class="text-sm text-gray-600">Periodo: {{ $period }} · Generado el {{ now()->translatedFormat('d M Y H:i') }} por {{ auth()->user()->name }}</p>
</div>
