@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'hint' => null,
])

<div class="grid gap-2">
    <div class="flex items-baseline justify-between gap-4">
        <label class="text-sm font-semibold" for="{{ $name }}">{{ $label }}</label>
        @if ($hint)
            <span class="text-xs text-slate-500">{{ $hint }}</span>
        @endif
    </div>

    <input
        id="{{ $name }}"
        type="{{ $type }}"
        name="{{ $name }}"
        @if (! is_null($value)) value="{{ $value }}" @endif
        @if ($errors->has($name)) aria-invalid="true" aria-describedby="{{ $name }}-error" @endif
        {{ $attributes->class([
            'min-h-12 w-full border-2 bg-white px-3.5 py-2.5 text-base text-slate-950 outline-none transition placeholder:text-slate-400 focus:border-[#1746d1] focus:ring-2 focus:ring-[#1746d1]/20',
            'border-red-700' => $errors->has($name),
            'border-slate-950' => ! $errors->has($name),
        ]) }}
    >

    @error($name)
        <p class="text-sm font-medium text-red-800" id="{{ $name }}-error">{{ $message }}</p>
    @enderror
</div>
