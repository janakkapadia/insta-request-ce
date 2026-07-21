<?php

namespace App\Http\Controllers;

use App\Domains\Collections\Models\Collection;
use App\Domains\Environments\Models\Environment;
use App\Domains\History\Models\RequestHistory;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $team = $request->user()->currentTeam;

        $recentCollections = Collection::where('team_id', $team->id)
            ->withCount('requests')
            ->latest()
            ->take(6)
            ->get();

        $recentHistory = RequestHistory::where('team_id', $team->id)
            ->with('request')
            ->latest()
            ->take(10)
            ->get();

        return Inertia::render('Dashboard', [
            'recentCollections' => $recentCollections,
            'recentHistory' => $recentHistory,
            'stats' => [
                'total_collections' => Collection::where('team_id', $team->id)->count(),
                'total_requests_made' => RequestHistory::where('team_id', $team->id)->count(),
                'total_environments' => Environment::where('team_id', $team->id)->count(),
            ],
        ]);
    }
}
