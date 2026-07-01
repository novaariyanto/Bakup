<?php

namespace App\Http\Controllers;

use App\Enums\StorageDriver;
use App\Exceptions\StorageDestinationException;
use App\Http\Requests\StorageDestinationRequest;
use App\Models\BackupDestination;
use App\Repositories\BackupDestinationRepository;
use App\Services\Storage\StorageDestinationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StorageDestinationController extends Controller
{
    public function index(Request $request, BackupDestinationRepository $repository): View
    {
        $this->authorize('viewAny', BackupDestination::class);

        $search = $request->string('q')->trim()->toString();
        $status = $request->string('status')->toString();
        $driver = $request->string('driver')->toString();

        return view('storage-destinations.index', [
            'destinations' => $repository->paginate(
                search: $search !== '' ? $search : null,
                status: $status === 'all' || $status === '' ? null : $status,
                driver: $driver === 'all' || $driver === '' ? null : $driver,
            ),
            'drivers' => StorageDriver::cases(),
            'search' => $search,
            'statusFilter' => $status !== '' ? $status : 'all',
            'driverFilter' => $driver !== '' ? $driver : 'all',
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', BackupDestination::class);

        return view('storage-destinations.create', [
            'defaults' => array_merge(
                StorageDestinationRequest::formDefaultsFromConfig([]),
                [
                    'driver' => StorageDriver::Local->value,
                    'is_active' => true,
                ],
            ),
            'drivers' => StorageDriver::cases(),
        ]);
    }

    public function store(StorageDestinationRequest $request, StorageDestinationService $service): RedirectResponse
    {
        $this->authorize('create', BackupDestination::class);

        try {
            $service->create($request->toServicePayload());

            return redirect()
                ->route('storage-destinations.index')
                ->with('success', 'Storage destination berhasil ditambahkan.');
        } catch (StorageDestinationException $exception) {
            return back()
                ->withInput()
                ->with('error', $exception->userMessage());
        }
    }

    public function edit(BackupDestination $backupDestination): View
    {
        $this->authorize('update', $backupDestination);

        return view('storage-destinations.edit', [
            'destination' => $backupDestination,
            'formDefaults' => array_merge(
                StorageDestinationRequest::formDefaultsFromConfig($backupDestination->config ?? [], clearSecrets: true),
                [
                    'name' => $backupDestination->name,
                    'driver' => $backupDestination->driver->value,
                    'is_active' => $backupDestination->is_active,
                ],
            ),
            'drivers' => StorageDriver::cases(),
        ]);
    }

    public function update(
        StorageDestinationRequest $request,
        BackupDestination $backupDestination,
        StorageDestinationService $service,
    ): RedirectResponse {
        $this->authorize('update', $backupDestination);

        try {
            $service->update($backupDestination, $request->toServicePayload());

            return redirect()
                ->route('storage-destinations.index')
                ->with('success', 'Storage destination berhasil diperbarui.');
        } catch (StorageDestinationException $exception) {
            return back()
                ->withInput()
                ->with('error', $exception->userMessage());
        }
    }

    public function destroy(BackupDestination $backupDestination, StorageDestinationService $service): RedirectResponse
    {
        $this->authorize('delete', $backupDestination);

        $service->delete($backupDestination);

        return redirect()
            ->route('storage-destinations.index')
            ->with('success', 'Storage destination berhasil dihapus.');
    }

    public function testForm(StorageDestinationRequest $request, StorageDestinationService $service): RedirectResponse
    {
        try {
            $driver = StorageDriver::from($request->validated()['driver']);
            $config = $request->buildConfig();
            $destination = $request->route('backup_destination');

            if ($destination instanceof BackupDestination) {
                $this->authorize('update', $destination);
                $config = $request->mergeSecretsForTest($destination->config ?? []);
            } else {
                $this->authorize('create', BackupDestination::class);
            }

            $testResult = $service->testConfig($driver, $config, (int) auth()->id())->toArray();

            return back()
                ->withInput()
                ->with('testResult', $testResult);
        } catch (StorageDestinationException $exception) {
            return back()
                ->withInput()
                ->with('error', $exception->userMessage());
        }
    }

    public function test(BackupDestination $backupDestination, StorageDestinationService $service): RedirectResponse
    {
        try {
            $this->authorize('test', $backupDestination);

            $testResult = $service->test($backupDestination, (int) auth()->id())->toArray();

            return back()
                ->with('testResult', $testResult);
        } catch (StorageDestinationException $exception) {
            return back()->with('error', $exception->userMessage());
        }
    }
}
