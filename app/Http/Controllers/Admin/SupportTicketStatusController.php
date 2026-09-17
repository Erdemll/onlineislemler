<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SupportTicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSupportTicketStatusRequest;
use App\Models\SupportTicket;
use App\Services\Support\SupportTicketService;
use Illuminate\Http\RedirectResponse;

class SupportTicketStatusController extends Controller
{
    public function __invoke(
        UpdateSupportTicketStatusRequest $request,
        SupportTicket $supportTicket,
        SupportTicketService $supportTicketService,
    ): RedirectResponse {
        $status = SupportTicketStatus::from($request->validated('status'));
        $supportTicketService->updateStatus($supportTicket, $status);

        return redirect()
            ->route('admin.support.show', $supportTicket)
            ->with('status', $status === SupportTicketStatus::Closed
                ? 'Destek talebi kapatıldı.'
                : 'Destek talebi yeniden açıldı.');
    }
}
