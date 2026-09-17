@extends('layouts.admin')

@section('title', $ticket->ticket_number)

@section('content')
    <header class="grid gap-5 border-b-2 border-slate-950 pb-7 md:grid-cols-[1fr_auto] md:items-end">
        <div>
            <a class="inline-flex min-h-11 items-center text-sm font-bold underline decoration-2 underline-offset-4" href="{{ route('admin.support.index') }}">← Destek taleplerine dön</a>
            <p class="mt-4 font-mono text-xs font-black uppercase tracking-[0.15em] text-[#1746d1]">{{ $ticket->ticket_number }}</p>
            <h1 class="mt-3 text-3xl font-black tracking-[-0.045em] sm:text-5xl">{{ $ticket->subject }}</h1>
            <p class="mt-2 text-sm text-slate-600">{{ $ticket->category->label() }} · {{ $ticket->customer->billingTitle() }} · {{ $ticket->customer->email }}</p>
        </div>
        <form method="POST" action="{{ route('admin.support.status.update', $ticket) }}">
            @csrf
            @method('PATCH')
            @if ($ticket->status === \App\Enums\SupportTicketStatus::Closed)
                <input type="hidden" name="status" value="{{ \App\Enums\SupportTicketStatus::AwaitingSupport->value }}">
                <button class="inline-flex min-h-12 items-center justify-center border-2 border-slate-950 px-5 text-sm font-black hover:bg-slate-950 hover:text-white" type="submit">Talebi yeniden aç</button>
            @else
                <input type="hidden" name="status" value="{{ \App\Enums\SupportTicketStatus::Closed->value }}">
                <button class="inline-flex min-h-12 items-center justify-center border-2 border-red-800 px-5 text-sm font-black text-red-900 hover:bg-red-800 hover:text-white" type="submit">Talebi kapat</button>
            @endif
        </form>
    </header>

    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_18rem] lg:items-start">
        <div class="grid min-w-0 gap-5">
            <section class="grid gap-4" aria-label="Destek görüşmesi">
                @foreach ($messages as $message)
                    @php($isAdmin = $message->sender_type === \App\Enums\SupportMessageSender::Admin)
                    <article @class(['grid max-w-3xl gap-2', 'justify-self-end' => $isAdmin, 'justify-self-start' => ! $isAdmin])>
                        <div @class([
                            'border-2 border-slate-950 p-4 sm:p-5',
                            'bg-slate-950 text-white' => $isAdmin,
                            'bg-white' => ! $isAdmin,
                        ])>
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <p class="text-xs font-black uppercase tracking-wider">{{ $message->sender_type->label() }} · {{ $message->sender_name_snapshot }}</p>
                                <time class="text-xs opacity-60">{{ $message->created_at->format('d.m.Y H:i') }}</time>
                            </div>
                            <p class="mt-3 whitespace-pre-wrap break-words text-sm leading-6">{{ $message->body }}</p>
                        </div>
                    </article>
                @endforeach
            </section>

            {{ $messages->links() }}

            @if ($ticket->status !== \App\Enums\SupportTicketStatus::Closed)
                <form class="grid gap-4 border-2 border-slate-950 bg-white p-5 sm:p-7" method="POST" action="{{ route('admin.support.replies.store', $ticket) }}">
                    @csrf
                    <label class="text-lg font-black" for="message">Müşteriye yanıt ver</label>
                    <textarea class="min-h-36 w-full resize-y border-2 border-slate-950 p-3.5 text-base leading-6 outline-none focus:border-[#1746d1]" id="message" name="message" maxlength="5000" required>{{ old('message') }}</textarea>
                    @error('message') <p class="text-sm font-bold text-red-800">{{ $message }}</p> @enderror
                    <button class="inline-flex min-h-12 items-center justify-center bg-[#1746d1] px-6 text-sm font-black text-white hover:bg-slate-950 sm:justify-self-end" type="submit">Yanıtı gönder</button>
                </form>
            @else
                <div class="border-2 border-slate-400 bg-white/60 p-5 text-sm text-slate-600">Yanıt göndermek için talebi yeniden açın.</div>
            @endif
        </div>

        <aside class="grid gap-px border-2 border-slate-950 bg-slate-200">
            @foreach ([
                'Durum' => $ticket->status->label(),
                'Kategori' => $ticket->category->label(),
                'Müşteri' => $ticket->customer->billingTitle(),
                'E-posta' => $ticket->customer->email,
                'Telefon' => $ticket->customer->phone,
                'Açılış' => $ticket->created_at->format('d.m.Y H:i'),
                'Son hareket' => $ticket->last_message_at->format('d.m.Y H:i'),
            ] as $label => $value)
                <div class="bg-white p-4">
                    <p class="text-[0.68rem] font-black uppercase tracking-wider text-slate-500">{{ $label }}</p>
                    <p class="mt-1 break-words text-sm font-bold">{{ $value }}</p>
                </div>
            @endforeach
        </aside>
    </div>
@endsection
