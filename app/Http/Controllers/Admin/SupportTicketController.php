<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SupportTicketStatus;
use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupportTicketController extends Controller
{
    public function index(Request $request): View
    {
        $search = mb_substr(trim((string) $request->query('q')), 0, 100);
        $status = SupportTicketStatus::tryFrom((string) $request->query('status'));

        $tickets = SupportTicket::query()
            ->with([
                'customer:id,first_name,last_name,email,company_title',
                'latestMessage',
            ])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('ticket_number', 'like', "%{$search}%")
                        ->orWhere('subject', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn ($query) => $query
                            ->where('email', 'like', "%{$search}%")
                            ->orWhere('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%"));
                });
            })
            ->orderByRaw("CASE WHEN status = 'awaiting_support' THEN 0 WHEN status = 'awaiting_customer' THEN 1 ELSE 2 END")
            ->orderByDesc('last_message_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.support.index', [
            'tickets' => $tickets,
            'search' => $search,
            'selectedStatus' => $status,
            'statuses' => SupportTicketStatus::cases(),
        ]);
    }

    public function show(SupportTicket $supportTicket): View
    {
        $supportTicket->load('customer');
        $messages = $supportTicket->messages()
            ->orderBy('id')
            ->paginate(50);

        return view('admin.support.show', [
            'ticket' => $supportTicket,
            'messages' => $messages,
        ]);
    }
}
