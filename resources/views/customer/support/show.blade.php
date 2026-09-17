@extends('layouts.customer')

@section('title', $ticket->subject)

@section('content')
    <div class="grid gap-6">
        <header class="grid gap-5 border-b-2 border-slate-950 pb-7 md:grid-cols-[1fr_auto] md:items-end">
            <div>
                <a class="inline-flex min-h-11 items-center text-sm font-bold underline decoration-2 underline-offset-4" href="{{ route('customer.support.index') }}">← Taleplere dön</a>
                <p class="mt-4 font-mono text-xs font-black uppercase tracking-[0.15em] text-[#1746d1]">{{ $ticket->ticket_number }}</p>
                <h1 class="mt-3 text-3xl font-black tracking-[-0.045em] sm:text-5xl">{{ $ticket->subject }}</h1>
                <p class="mt-2 text-sm text-slate-600">{{ $ticket->category->label() }} · {{ $ticket->created_at->format('d.m.Y H:i') }}</p>
            </div>
            <span @class([
                'inline-flex min-h-10 w-fit items-center px-3 text-xs font-black uppercase tracking-wide',
                'bg-amber-100 text-amber-950' => $ticket->status === \App\Enums\SupportTicketStatus::AwaitingSupport,
                'bg-blue-100 text-blue-950' => $ticket->status === \App\Enums\SupportTicketStatus::AwaitingCustomer,
                'bg-slate-200 text-slate-700' => $ticket->status === \App\Enums\SupportTicketStatus::Closed,
            ])>{{ $ticket->status->label() }}</span>
        </header>

        <section class="grid gap-4" aria-label="Destek görüşmesi">
            @foreach ($messages as $message)
                @php($isCustomer = $message->sender_type === \App\Enums\SupportMessageSender::Customer)
                <article @class(['grid max-w-3xl gap-2', 'justify-self-end' => $isCustomer, 'justify-self-start' => ! $isCustomer])>
                    <div @class([
                        'border-2 border-slate-950 p-4 sm:p-5',
                        'bg-slate-950 text-white' => $isCustomer,
                        'bg-white' => ! $isCustomer,
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
            <form class="grid gap-4 border-2 border-slate-950 bg-white p-5 sm:p-7" method="POST" action="{{ route('customer.support.replies.store', $ticket->uuid) }}">
                @csrf
                <div>
                    <label class="text-lg font-black" for="message">Yanıt yaz</label>
                    <p class="mt-1 text-xs text-slate-500">Yanıtınız destek ekibine iletilecek.</p>
                </div>
                <textarea class="min-h-32 w-full resize-y border-2 border-slate-950 p-3.5 text-base leading-6 outline-none focus:border-[#1746d1]" id="message" name="message" maxlength="5000" required>{{ old('message') }}</textarea>
                @error('message') <p class="text-sm font-bold text-red-800">{{ $message }}</p> @enderror
                <button class="inline-flex min-h-12 items-center justify-center bg-[#1746d1] px-6 text-sm font-black text-white hover:bg-slate-950 sm:justify-self-end" type="submit">Yanıtı gönder</button>
            </form>
        @else
            <div class="border-2 border-slate-400 bg-white/60 p-5 text-sm text-slate-600">Bu destek talebi kapatıldı. Yeni bir konu için yeni destek talebi oluşturabilirsiniz.</div>
        @endif
    </div>
@endsection
