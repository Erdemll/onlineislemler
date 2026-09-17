@extends('layouts.customer')

@section('title', 'Destek')

@section('content')
    <div class="grid gap-7">
        <header class="grid gap-5 border-b-2 border-slate-950 pb-7 md:grid-cols-[1fr_auto] md:items-end">
            <div>
                <p class="text-xs font-black uppercase tracking-[0.2em] text-[#1746d1]">Tepenet Güvenlik / 03</p>
                <h1 class="mt-3 text-4xl font-black tracking-[-0.055em] sm:text-6xl">Destek</h1>
                <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600">Yeni talep oluşturun, destek ekibinin yanıtlarını takip edin ve görüşmeye devam edin.</p>
            </div>
            <a class="inline-flex min-h-12 items-center justify-center bg-slate-950 px-5 text-sm font-black text-white hover:bg-[#1746d1]" href="{{ route('customer.support.create') }}">Yeni destek talebi</a>
        </header>

        <section class="grid gap-4">
            @forelse ($tickets as $ticket)
                <a class="grid gap-4 border-2 border-slate-950 bg-white p-5 transition hover:-translate-y-0.5 hover:shadow-[5px_5px_0_#0f172a] sm:p-6 md:grid-cols-[1fr_auto] md:items-center" href="{{ route('customer.support.show', $ticket->uuid) }}">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-mono text-xs font-black text-[#1746d1]">{{ $ticket->ticket_number }}</span>
                            <span class="bg-slate-100 px-2 py-1 text-[0.68rem] font-black uppercase tracking-wide">{{ $ticket->category->label() }}</span>
                        </div>
                        <h2 class="mt-3 text-xl font-black tracking-[-0.025em]">{{ $ticket->subject }}</h2>
                        @if ($ticket->latestMessage)
                            <p class="mt-2 line-clamp-2 text-sm leading-6 text-slate-600">{{ $ticket->latestMessage->body }}</p>
                        @endif
                    </div>
                    <div class="grid gap-2 md:justify-items-end">
                        <span @class([
                            'w-fit px-3 py-2 text-xs font-black',
                            'bg-amber-100 text-amber-950' => $ticket->status === \App\Enums\SupportTicketStatus::AwaitingSupport,
                            'bg-blue-100 text-blue-950' => $ticket->status === \App\Enums\SupportTicketStatus::AwaitingCustomer,
                            'bg-slate-200 text-slate-700' => $ticket->status === \App\Enums\SupportTicketStatus::Closed,
                        ])>{{ $ticket->status->label() }}</span>
                        <time class="text-xs font-semibold text-slate-500">{{ $ticket->last_message_at->format('d.m.Y H:i') }}</time>
                    </div>
                </a>
            @empty
                <div class="border-2 border-dashed border-slate-400 bg-white/40 p-8 text-center sm:p-12">
                    <p class="text-xs font-black uppercase tracking-wider text-[#1746d1]">Temiz başlangıç</p>
                    <h2 class="mt-2 text-2xl font-black">Henüz destek talebiniz yok</h2>
                    <p class="mx-auto mt-2 max-w-lg text-sm leading-6 text-slate-600">Bir konuda yardıma ihtiyacınız olduğunda ekibimize doğrudan buradan ulaşabilirsiniz.</p>
                    <a class="mt-5 inline-flex min-h-12 items-center justify-center bg-[#1746d1] px-5 text-sm font-black text-white" href="{{ route('customer.support.create') }}">İlk talebi oluştur</a>
                </div>
            @endforelse
        </section>

        {{ $tickets->links() }}
    </div>
@endsection
