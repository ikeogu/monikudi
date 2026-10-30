<?php

use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

test('admins can add a collector who can then log in', function () {
    $this->actingAs($this->admin)->post(route('staff.store'), [
        'name' => 'Blessing Uche',
        'email' => 'blessing@example.com',
        'phone' => '08023456789',
        'role' => 'collector',
        'is_active' => true,
        'password' => 'secret-password',
        'password_confirmation' => 'secret-password',
    ])->assertRedirect(route('staff.index'));

    $member = User::where('email', 'blessing@example.com')->sole();
    expect($member->role)->toBe('collector')->and($member->isAdmin())->toBeFalse();

    $this->post('/logout');
    $this->post('/login', ['email' => 'blessing@example.com', 'password' => 'secret-password']);
    $this->assertAuthenticatedAs($member);
});

test('the team page lists staff with today\'s collections', function () {
    User::factory()->collector()->create(['name' => 'Emeka Johnson']);

    $this->actingAs($this->admin)->get(route('staff.index'))
        ->assertInertia(fn ($page) => $page->component('Staff/Index')->has('staff', 2));
});

test('updating staff keeps the password unless a new one is given', function () {
    $member = User::factory()->collector()->create();
    $hash = $member->password;

    $this->actingAs($this->admin)->put(route('staff.update', $member), [
        'name' => 'Renamed', 'email' => $member->email, 'role' => 'collector', 'is_active' => false, 'password' => '',
    ])->assertSessionHasNoErrors();

    $member->refresh();
    expect($member->name)->toBe('Renamed')
        ->and($member->is_active)->toBeFalse()
        ->and($member->password)->toBe($hash);
});

test('admins cannot remove their own admin access', function () {
    $this->actingAs($this->admin)->put(route('staff.update', $this->admin), [
        'name' => $this->admin->name, 'email' => $this->admin->email, 'role' => 'collector', 'is_active' => true,
    ])->assertSessionHas('error');

    expect($this->admin->fresh()->role)->toBe('admin');
});

test('admins can demote or deactivate other admins', function () {
    $other = User::factory()->create();

    $this->actingAs($this->admin)->put(route('staff.update', $other), [
        'name' => $other->name, 'email' => $other->email, 'role' => 'collector', 'is_active' => false,
    ])->assertSessionHasNoErrors();

    expect($other->fresh()->role)->toBe('collector')
        ->and(User::active()->where('role', 'admin')->count())->toBe(1);
});
