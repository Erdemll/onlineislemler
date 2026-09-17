<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('an administrator can sign in and sign out', function () {
    $admin = User::factory()->admin()->create([
        'email' => 'admin@example.com',
        'password' => 'StrongPassword123!',
    ]);

    $this->post(route('admin.login.store'), [
        'email' => 'ADMIN@EXAMPLE.COM',
        'password' => 'StrongPassword123!',
    ])->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticatedAs($admin);

    $this->post(route('admin.logout'))
        ->assertRedirect(route('admin.login'));

    $this->assertGuest();
});

test('a regular user cannot sign in to the admin panel', function () {
    User::factory()->create([
        'email' => 'user@example.com',
        'password' => 'StrongPassword123!',
    ]);

    $this->post(route('admin.login.store'), [
        'email' => 'user@example.com',
        'password' => 'StrongPassword123!',
    ])->assertSessionHasErrors([
        'email' => 'Giriş bilgileri hatalı veya bu hesabın yönetici yetkisi bulunmuyor.',
    ]);

    $this->assertGuest();
});

test('admin pages redirect guests to the admin login page', function () {
    $this->get(route('admin.dashboard'))
        ->assertRedirect(route('admin.login'));
});

test('authenticated regular users are forbidden from admin pages', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});
