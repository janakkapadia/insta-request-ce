<?php

namespace App\Domains\History\Controllers;

use App\Domains\History\Models\RequestHistory;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;

class HistoryController extends Controller
{
    public function index(Request $request)
    {
        $team = $request->user()->currentTeam;

        if (! $team) {
            return Inertia::render('Dashboard');
        }

        $history = RequestHistory::where('team_id', $team->id)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return Inertia::render('History/Index', [
            'history' => $history,
        ]);
    }

    public function apiIndex(Request $request)
    {
        $team = $request->user()->currentTeam;

        if (! $team) {
            return response()->json([]);
        }

        $query = RequestHistory::where('team_id', $team->id);

        Log::info('apiIndex called', ['request_id' => $request->input('request_id'), 'team_id' => $team->id]);

        if ($request->has('request_id') && Str::isUuid($request->input('request_id'))) {
            $query->where('request_id', $request->input('request_id'));
        }

        $history = $query->with('user')
            ->orderBy('created_at', 'desc')
            ->take(50)
            ->get();

        Log::info('apiIndex results', ['count' => $history->count()]);

        return response()->json($history);
    }

    public function destroy(Request $request)
    {
        $team = $request->user()->currentTeam;

        if ($team) {
            $ids = $request->input('ids');
            if (is_array($ids) && count($ids) > 0) {
                RequestHistory::where('team_id', $team->id)
                    ->whereIn('id', $ids)
                    ->delete();
                $message = 'Selected history records deleted successfully';
            } else {
                RequestHistory::where('team_id', $team->id)->delete();
                $message = 'History cleared successfully';
            }
        } else {
            $message = 'Unable to delete history';
        }

        return back()->with('flash', [
            'message' => $message,
        ]);
    }
}
