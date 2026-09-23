@extends('layouts.admin')

@section('title', 'Destek talepleri')

@section('content')
    <header class="grid gap-5 border-b-2 border-slate-950 pb-7">
        <div>
            <p class="text-xs font-black uppercase tracking-[0.2em] text-[#1746d1]">Yönetim / 04</p>
            <h1 class="mt-3 text-4xl font-black tracking-[-0.055em] sm:text-6xl">Destek talepleri</h1>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600">Müşteri görüşmelerini yanıtlayın ve tamamlanan talepleri kapatın.</p>
        </div>

        <form class="grid gap-3 border-2 border-slate-950 bg-white p-4 sm:grid-cols-[1fr_14rem_auto]" method="GET" action="{{ route('admin.support.index') }}">
            <label class="sr-only" for="q">Talep ara</label>
            <input class="min-h-12 min-w-0 border-2 border-slate-950 px-3.5 text-base outline-none focus:border-[#1746d1]" id="q" name="q" value="{{ $search }}" placeholder="Talep no, başlık, müşteri">
            <label class="sr-only" for="status">Durum</label>
            <select class="min-h-12 border-2 border-slate-950 bg-white px-3.5 text-base" id="status" name="status">
                <option value="">Tüm durumlar</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected($selectedStatus === $status)>{{ $status->label() }}</option>
                @endforeach
            </select>
            <button class="min-h-12 bg-slate-950 px-5 text-sm font-black text-white" type="submit">Filtrele</button>
        </form>
    </header>

    <section class="border-2 border-slate-950 bg-white">
        <div class="hidden grid-cols-[1fr_0.8fr_0.8fr_auto] gap-4 border-b-2 border-slate-950 bg-slate-950 px-5 py-3 text-xs font-black uppercase tracking-wider text-white xl:grid">
            <span>Talep</span><span>Müşteri</span><span>Durum</span><span>İşlem</span>
        </div>
        <div class="divide-y divide-slate-200">
            @forelse ($tickets as $ticket)
                <article class="grid gap-4 p-5 xl:grid-cols-[1fr_0.8fr_0.8fr_auto] xl:items-center">
                    <div class="min-w-0">
                        <p class="font-mono text-xs font-black text-[#1746d1]">{{ $ticket->ticket_number }}</p>
                        <h2 class="mt-1 font-black">{{ $ticket->subject }}</h2>
                        @if ($ticket->latestMessage)
                            <p class="mt-1 truncate text-xs text-slate-500">{{ $ticket->latestMessage->sender_type->label() }}: {{ $ticket->latestMessage->body }}</p>
                        @endif
                    </div>
                    <div>
                        <p class="text-xs font-black uppercase tracking-wide text-slate-500 xl:hidden">Müşteri</p>
                        <p class="text-sm font-bold">{{ $ticket->customer->billingTitle() }}</p>
                        <p class="mt-1 break-all text-xs text-slate-500">{{ $ticket->customer->email }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-black uppercase tracking-wide text-slate-500 xl:hidden">Durum</p>
                        <span @class([
                            'mt-1 inline-flex px-2.5 py-1 text-xs font-black xl:mt-0',
                            'bg-amber-100 text-amber-950' => $ticket->status === \App\Enums\SupportTicketStatus::AwaitingSupport,
                            'bg-blue-100 text-blue-950' => $ticket->status === \App\Enums\SupportTicketStatus::AwaitingCustomer,
                            'bg-slate-200 text-slate-700' => $ticket->status === \App\Enums\SupportTicketStatus::Closed,
                        ])>{{ $ticket->status->label() }}</span>
                        <p class="mt-2 text-xs text-slate-500">{{ $ticket->last_message_at->format('d.m.Y H:i') }}</p>
                    </div>
                    <a class="inline-flex min-h-11 items-center justify-center border-2 border-slate-950 px-4 text-sm font-black hover:bg-slate-950 hover:text-white" href="{{ route('admin.support.show', $ticket) }}">Görüşmeyi aç</a>
                </article>
            @empty
                <p class="p-8 text-center text-sm text-slate-600">Filtrelerle eşleşen destek talebi bulunamadı.</p>
            @endforelse
        </div>
    </section>

    {{ $tickets->links() }}
@endsection
