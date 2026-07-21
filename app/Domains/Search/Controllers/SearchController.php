<?php

namespace App\Domains\Search\Controllers;

use App\Domains\Collections\Models\Collection;
use App\Domains\Collections\Models\CollectionFolder;
use App\Domains\Requests\Models\Request as ApiRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        $term = $request->query('q', '');
        $team = $request->user() ? $request->user()->currentTeam : null;

        if (! $team || strlen($term) < 1) {
            return response()->json(['collections' => [], 'folders' => [], 'requests' => []]);
        }

        // Search Collections
        $collections = Collection::where('team_id', $team->id)
            ->where('name', 'like', "%{$term}%")
            ->limit(10)
            ->get(['id', 'name']);

        // Search Folders
        $folders = CollectionFolder::whereHas('collection', function ($q) use ($team) {
            $q->where('team_id', $team->id);
        })
            ->with('collection:id,name')
            ->where('name', 'like', "%{$term}%")
            ->limit(10)
            ->get(['id', 'collection_id', 'name']);

        // Search Requests
        $requests = ApiRequest::whereHas('collection', function ($q) use ($team) {
            $q->where('team_id', $team->id);
        })
            ->with(['collection:id,name', 'folder:id,name'])
            ->where(function ($query) use ($term) {
                $query->where('name', 'like', "%{$term}%")
                    ->orWhere('url', 'like', "%{$term}%");
            })
            ->limit(20)
            ->get(['id', 'collection_id', 'folder_id', 'name', 'method', 'url']);

        return response()->json([
            'collections' => $collections,
            'folders' => $folders,
            'requests' => $requests,
        ]);
    }
}
