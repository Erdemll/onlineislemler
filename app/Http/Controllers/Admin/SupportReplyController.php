<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSupportReplyRequest;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Support\SupportTicketService;
use Illuminate\Http\RedirectResponse;

class SupportReplyController extends Controller
{
    public function __invoke(
        StoreSupportReplyRequest $request,
        SupportTicket $supportTicket,
        SupportTicketService $supportTicketService,
    ): RedirectResponse {
        /** @var User $admin */
        $admin = $request->user();

        $supportTicketService->replyAsAdmin(
            ticket: $supportTicket,
            admin: $admin,
            body: $request->validated('message'),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return redirect()
            ->route('admin.support.show', $supportTicket)
            ->with('status', 'Yanıt müşteriye iletildi.');
    }
}
