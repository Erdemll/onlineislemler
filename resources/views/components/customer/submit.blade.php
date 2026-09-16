@props(['label'])

<button
    type="submit"
    {{ $attributes->class('inline-flex min-h-12 w-full items-center justify-center bg-[#1746d1] px-5 py-3 text-sm font-bold text-white transition hover:bg-[#1037a7] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-950 active:translate-y-px') }}
>
    {{ $label }}
</button>
