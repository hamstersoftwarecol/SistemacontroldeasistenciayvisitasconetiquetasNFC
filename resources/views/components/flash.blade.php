@if (session('success') || session('status') || session('error') || session('warning'))
    <div x-data="{ show: true }" x-show="show" x-transition class="mb-4 print:hidden">
        @foreach (['success' => 'emerald', 'status' => 'emerald', 'warning' => 'amber', 'error' => 'rose'] as $key => $color)
            @if (session($key) && ! in_array(session($key), ['profile-updated', 'password-updated', 'verification-link-sent'], true))
                <div @class([
                    'flex items-start gap-3 rounded-xl border p-4 text-sm',
                    'border-emerald-200 bg-emerald-50 text-emerald-800' => $color === 'emerald',
                    'border-amber-200 bg-amber-50 text-amber-800' => $color === 'amber',
                    'border-rose-200 bg-rose-50 text-rose-800' => $color === 'rose',
                ])>
                    @if ($color === 'rose')
                        <x-heroicon-o-exclamation-circle class="h-5 w-5 shrink-0" />
                    @elseif ($color === 'amber')
                        <x-heroicon-o-exclamation-triangle class="h-5 w-5 shrink-0" />
                    @else
                        <x-heroicon-o-check-circle class="h-5 w-5 shrink-0" />
                    @endif
                    <p class="flex-1">{{ session($key) }}</p>
                    <button type="button" @click="show = false" class="opacity-60 hover:opacity-100" aria-label="Cerrar">
                        <x-heroicon-o-x-mark class="h-4 w-4" />
                    </button>
                </div>
            @endif
        @endforeach
    </div>
@endif
