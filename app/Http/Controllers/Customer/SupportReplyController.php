<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreSupportReplyRequest;
use App\Models\Customer;
use App\Services\Support\SupportTicketService;
use Illuminate\Http\RedirectResponse;

class SupportReplyController extends Controller
{
    public function __invoke(
        StoreSupportReplyRequest $request,
        string $supportTicket,
        SupportTicketService $supportTicketService,
    ): RedirectResponse {
        /** @var Customer $customer */
        $customer = $request->user('customer');
        $ticket = $customer->supportTickets()
            ->where('uuid', $supportTicket)
            ->firstOrFail();

        $supportTicketService->replyAsCustomer(
            ticket: $ticket,
            customer: $customer,
            body: $request->validated('message'),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return redirect()
            ->route('customer.support.show', $ticket->uuid)
            ->with('status', 'Yanıtınız destek ekibine iletildi.');
    }
}
