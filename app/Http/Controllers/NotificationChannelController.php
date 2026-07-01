<?php

namespace App\Http\Controllers;

use App\Enums\NotificationDriver;
use App\Exceptions\NotificationChannelException;
use App\Http\Requests\NotificationChannelRequest;
use App\Models\NotificationChannel;
use App\Repositories\NotificationChannelRepository;
use App\Services\Notification\NotificationChannelService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationChannelController extends Controller
{
    public function index(Request $request, NotificationChannelRepository $repository): View
    {
        $this->authorize('viewAny', NotificationChannel::class);

        $search = $request->string('q')->trim()->toString();
        $status = $request->string('status')->toString();
        $driver = $request->string('driver')->toString();

        return view('notifications.index', [
            'channels' => $repository->paginate(
                search: $search !== '' ? $search : null,
                status: $status === 'all' || $status === '' ? null : $status,
                driver: $driver === 'all' || $driver === '' ? null : $driver,
            ),
            'drivers' => NotificationDriver::cases(),
            'search' => $search,
            'statusFilter' => $status !== '' ? $status : 'all',
            'driverFilter' => $driver !== '' ? $driver : 'all',
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', NotificationChannel::class);

        return view('notifications.create', [
            'defaults' => array_merge(
                NotificationChannelRequest::formDefaultsFromConfig([]),
                [
                    'driver' => NotificationDriver::Email->value,
                    'is_active' => true,
                    'notify_on_success' => true,
                    'notify_on_failure' => true,
                ],
            ),
            'drivers' => NotificationDriver::cases(),
        ]);
    }

    public function store(NotificationChannelRequest $request, NotificationChannelService $service): RedirectResponse
    {
        $this->authorize('create', NotificationChannel::class);

        try {
            $service->create($request->toServicePayload());

            return redirect()
                ->route('notifications.index')
                ->with('success', 'Notification channel berhasil ditambahkan.');
        } catch (NotificationChannelException $exception) {
            return back()
                ->withInput()
                ->with('error', $exception->userMessage());
        }
    }

    public function edit(NotificationChannel $notificationChannel): View
    {
        $this->authorize('update', $notificationChannel);

        return view('notifications.edit', [
            'channel' => $notificationChannel,
            'formDefaults' => array_merge(
                NotificationChannelRequest::formDefaultsFromConfig($notificationChannel->config ?? [], clearSecrets: true),
                [
                    'name' => $notificationChannel->name,
                    'driver' => $notificationChannel->driver->value,
                    'is_active' => $notificationChannel->is_active,
                    'notify_on_success' => $notificationChannel->notify_on_success,
                    'notify_on_failure' => $notificationChannel->notify_on_failure,
                ],
            ),
            'drivers' => NotificationDriver::cases(),
        ]);
    }

    public function update(
        NotificationChannelRequest $request,
        NotificationChannel $notificationChannel,
        NotificationChannelService $service,
    ): RedirectResponse {
        $this->authorize('update', $notificationChannel);

        try {
            $service->update($notificationChannel, $request->toServicePayload());

            return redirect()
                ->route('notifications.index')
                ->with('success', 'Notification channel berhasil diperbarui.');
        } catch (NotificationChannelException $exception) {
            return back()
                ->withInput()
                ->with('error', $exception->userMessage());
        }
    }

    public function destroy(NotificationChannel $notificationChannel, NotificationChannelService $service): RedirectResponse
    {
        $this->authorize('delete', $notificationChannel);

        $service->delete($notificationChannel);

        return redirect()
            ->route('notifications.index')
            ->with('success', 'Notification channel berhasil dihapus.');
    }

    public function testForm(NotificationChannelRequest $request, NotificationChannelService $service): RedirectResponse
    {
        try {
            $driver = NotificationDriver::from($request->validated()['driver']);
            $config = $request->buildConfig();
            $channel = $request->route('notification_channel');

            if ($channel instanceof NotificationChannel) {
                $this->authorize('update', $channel);
                $config = $request->mergeSecretsForTest($channel->config ?? []);
            } else {
                $this->authorize('create', NotificationChannel::class);
            }

            $testResult = $service->testConfig($driver, $config, (int) auth()->id())->toArray();

            return back()
                ->withInput()
                ->with('testResult', $testResult);
        } catch (NotificationChannelException $exception) {
            return back()
                ->withInput()
                ->with('error', $exception->userMessage());
        }
    }

    public function test(NotificationChannel $notificationChannel, NotificationChannelService $service): RedirectResponse
    {
        try {
            $this->authorize('test', $notificationChannel);

            $testResult = $service->test($notificationChannel, (int) auth()->id())->toArray();

            return back()
                ->with('testResult', $testResult);
        } catch (NotificationChannelException $exception) {
            return back()->with('error', $exception->userMessage());
        }
    }
}
