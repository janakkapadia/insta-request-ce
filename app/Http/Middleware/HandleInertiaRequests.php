<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'status' => fn () => $request->session()->get('status'),
            'flash' => fn () => $request->session()->get('flash') ?? [],
            'auth' => [
                'user' => $user,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'currentTeam' => fn () => $user?->currentTeam ? $user->toUserTeam($user->currentTeam) : null,
            'teams' => fn () => $user?->toUserTeams(includeCurrent: true) ?? [],
            'environments' => function () use ($request, $user) {
                if ($request->header('X-Inertia') && !$request->header('X-Inertia-Partial-Data')) {
                    return null;
                }
                return $user?->currentTeam ? \App\Domains\Environments\Models\Environment::with('variables')->where('team_id', $user->currentTeam->id)->get() : [];
            },
            'collections' => function () use ($request, $user) {
                if ($request->header('X-Inertia') && !$request->header('X-Inertia-Partial-Data')) {
                    return null;
                }
                return $this->getCollections($request, $user);
            },
        ];
    }

    private function getCollections(Request $request, $user)
    {
        if (!$user || !$user->currentTeam) {
            return [];
        }

        $team = $user->currentTeam;
        $collections = \App\Domains\Collections\Models\Collection::where('team_id', $team->id)->get();

        if ($collections->isEmpty()) {
            $defaultCollection = \App\Domains\Collections\Models\Collection::create([
                'team_id' => $team->id,
                'name' => 'Echo API',
                'description' => 'A sample collection to demonstrate real-time API collaboration.',
            ]);

            $folder = \App\Domains\Collections\Models\CollectionFolder::create([
                'collection_id' => $defaultCollection->id,
                'name' => 'Users',
            ]);

            \App\Domains\Requests\Models\Request::create([
                'collection_id' => $defaultCollection->id,
                'folder_id' => $folder->id,
                'name' => 'Get Users',
                'method' => 'GET',
                'url' => 'https://jsonplaceholder.typicode.com/users',
                'headers' => [],
                'query_params' => [],
                'body' => ['text' => ''],
                'auth' => [],
            ]);

            \App\Domains\Requests\Models\Request::create([
                'collection_id' => $defaultCollection->id,
                'folder_id' => $folder->id,
                'name' => 'Create User',
                'method' => 'POST',
                'url' => 'https://jsonplaceholder.typicode.com/users',
                'headers' => [['key' => 'Content-Type', 'value' => 'application/json']],
                'query_params' => [],
                'body' => ['text' => "{\n  \"name\": \"Leanne Graham\",\n  \"username\": \"Bret\",\n  \"email\": \"Sincere@april.biz\"\n}"],
                'auth' => [],
            ]);

            \App\Domains\Requests\Models\Request::create([
                'collection_id' => $defaultCollection->id,
                'name' => 'Auth Login',
                'method' => 'POST',
                'url' => 'https://reqres.in/api/login',
                'headers' => [['key' => 'Content-Type', 'value' => 'application/json']],
                'query_params' => [],
                'body' => ['text' => "{\n  \"email\": \"eve.holt@reqres.in\",\n  \"password\": \"cityslicka\"\n}"],
                'auth' => [],
            ]);

            $collections = \App\Domains\Collections\Models\Collection::where('team_id', $team->id)->get();
        }

        // Determine active collection ID
        $activeCollectionId = null;
        if ($request->route('collection')) {
            $colParam = $request->route('collection');
            $activeCollectionId = $colParam instanceof \App\Domains\Collections\Models\Collection ? $colParam->id : $colParam;
        } elseif ($request->route('apiRequest')) {
            $reqParam = $request->route('apiRequest');
            if ($reqParam instanceof \App\Domains\Requests\Models\Request) {
                $activeCollectionId = $reqParam->collection_id;
            } else {
                $reqModel = \App\Domains\Requests\Models\Request::find($reqParam);
                if ($reqModel) {
                    $activeCollectionId = $reqModel->collection_id;
                }
            }
        }

        // Loaded IDs from query
        $loadedIds = $request->query('loaded_ids', []);
        if (!is_array($loadedIds)) {
            $loadedIds = [$loadedIds];
        }

        foreach ($collections as $col) {
            if (($activeCollectionId && $col->id === $activeCollectionId) || in_array($col->id, $loadedIds)) {
                $col->load(['requests', 'folders.requests']);
                $col->has_loaded_details = true;
            } else {
                $col->setRelation('requests', collect([]));
                $col->setRelation('folders', collect([]));
                $col->has_loaded_details = false;
            }
        }

        return $collections;
    }
}
