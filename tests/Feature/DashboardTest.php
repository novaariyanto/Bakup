<?php

use App\Models\BackupHistory;
use App\Models\BackupProfile;

it('requires authentication to access dashboard', function () {
    $this->get(route('dashboard'))
        ->assertRedirect(route('login'));
});

it('renders dashboard for authenticated administrator', function () {
    actingAsAdmin();

    $profile = BackupProfile::factory()->create(['name' => 'Dashboard Profile', 'is_active' => true]);
    BackupHistory::factory()->success()->create([
        'backup_profile_id' => $profile->id,
        'compressed_size_bytes' => 2048,
    ]);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Dashboard')
        ->assertSee('Dashboard Profile')
        ->assertSee('2 KB');
});

it('allows administrator to logout', function () {
    actingAsAdmin();

    $this->post(route('logout'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});
