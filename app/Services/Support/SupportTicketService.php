<?php

namespace App\Services\Support;

use App\Enums\SupportMessageSender;
use App\Enums\SupportTicketCategory;
use App\Enums\SupportTicketStatus;
use App\Models\Customer;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SupportTicketService
{
    public function create(
        Customer $customer,
        SupportTicketCategory $category,
        string $subject,
        string $body,
        ?string $ipAddress,
        ?string $userAgent,
    ): SupportTicket {
        return DB::transaction(function () use ($customer, $category, $subject, $body, $ipAddress, $userAgent): SupportTicket {
            $ticket = $customer->supportTickets()->create([
                'ticket_number' => $this->generateTicketNumber(),
                'category' => $category,
                'subject' => $subject,
                'status' => SupportTicketStatus::AwaitingSupport,
                'last_message_at' => now(),
            ]);

            $ticket->messages()->create([
                'sender_type' => SupportMessageSender::Customer,
                'customer_id' => $customer->id,
                'sender_name_snapshot' => $customer->billingTitle(),
                'body' => $body,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);

            return $ticket;
        });
    }

    public function replyAsCustomer(
        SupportTicket $ticket,
        Customer $customer,
        string $body,
        ?string $ipAddress,
        ?string $userAgent,
    ): void {
        DB::transaction(function () use ($ticket, $customer, $body, $ipAddress, $userAgent): void {
            $lockedTicket = SupportTicket::query()->lockForUpdate()->findOrFail($ticket->id);

            if ($lockedTicket->status === SupportTicketStatus::Closed) {
                throw ValidationException::withMessages([
                    'message' => 'Kapatılmış bir destek talebine yanıt veremezsiniz.',
                ]);
            }

            $lockedTicket->messages()->create([
                'sender_type' => SupportMessageSender::Customer,
                'customer_id' => $customer->id,
                'sender_name_snapshot' => $customer->billingTitle(),
                'body' => $body,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);

            $lockedTicket->update([
                'status' => SupportTicketStatus::AwaitingSupport,
                'last_message_at' => now(),
            ]);
        });
    }

    public function replyAsAdmin(
        SupportTicket $ticket,
        User $admin,
        string $body,
        ?string $ipAddress,
        ?string $userAgent,
    ): void {
        DB::transaction(function () use ($ticket, $admin, $body, $ipAddress, $userAgent): void {
            $lockedTicket = SupportTicket::query()->lockForUpdate()->findOrFail($ticket->id);

            if ($lockedTicket->status === SupportTicketStatus::Closed) {
                throw ValidationException::withMessages([
                    'message' => 'Yanıt vermeden önce destek talebini yeniden açın.',
                ]);
            }

            $lockedTicket->messages()->create([
                'sender_type' => SupportMessageSender::Admin,
                'user_id' => $admin->id,
                'sender_name_snapshot' => $admin->name,
                'body' => $body,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);

            $lockedTicket->update([
                'status' => SupportTicketStatus::AwaitingCustomer,
                'last_message_at' => now(),
            ]);
        });
    }

    public function updateStatus(SupportTicket $ticket, SupportTicketStatus $status): void
    {
        DB::transaction(function () use ($ticket, $status): void {
            SupportTicket::query()
                ->lockForUpdate()
                ->findOrFail($ticket->id)
                ->update([
                    'status' => $status,
                    'closed_at' => $status === SupportTicketStatus::Closed ? now() : null,
                ]);
        });
    }

    private function generateTicketNumber(): string
    {
        do {
            $number = 'DST-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (SupportTicket::query()->where('ticket_number', $number)->exists());

        return $number;
    }
}
