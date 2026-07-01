<?php

namespace App\Http\Controllers;

use App\Services\Dashboard\DashboardService;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(DashboardService $dashboardService): View
    {
        abort_unless(auth()->user()?->can('dashboard.view'), 403);

        $overview = $dashboardService->getOverview();

        return view('dashboard.index', [
            'stats' => $overview['stats'],
            'activityChart' => $overview['activity_chart'],
            'recentActivity' => $overview['recent_activity'],
        ]);
    }
}
