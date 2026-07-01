<?php

namespace App\Http\Controllers;

use App\Exceptions\DatabaseConnectionException;
use App\Http\Requests\DatabaseConnectionRequest;
use App\Models\DatabaseConnection;
use App\Repositories\DatabaseConnectionRepository;
use App\Services\Database\DatabaseConnectionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DatabaseConnectionController extends Controller
{
    public function index(Request $request, DatabaseConnectionRepository $repository): View
    {
        $this->authorize('viewAny', DatabaseConnection::class);

        $search = $request->string('q')->trim()->toString();
        $status = $request->string('status')->toString();

        return view('database-connections.index', [
            'connections' => $repository->paginate(
                search: $search !== '' ? $search : null,
                status: $status === 'all' || $status === '' ? null : $status,
            ),
            'search' => $search,
            'statusFilter' => $status !== '' ? $status : 'all',
        ]);
    }

    public function create(): View|RedirectResponse
    {
        if (! auth()->user()?->can('create', DatabaseConnection::class)) {
            return redirect()
                ->route('database-connections.index')
                ->with('error', 'Anda tidak memiliki izin untuk menambah koneksi database.');
        }

        return view('database-connections.create', [
            'defaults' => [
                'host' => '127.0.0.1',
                'port' => 3306,
                'username' => 'root',
                'is_active' => true,
            ],
        ]);
    }

    public function store(DatabaseConnectionRequest $request, DatabaseConnectionService $service): RedirectResponse
    {
        $this->authorize('create', DatabaseConnection::class);

        try {
            $service->create($request->validated());

            return redirect()
                ->route('database-connections.index')
                ->with('success', 'Koneksi database berhasil ditambahkan.');
        } catch (DatabaseConnectionException $exception) {
            return back()
                ->withInput()
                ->with('error', $exception->userMessage());
        }
    }

    public function edit(DatabaseConnection $databaseConnection): View
    {
        $this->authorize('update', $databaseConnection);

        return view('database-connections.edit', [
            'connection' => $databaseConnection,
        ]);
    }

    public function update(
        DatabaseConnectionRequest $request,
        DatabaseConnection $databaseConnection,
        DatabaseConnectionService $service,
    ): RedirectResponse {
        $this->authorize('update', $databaseConnection);

        try {
            $service->update($databaseConnection, $request->validated());

            return redirect()
                ->route('database-connections.index')
                ->with('success', 'Koneksi database berhasil diperbarui.');
        } catch (DatabaseConnectionException $exception) {
            return back()
                ->withInput()
                ->with('error', $exception->userMessage());
        }
    }

    public function destroy(DatabaseConnection $databaseConnection, DatabaseConnectionService $service): RedirectResponse
    {
        $this->authorize('delete', $databaseConnection);

        try {
            $service->delete($databaseConnection);

            return redirect()
                ->route('database-connections.index')
                ->with('success', 'Koneksi database berhasil dihapus.');
        } catch (DatabaseConnectionException $exception) {
            return redirect()
                ->route('database-connections.index')
                ->with('error', $exception->userMessage());
        }
    }

    public function testForm(DatabaseConnectionRequest $request, DatabaseConnectionService $service): RedirectResponse
    {
        $connection = $request->route('database_connection');

        if ($connection instanceof DatabaseConnection) {
            $this->authorize('update', $connection);
        } else {
            $this->authorize('create', DatabaseConnection::class);
        }

        try {
            $data = $request->validated();

            $password = $data['password'] ?? '';
            if ($password === '' && $connection instanceof DatabaseConnection) {
                $password = $connection->password;
            }

            $testResult = $service->testCredentials(
                $data['host'],
                $data['port'],
                $data['database_name'],
                $data['username'],
                $password,
                (int) auth()->id(),
            )->toArray();

            return back()
                ->withInput()
                ->with('testResult', $testResult);
        } catch (DatabaseConnectionException $exception) {
            return back()
                ->withInput()
                ->with('error', $exception->userMessage());
        }
    }

    public function test(DatabaseConnection $databaseConnection, DatabaseConnectionService $service): RedirectResponse
    {
        try {
            $this->authorize('test', $databaseConnection);

            $testResult = $service->test($databaseConnection, (int) auth()->id())->toArray();

            return back()
                ->with('testResult', $testResult);
        } catch (DatabaseConnectionException $exception) {
            return back()->with('error', $exception->userMessage());
        }
    }
}
