<?php

use App\Models\User;
use App\Services\AdminService;
use Livewire\Livewire;

test('the super admin is always treated as an admin regardless of the is_admin column', function () {
    $user = User::factory()->withTwoFactor()->create([
        'email' => User::SUPER_ADMIN_EMAIL,
        'is_admin' => false,
    ]);

    expect($user->is_admin)->toBeTrue();
    expect($user->isSuperAdmin())->toBeTrue();
});

test('a regular user is not treated as an admin by default', function () {
    $user = User::factory()->withTwoFactor()->create(['is_admin' => false]);

    expect($user->is_admin)->toBeFalse();
    expect($user->isSuperAdmin())->toBeFalse();
});

test('an admin can promote another member to admin', function () {
    $admin = User::factory()->withTwoFactor()->create(['is_admin' => true]);
    $member = User::factory()->withTwoFactor()->create(['is_admin' => false]);

    Livewire::actingAs($admin)
        ->test('pages::admin.members')
        ->call('promote', $member->id)
        ->assertHasNoErrors();

    expect($member->fresh()->is_admin)->toBeTrue();
});

test('an admin can demote another admin', function () {
    $admin = User::factory()->withTwoFactor()->create(['is_admin' => true]);
    $otherAdmin = User::factory()->withTwoFactor()->create(['is_admin' => true]);

    Livewire::actingAs($admin)
        ->test('pages::admin.members')
        ->call('demote', $otherAdmin->id)
        ->assertHasNoErrors();

    expect($otherAdmin->fresh()->is_admin)->toBeFalse();
});

test('the super admin cannot be demoted, even via a direct service call', function () {
    $superAdmin = User::factory()->withTwoFactor()->create(['email' => User::SUPER_ADMIN_EMAIL]);

    expect(fn () => app(AdminService::class)->demote($superAdmin))->toThrow(RuntimeException::class);
    expect($superAdmin->fresh()->is_admin)->toBeTrue();
});

test('attempting to demote the super admin from the page fails gracefully', function () {
    $admin = User::factory()->withTwoFactor()->create(['is_admin' => true]);
    $superAdmin = User::factory()->withTwoFactor()->create(['email' => User::SUPER_ADMIN_EMAIL]);

    Livewire::actingAs($admin)
        ->test('pages::admin.members')
        ->call('demote', $superAdmin->id)
        ->assertHasNoErrors();

    expect($superAdmin->fresh()->is_admin)->toBeTrue();
});

test('the manage-admins page shows a super admin badge instead of a demote button', function () {
    $admin = User::factory()->withTwoFactor()->create(['is_admin' => true]);
    User::factory()->withTwoFactor()->create(['email' => User::SUPER_ADMIN_EMAIL]);

    Livewire::actingAs($admin)
        ->test('pages::admin.members')
        ->assertSee('Super admin');
});

test('a non-admin cannot access the manage-admins page', function () {
    $user = User::factory()->withTwoFactor()->create(['is_admin' => false]);

    Livewire::actingAs($user)
        ->test('pages::admin.members')
        ->assertForbidden();
});
