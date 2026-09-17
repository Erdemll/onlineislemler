<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('the command creates an administrator with a hashed password', function () {
    $this->artisan('admin:create', [
        'email' => 'ADMIN@EXAMPLE.COM',
        '--name' => 'Panel Yöneticisi',
        '--password' => 'StrongPassword123!',
    ])->assertSuccessful();

    $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

    expect($admin->name)->toBe('Panel Yöneticisi')
        ->and($admin->is_admin)->toBeTrue()
        ->and($admin->email_verified_at)->not->toBeNull()
        ->and(Hash::check('StrongPassword123!', $admin->password))->toBeTrue();
});

test('the command upgrades an existing account without creating a duplicate', function () {
    User::factory()->create(['email' => 'admin@example.com']);

    $this->artisan('admin:create', [
        'email' => 'admin@example.com',
        '--password' => 'AnotherPassword123!',
    ])->assertSuccessful();

    expect(User::query()->where('email', 'admin@example.com')->count())->toBe(1)
        ->and(User::query()->where('email', 'admin@example.com')->value('is_admin'))->toBeTrue();
});
