@props(['label' => null, 'name' => null, 'hint' => null])

<div {{ $attributes->merge(['class' => 'space-y-1']) }}>
    @if ($label)
        <label @if ($name) for="{{ $name }}" @endif class="block text-sm font-medium text-gray-700">{{ $label }}</label>
    @endif
    {{ $slot }}
    @if ($hint)
        <p class="text-xs text-gray-500">{{ $hint }}</p>
    @endif
    @if ($name)
        <x-input-error :messages="$errors->get($name)" />
    @endif
</div>
