@props([
    'number',
    'title',
    'description',
    'items' => [],
])

<div class="grid gap-8">
    <header class="grid gap-6 border-b-2 border-slate-950 pb-8 sm:grid-cols-[1fr_auto] sm:items-end">
        <div class="grid gap-3">
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-[#1746d1]">Tepenet Güvenlik / {{ $number }}</p>
            <h1 class="text-4xl font-semibold tracking-[-0.05em] sm:text-5xl">{{ $title }}</h1>
            <p class="max-w-2xl text-sm leading-6 text-slate-600">{{ $description }}</p>
        </div>
        <span class="font-mono text-5xl font-semibold tracking-[-0.08em] text-slate-300 sm:text-7xl" aria-hidden="true">{{ $number }}</span>
    </header>

    <section class="grid border-2 border-slate-950 bg-white lg:grid-cols-[0.75fr_1.25fr]">
        <div class="grid content-between gap-10 border-b-2 border-slate-950 bg-slate-950 p-7 text-white lg:border-b-0 lg:border-r-2 sm:p-9">
            <div class="grid gap-3">
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-blue-300">Sayfa hazır</p>
                <h2 class="text-2xl font-semibold tracking-[-0.03em]">İçerik sonraki aşamada eklenecek.</h2>
            </div>
            <p class="text-sm leading-6 text-slate-400">Route, erişim kontrolü ve temel arayüz yerleşimi tamamlandı.</p>
        </div>

        <div class="p-7 sm:p-9">
            <h2 class="text-sm font-bold uppercase tracking-[0.16em] text-slate-500">Planlanan içerik</h2>
            <ul class="mt-5 divide-y divide-slate-200 border-y border-slate-200">
                @foreach ($items as $item)
                    <li class="flex items-center gap-4 py-4 text-sm font-medium">
                        <span class="size-2 shrink-0 bg-[#1746d1]" aria-hidden="true"></span>
                        {{ $item }}
                    </li>
                @endforeach
            </ul>
        </div>
    </section>
</div>
