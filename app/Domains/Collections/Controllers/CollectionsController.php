<?php

namespace App\Domains\Collections\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Collections\Models\Collection;
use App\Domains\Collections\Models\CollectionFolder;
use App\Domains\Requests\Models\Request as ApiRequest;
use App\Enums\TeamRole;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CollectionsController extends Controller
{
    public function index(Request $request, Collection $collection = null, ApiRequest $apiRequest = null)
    {
        $team = $request->user()->currentTeam;

        if (!$team) {
            return Inertia::render('Dashboard');
        }

        if ($collection && $collection->team_id !== $team->id) {
            abort(403);
        }

        if ($apiRequest && $apiRequest->collection->team_id !== $team->id) {
            abort(403);
        }

        return Inertia::render('Collections/Index', [
            'activeCollectionId' => $collection ? $collection->id : ($apiRequest ? $apiRequest->collection_id : null),
            'activeRequestId' => $apiRequest ? $apiRequest->id : null,
        ]);
    }

    public function details(Collection $collection)
    {
        $collection->load(['requests', 'folders.requests']);
        $collection->has_loaded_details = true;
        return response()->json($collection);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $team = $request->user()->currentTeam;

        $collection = Collection::create([
            'team_id' => $team->id,
            'user_id' => $request->user()?->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        if ($request->wantsJson()) {
            return response()->json($collection, 201);
        }

        return back()->with('flash', [
            'message' => 'Collection created successfully',
            'collection' => $collection,
        ]);
    }

    public function updateCollection(Request $request, Collection $collection)
    {
        $team = $request->user()->currentTeam;

        if ($collection->team_id !== $team->id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $collection->update(array_filter($validated, fn($v) => !is_null($v)));

        if ($request->wantsJson()) {
            return response()->json($collection);
        }

        return back()->with('flash', ['message' => 'Collection updated successfully']);
    }

    public function storeFolder(Request $request, Collection $collection)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|uuid|exists:collection_folders,id',
            'description' => 'nullable|string',
        ]);

        $folder = CollectionFolder::create([
            'collection_id' => $collection->id,
            'user_id' => $request->user()?->id,
            'parent_id' => $validated['parent_id'] ?? null,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        if ($request->wantsJson()) {
            return response()->json($folder, 201);
        }

        return back()->with('flash', [
            'message' => 'Folder created successfully',
            'folder' => $folder,
        ]);
    }

    public function storeRequest(Request $request, Collection $collection)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'folder_id' => 'nullable|uuid|exists:collection_folders,id',
            'method' => 'required|string|in:GET,POST,PUT,PATCH,DELETE,OPTIONS,HEAD',
            'url' => 'nullable|string',
            'pre_request_script' => 'nullable|string',
            'test_script' => 'nullable|string',
        ]);

        $apiRequest = ApiRequest::create([
            'collection_id' => $collection->id,
            'user_id' => $request->user()?->id,
            'folder_id' => $validated['folder_id'] ?? null,
            'name' => $validated['name'],
            'method' => $validated['method'],
            'url' => $validated['url'] ?? '',
            'pre_request_script' => $validated['pre_request_script'] ?? '',
            'test_script' => $validated['test_script'] ?? '',
            'headers' => [],
            'query_params' => [],
            'path_variables' => [],
            'body' => [],
            'auth' => [],
        ]);

        if ($request->wantsJson()) {
            return response()->json($apiRequest, 201);
        }

        return back()->with('flash', [
            'message' => 'Request created successfully',
            'request' => $apiRequest,
        ]);
    }

    public function updateRequest(Request $request, ApiRequest $apiRequest)
    {
        $validated = $request->validate([
            'collection_id' => 'nullable|uuid|exists:collections,id',
            'folder_id' => 'nullable|uuid|exists:collection_folders,id',
            'name' => 'nullable|string|max:255',
            'method' => 'nullable|string|in:GET,POST,PUT,PATCH,DELETE,OPTIONS,HEAD',
            'url' => 'nullable|string',
            'headers' => 'nullable|array',
            'query_params' => 'nullable|array',
            'path_variables' => 'nullable|array',
            'body' => 'nullable|string',
            'pre_request_script' => 'nullable|string',
            'test_script' => 'nullable|string',
            'auth' => 'nullable|array',
        ]);

        if (isset($validated['collection_id']) && $validated['collection_id'] !== $apiRequest->collection_id) {
            $team = $request->user()->currentTeam;
            $targetCollection = Collection::findOrFail($validated['collection_id']);
            if ($targetCollection->team_id !== $team->id) {
                abort(403, 'Unauthorized target collection.');
            }
        }

        \Log::info("updateRequest payload:", $request->all());
        $updateData = array_filter($validated, fn($value) => !is_null($value));

        // Allow setting folder_id to null when moving to root
        if ($request->has('folder_id') && is_null($request->input('folder_id'))) {
            $updateData['folder_id'] = null;
        }

        if (isset($updateData['body'])) {
            $decoded = json_decode($updateData['body'], true);
            $updateData['body'] = is_array($decoded) ? $decoded : ['text' => $updateData['body']];
        }

        $apiRequest->update($updateData);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Request saved successfully',
                'request' => $apiRequest,
            ]);
        }

        return back()->with('flash', [
            'message' => 'Request saved successfully',
            'request' => $apiRequest,
        ]);
    }

    public function updateFolder(Request $request, CollectionFolder $folder)
    {
        $team = $request->user()->currentTeam;

        if ($folder->collection->team_id !== $team->id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|uuid|exists:collection_folders,id',
            'collection_id' => 'sometimes|required|uuid|exists:collections,id',
        ]);

        $updateData = array_filter($validated, fn($v) => !is_null($v));

        // Allow setting parent_id to null when moving to root
        if ($request->has('parent_id') && is_null($request->input('parent_id'))) {
            $updateData['parent_id'] = null;
        }

        // Prevent moving a folder into itself or one of its subfolders
        if (isset($updateData['parent_id']) && !is_null($updateData['parent_id'])) {
            $curr = CollectionFolder::find($updateData['parent_id']);
            while ($curr) {
                if ($curr->id === $folder->id) {
                    abort(400, 'Cannot move a folder into itself or one of its subfolders.');
                }
                $curr = $curr->parent;
            }
        }

        if (isset($updateData['collection_id']) && $updateData['collection_id'] !== $folder->collection_id) {
            $newCollectionId = $updateData['collection_id'];

            // Verify target collection belongs to team
            Collection::where('id', $newCollectionId)->where('team_id', $team->id)->firstOrFail();

            // Helper to recursively update descendant folders and requests
            $updateDescendants = function ($folderId) use (&$updateDescendants, $newCollectionId) {
                ApiRequest::where('folder_id', $folderId)->update(['collection_id' => $newCollectionId]);
                $childFolders = CollectionFolder::where('parent_id', $folderId)->get();
                foreach ($childFolders as $child) {
                    $child->update(['collection_id' => $newCollectionId]);
                    $updateDescendants($child->id);
                }
            };

            $updateDescendants($folder->id);
        }

        $folder->update($updateData);

        if ($request->wantsJson()) {
            return response()->json($folder);
        }

        return back()->with('flash', ['message' => 'Folder renamed successfully']);
    }

    public function destroyFolder(Request $request, CollectionFolder $folder)
    {
        $team = $request->user()->currentTeam;

        if ($folder->collection->team_id !== $team->id) {
            abort(403);
        }

        if ($request->user()->teamRole($team) === TeamRole::Member && $folder->user_id !== $request->user()->id) {
            abort(403, 'You can only delete your own folders.');
        }

        // Delete all requests in this folder
        $folder->requests()->delete();

        $folder->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('flash', [
            'message' => 'Folder deleted successfully',
        ]);
    }

    public function destroyCollection(Request $request, Collection $collection)
    {
        $team = $request->user()->currentTeam;

        if ($collection->team_id !== $team->id) {
            abort(403);
        }

        if ($request->user()->teamRole($team) === TeamRole::Member && $collection->user_id !== $request->user()->id) {
            abort(403, 'You can only delete your own collections.');
        }

        // Delete all requests in this collection
        $collection->requests()->delete();

        // Delete all folders in this collection
        $collection->folders()->delete();

        $collection->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('flash', [
            'message' => 'Collection deleted successfully',
        ]);
    }

    public function destroyRequest(Request $request, ApiRequest $apiRequest)
    {
        $team = $request->user()->currentTeam;

        if ($apiRequest->collection->team_id !== $team->id) {
            abort(403);
        }

        if ($request->user()->teamRole($team) === TeamRole::Member && $apiRequest->user_id !== $request->user()->id) {
            abort(403, 'You can only delete your own requests.');
        }

        $collectionId = $apiRequest->collection_id;
        $requestId = $apiRequest->id;

        $apiRequest->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        if (str_contains(url()->previous(), "/requests/{$requestId}")) {
            return redirect()->route('collections.show', $collectionId)->with('flash', [
                'message' => 'Request deleted successfully',
            ]);
        }

        return back()->with('flash', [
            'message' => 'Request deleted successfully',
        ]);
    }

    public function destroyRequestsBatch(Request $request)
    {
        $team = $request->user()->currentTeam;

        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|uuid|exists:requests,id',
        ]);

        if ($request->user()->teamRole($team) === TeamRole::Member) {
            $unownedCount = ApiRequest::whereIn('id', $validated['ids'])
                ->whereHas('collection', function ($query) use ($team) {
                    $query->where('team_id', $team->id);
                })
                ->where(function ($q) use ($request) {
                    $q->whereNull('user_id')->orWhere('user_id', '!=', $request->user()->id);
                })
                ->count();

            if ($unownedCount > 0) {
                abort(403, 'You can only delete your own requests.');
            }
        }

        $firstCollectionId = ApiRequest::whereIn('id', $validated['ids'])->value('collection_id');

        $count = ApiRequest::whereIn('id', $validated['ids'])
            ->whereHas('collection', function ($query) use ($team) {
                $query->where('team_id', $team->id);
            })
            ->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'count' => $count]);
        }

        $previousUrl = url()->previous();
        foreach ($validated['ids'] as $id) {
            if (str_contains($previousUrl, "/requests/{$id}")) {
                return redirect()->route('collections.show', $firstCollectionId)->with('flash', [
                    'message' => "{$count} requests deleted successfully",
                ]);
            }
        }

        return back()->with('flash', [
            'message' => "{$count} requests deleted successfully",
        ]);
    }

    public function cloneRequest(Request $request, ApiRequest $apiRequest)
    {
        $team = $request->user()->currentTeam;

        if ($apiRequest->collection->team_id !== $team->id) {
            abort(403);
        }

        $clonedRequest = $apiRequest->replicate();
        $clonedRequest->user_id = $request->user()?->id;
        $clonedRequest->name = $apiRequest->name . ' (Copy)';
        $clonedRequest->save();

        if ($request->wantsJson()) {
            return response()->json($clonedRequest, 201);
        }

        return back()->with('flash', [
            'message' => 'Request cloned successfully',
            'request' => $clonedRequest,
        ]);
    }
}
