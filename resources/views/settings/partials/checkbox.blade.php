<label class="flex items-start gap-3 text-sm">
    <input type="hidden" name="{{ $name }}" value="0">
    <input type="checkbox" name="{{ $name }}" value="1" @checked(old($name, $values[$name] ?? false) && old($name, $values[$name] ?? false) !== '0') class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
    <span>
        <span class="font-medium text-gray-900">{{ $label }}</span>
        @if (! empty($hint))
            <span class="block text-xs text-gray-500">{{ $hint }}</span>
        @endif
    </span>
</label>
