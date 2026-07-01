<?php

use App\Enums\UserRole;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $permissions = ['dashboard.view'];

    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }

    $role = Role::firstOrCreate(['name' => UserRole::Administrator->value, 'guard_name' => 'web']);
    $role->syncPermissions($permissions);
});

it('renders login page for guests', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Masuk ke Backup Manager')
        ->assertSee('name="email"', false)
        ->assertSee('name="password"', false);
});

it('redirects authenticated users away from login', function () {
    actingAsAdmin();

    $this->get(route('login'))
        ->assertRedirect(route('dashboard', absolute: false));
});

it('authenticates administrator with valid credentials', function () {
    $user = User::factory()->create([
        'email' => 'admin@test.com',
        'password' => bcrypt('secret-password'),
    ]);
    $user->assignRole(UserRole::Administrator->value);

    $this->post(route('login'), [
        'email' => 'admin@test.com',
        'password' => 'secret-password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
});

it('rejects invalid credentials', function () {
    User::factory()->create(['email' => 'admin@test.com']);

    $this->from(route('login'))
        ->post(route('login'), [
            'email' => 'admin@test.com',
            'password' => 'wrong-password',
        ])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email']);

    $this->assertGuest();
});

it('persists session after login and allows dashboard access', function () {
    $user = User::factory()->create([
        'email' => 'admin@test.com',
        'password' => bcrypt('secret-password'),
    ]);
    $user->assignRole(UserRole::Administrator->value);

    $this->post(route('login'), [
        'email' => 'admin@test.com',
        'password' => 'secret-password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Dashboard');
});
