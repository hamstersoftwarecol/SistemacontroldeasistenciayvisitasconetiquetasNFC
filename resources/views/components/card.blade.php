@props(['title' => null, 'subtitle' => null, 'padding' => true])

<section {{ $attributes->merge(['class' => 'rounded-2xl border border-gray-200 bg-white shadow-sm print:border-0 print:shadow-none']) }}>
    @if ($title || isset($headerActions))
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-4 py-3 sm:px-5">
            <div class="min-w-0">
                @if ($title)
                    <h2 class="font-semibold text-gray-900">{{ $title }}</h2>
                @endif
                @if ($subtitle)
                    <p class="text-sm text-gray-500">{{ $subtitle }}</p>
                @endif
            </div>
            @isset($headerActions)
                <div class="flex flex-wrap items-center gap-2">{{ $headerActions }}</div>
            @endisset
        </div>
    @endif
    <div @class(['p-4 sm:p-5' => $padding])>
        {{ $slot }}
    </div>
</section>
