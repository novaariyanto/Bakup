<?php

use App\Models\DatabaseConnection;

it('requires authentication to access database connections', function () {
    $this->get(route('database-connections.index'))
        ->assertRedirect(route('login'));
});

it('renders database connections page for administrator', function () {
    actingAsAdmin();

    $this->get(route('database-connections.index'))
        ->assertOk()
        ->assertSee('Database Connections');
});

it('creates a database connection', function () {
    actingAsAdmin();

    $this->post(route('database-connections.store'), [
        'name' => 'Production DB',
        'host' => '127.0.0.1',
        'port' => 3306,
        'database_name' => 'myapp',
        'username' => 'root',
        'password' => 'secret',
        'is_active' => '1',
    ])->assertRedirect(route('database-connections.index'));

    $this->assertDatabaseHas('database_connections', [
        'name' => 'Production DB',
        'host' => '127.0.0.1',
        'database_name' => 'myapp',
        'username' => 'root',
        'is_active' => true,
    ]);
});

it('updates a database connection without changing password', function () {
    actingAsAdmin();

    $connection = DatabaseConnection::factory()->create([
        'name' => 'Old Name',
        'password' => 'original-secret',
    ]);

    $this->put(route('database-connections.update', $connection), [
        'name' => 'New Name',
        'host' => $connection->host,
        'port' => $connection->port,
        'database_name' => $connection->database_name,
        'username' => $connection->username,
        'password' => '',
        'is_active' => '1',
    ])->assertRedirect(route('database-connections.index'));

    expect($connection->fresh()->name)->toBe('New Name');
    expect($connection->fresh()->password)->toBe('original-secret');
});

it('deletes a database connection', function () {
    actingAsAdmin();

    $connection = DatabaseConnection::factory()->create();

    $this->delete(route('database-connections.destroy', $connection))
        ->assertRedirect(route('database-connections.index'));

    $this->assertSoftDeleted('database_connections', ['id' => $connection->id]);
});

it('filters connections by search query', function () {
    actingAsAdmin();

    DatabaseConnection::factory()->create(['name' => 'Alpha DB']);
    DatabaseConnection::factory()->create(['name' => 'Beta DB']);

    $this->get(route('database-connections.index', ['q' => 'Alpha']))
        ->assertOk()
        ->assertSee('Alpha DB')
        ->assertDontSee('Beta DB');
});

it('filters connections by active status', function () {
    actingAsAdmin();

    DatabaseConnection::factory()->create(['name' => 'Active DB', 'is_active' => true]);
    DatabaseConnection::factory()->inactive()->create(['name' => 'Inactive DB']);

    $this->get(route('database-connections.index', ['status' => 'inactive']))
        ->assertOk()
        ->assertSee('Inactive DB')
        ->assertDontSee('Active DB');
});

it('shows create form when add button is clicked', function () {
    actingAsAdmin();

    $this->get(route('database-connections.create'))
        ->assertOk()
        ->assertSee('Koneksi aktif')
        ->assertSee('name="name"', false);
});

it('opens test result modal for connection test', function () {
    actingAsAdmin();

    $connection = DatabaseConnection::factory()->create([
        'host' => '127.0.0.1',
        'port' => 3306,
        'database_name' => 'backup_manager',
        'username' => 'root',
        'password' => '',
    ]);

    $this->post(route('database-connections.test', $connection))
        ->assertRedirect()
        ->assertSessionHas('testResult');
});
