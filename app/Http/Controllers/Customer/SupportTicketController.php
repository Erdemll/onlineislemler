<?php

namespace App\Http\Controllers\Customer;

use App\Enums\SupportTicketCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreSupportTicketRequest;
use App\Models\Customer;
use App\Models\SupportTicket;
use App\Services\Support\SupportTicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupportTicketController extends Controller
{
    public function index(Request $request): View
    {
        $tickets = $this->customer($request)->supportTickets()
            ->with('latestMessage')
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate(12);

        return view('customer.support.index', compact('tickets'));
    }

    public function create(): View
    {
        return view('customer.support.create', [
            'categories' => SupportTicketCategory::cases(),
        ]);
    }

    public function store(
        StoreSupportTicketRequest $request,
        SupportTicketService $supportTicketService,
    ): RedirectResponse {
        $ticket = $supportTicketService->create(
            customer: $this->customer($request),
            category: SupportTicketCategory::from($request->validated('category')),
            subject: $request->validated('subject'),
            body: $request->validated('message'),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return redirect()
            ->route('customer.support.show', $ticket->uuid)
            ->with('status', 'Destek talebiniz oluşturuldu.');
    }

    public function show(Request $request, string $supportTicket): View
    {
        $ticket = $this->ownedTicket($request, $supportTicket);
        $messages = $ticket->messages()
            ->orderBy('id')
            ->paginate(50);

        return view('customer.support.show', compact('ticket', 'messages'));
    }

    private function customer(Request $request): Customer
    {
        /** @var Customer $customer */
        $customer = $request->user('customer');

        return $customer;
    }

    private function ownedTicket(Request $request, string $uuid): SupportTicket
    {
        return $this->customer($request)->supportTickets()
            ->where('uuid', $uuid)
            ->firstOrFail();
    }
}
