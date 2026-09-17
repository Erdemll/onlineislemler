<?php

use App\Enums\SupportMessageSender;
use App\Enums\SupportTicketCategory;
use App\Enums\SupportTicketStatus;
use App\Models\Customer;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('verified customers can reach support even when cari plus provisioning is pending', function () {
    $customer = Customer::factory()->emailVerified()->create();

    $this->actingAsCustomer($customer)
        ->get(route('customer.support.index'))
        ->assertOk();
});

test('a customer can create a support ticket', function () {
    $customer = Customer::factory()->ready()->create();

    $response = $this->actingAsCustomer($customer)
        ->post(route('customer.support.store'), [
            'category' => SupportTicketCategory::Billing->value,
            'subject' => 'Faturam hakkında',
            'message' => 'Son faturamdaki tutarı kontrol eder misiniz?',
        ]);

    $ticket = SupportTicket::query()->firstOrFail();
    $message = SupportMessage::query()->firstOrFail();

    $response->assertRedirect(route('customer.support.show', $ticket->uuid))
        ->assertSessionHas('status');

    expect($ticket->customer_id)->toBe($customer->id)
        ->and($ticket->category)->toBe(SupportTicketCategory::Billing)
        ->and($ticket->status)->toBe(SupportTicketStatus::AwaitingSupport)
        ->and($ticket->ticket_number)->toStartWith('DST-');
    expect($message->sender_type)->toBe(SupportMessageSender::Customer)
        ->and($message->body)->toBe('Son faturamdaki tutarı kontrol eder misiniz?');
});

test('a customer can view and reply to their own ticket', function () {
    $customer = Customer::factory()->ready()->create();
    $ticket = SupportTicket::factory()->for($customer)->create([
        'subject' => 'Kamera bağlantısı',
        'status' => SupportTicketStatus::AwaitingCustomer,
    ]);
    SupportMessage::factory()
        ->for($ticket, 'ticket')
        ->for($customer)
        ->create([
            'sender_name_snapshot' => 'Destek',
            'body' => 'Modeminizi yeniden başlatır mısınız?',
        ]);

    $this->actingAsCustomer($customer)
        ->get(route('customer.support.show', $ticket->uuid))
        ->assertOk()
        ->assertSeeText('Kamera bağlantısı')
        ->assertSeeText('Modeminizi yeniden başlatır mısınız?');

    $this->actingAsCustomer($customer)
        ->post(route('customer.support.replies.store', $ticket->uuid), [
            'message' => 'Yeniden başlattım, sorun devam ediyor.',
        ])
        ->assertRedirect(route('customer.support.show', $ticket->uuid));

    expect($ticket->fresh()->status)->toBe(SupportTicketStatus::AwaitingSupport);
    $this->assertDatabaseHas('support_messages', [
        'support_ticket_id' => $ticket->id,
        'customer_id' => $customer->id,
        'body' => 'Yeniden başlattım, sorun devam ediyor.',
    ]);
});

test('a customer cannot discover or reply to another customers ticket', function () {
    $owner = Customer::factory()->ready()->create();
    $otherCustomer = Customer::factory()->ready()->create();
    $ticket = SupportTicket::factory()->for($owner)->create();

    $this->actingAsCustomer($otherCustomer)
        ->get(route('customer.support.show', $ticket->uuid))
        ->assertNotFound();

    $this->actingAsCustomer($otherCustomer)
        ->post(route('customer.support.replies.store', $ticket->uuid), [
            'message' => 'Yetkisiz yanıt',
        ])
        ->assertNotFound();

    expect($ticket->messages()->count())->toBe(0);
});

test('a customer cannot reply to a closed ticket', function () {
    $customer = Customer::factory()->ready()->create();
    $ticket = SupportTicket::factory()->for($customer)->create([
        'status' => SupportTicketStatus::Closed,
        'closed_at' => now(),
    ]);

    $this->actingAsCustomer($customer)
        ->post(route('customer.support.replies.store', $ticket->uuid), [
            'message' => 'Bu talebe tekrar yazıyorum.',
        ])
        ->assertSessionHasErrors([
            'message' => 'Kapatılmış bir destek talebine yanıt veremezsiniz.',
        ]);

    expect($ticket->messages()->count())->toBe(0);
});

test('support messages are escaped when rendered', function () {
    $customer = Customer::factory()->ready()->create();
    $ticket = SupportTicket::factory()->for($customer)->create();
    SupportMessage::factory()
        ->for($ticket, 'ticket')
        ->for($customer)
        ->create(['body' => '<script>alert("xss")</script>']);

    $this->actingAsCustomer($customer)
        ->get(route('customer.support.show', $ticket->uuid))
        ->assertOk()
        ->assertSee('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', false)
        ->assertDontSee('<script>alert("xss")</script>', false);
});

test('support ticket input is validated', function () {
    $customer = Customer::factory()->ready()->create();

    $this->actingAsCustomer($customer)
        ->post(route('customer.support.store'), [
            'category' => 'invalid',
            'subject' => 'x',
            'message' => 'kısa',
        ])
        ->assertSessionHasErrors(['category', 'subject', 'message']);

    expect(SupportTicket::query()->count())->toBe(0);
});
