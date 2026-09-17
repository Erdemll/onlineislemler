<?php

use App\Enums\SupportMessageSender;
use App\Enums\SupportTicketStatus;
use App\Models\Customer;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('an administrator can list search and inspect support tickets', function () {
    $admin = User::factory()->admin()->create();
    $customer = Customer::factory()->ready()->create([
        'first_name' => 'Deniz',
        'last_name' => 'Kaya',
        'email' => 'deniz@example.com',
    ]);
    $ticket = SupportTicket::factory()->for($customer)->create([
        'subject' => 'Alarm paneli bağlantısı',
    ]);
    SupportMessage::factory()
        ->for($ticket, 'ticket')
        ->for($customer)
        ->create(['body' => 'Alarm paneline bağlanamıyorum.']);

    $this->actingAs($admin)
        ->get(route('admin.support.index', ['q' => 'deniz@example.com']))
        ->assertOk()
        ->assertSeeText('Alarm paneli bağlantısı')
        ->assertSeeText('Deniz Kaya');

    $this->actingAs($admin)
        ->get(route('admin.support.show', $ticket))
        ->assertOk()
        ->assertSeeText('Alarm paneline bağlanamıyorum.')
        ->assertSeeText($ticket->ticket_number);
});

test('an administrator can reply to a support ticket', function () {
    $admin = User::factory()->admin()->create(['name' => 'Destek Uzmanı']);
    $customer = Customer::factory()->ready()->create();
    $ticket = SupportTicket::factory()->for($customer)->create();

    $this->actingAs($admin)
        ->post(route('admin.support.replies.store', $ticket), [
            'message' => 'Kontrolleri tamamladık, bağlantınızı tekrar deneyebilirsiniz.',
        ])
        ->assertRedirect(route('admin.support.show', $ticket))
        ->assertSessionHas('status');

    $message = SupportMessage::query()->firstOrFail();
    expect($message->sender_type)->toBe(SupportMessageSender::Admin)
        ->and($message->user_id)->toBe($admin->id)
        ->and($message->sender_name_snapshot)->toBe('Destek Uzmanı');
    expect($ticket->fresh()->status)->toBe(SupportTicketStatus::AwaitingCustomer);
});

test('an administrator can close and reopen a support ticket', function () {
    $admin = User::factory()->admin()->create();
    $ticket = SupportTicket::factory()->create();

    $this->actingAs($admin)
        ->patch(route('admin.support.status.update', $ticket), [
            'status' => SupportTicketStatus::Closed->value,
        ])
        ->assertRedirect(route('admin.support.show', $ticket));

    expect($ticket->fresh()->status)->toBe(SupportTicketStatus::Closed)
        ->and($ticket->fresh()->closed_at)->not->toBeNull();

    $this->actingAs($admin)
        ->patch(route('admin.support.status.update', $ticket), [
            'status' => SupportTicketStatus::AwaitingSupport->value,
        ])
        ->assertRedirect(route('admin.support.show', $ticket));

    expect($ticket->fresh()->status)->toBe(SupportTicketStatus::AwaitingSupport)
        ->and($ticket->fresh()->closed_at)->toBeNull();
});

test('an administrator must reopen a closed ticket before replying', function () {
    $admin = User::factory()->admin()->create();
    $ticket = SupportTicket::factory()->create([
        'status' => SupportTicketStatus::Closed,
        'closed_at' => now(),
    ]);

    $this->actingAs($admin)
        ->post(route('admin.support.replies.store', $ticket), [
            'message' => 'Kapalı talep yanıtı',
        ])
        ->assertSessionHasErrors([
            'message' => 'Yanıt vermeden önce destek talebini yeniden açın.',
        ]);

    expect($ticket->messages()->count())->toBe(0);
});

test('regular users and guests cannot access support administration', function () {
    $ticket = SupportTicket::factory()->create();

    $this->get(route('admin.support.index'))
        ->assertRedirect(route('admin.login'));

    $this->actingAs(User::factory()->create())
        ->get(route('admin.support.show', $ticket))
        ->assertForbidden();
});
