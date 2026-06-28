<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\Collections\Models\Collection;
use App\Domains\History\Models\RequestHistory;
use App\Domains\Environments\Models\Environment;
use Illuminate\Http\Request;

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

        return response()->json([
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
