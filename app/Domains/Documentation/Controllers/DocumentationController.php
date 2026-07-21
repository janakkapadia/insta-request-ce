<?php

namespace App\Domains\Documentation\Controllers;

use App\Domains\Collections\Models\Collection;
use App\Domains\Documentation\Models\CollectionDocumentation;
use App\Domains\Documentation\Models\RequestResponseExample;
use App\Domains\Environments\Models\Environment;
use App\Domains\Requests\Models\Request as ApiRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class DocumentationController extends Controller
{
    public function index(HttpRequest $request)
    {
        $team = $request->user()->currentTeam;

        $collections = Collection::where('team_id', $team->id)
            ->with(['folders', 'requests', 'documentation'])
            ->get();

        $doc = null;
        $requestsWithExamples = [];

        if ($request->has('collection_id') && $request->collection_id) {
            $collection = Collection::where('team_id', $team->id)->findOrFail($request->collection_id);

            $doc = CollectionDocumentation::firstOrCreate(
                ['collection_id' => $collection->id],
                [
                    'team_id' => $collection->team_id,
                    'is_public' => false,
                    'public_slug' => Str::slug($collection->name).'-'.Str::lower(Str::random(6)),
                    'version' => '1.0.0',
                ]
            );

            $requestsWithExamples = ApiRequest::where('collection_id', $collection->id)
                ->get()
                ->map(function ($req) {
                    return [
                        'id' => $req->id,
                        'folder_id' => $req->folder_id,
                        'name' => $req->name,
                        'description' => $req->description,
                        'method' => $req->method,
                        'url' => $req->url,
                        'headers' => $req->headers,
                        'query_params' => $req->query_params,
                        'body' => $req->body,
                        'auth' => $req->auth,
                        'examples' => RequestResponseExample::where('request_id', $req->id)->get(),
                    ];
                });
        }

        $environments = Environment::where('team_id', $team->id)
            ->with(['variables' => function ($q) {
                $q->where('enabled', true)->orderBy('key');
            }])
            ->orderBy('name')
            ->get();

        return Inertia::render('Documentation/Dashboard', [
            'collections' => $collections,
            'selectedCollectionId' => $request->collection_id ?: '',
            'documentation' => $doc,
            'requestsList' => $requestsWithExamples,
            'environments' => $environments,
        ]);
    }

    public function saveDoc(HttpRequest $request, Collection $collection)
    {
        $request->validate([
            'public_slug' => [
                'nullable',
                'string',
                Rule::unique('collection_documentations', 'public_slug')->ignore($collection->id, 'collection_id'),
            ],
            'environment_id' => 'nullable|uuid|exists:environments,id',
            'logo' => 'nullable|image|max:5120',
            'favicon' => 'nullable|mimes:ico,png,svg,jpeg,jpg,webp,gif|max:5120',
        ]);

        $doc = CollectionDocumentation::updateOrCreate(
            ['collection_id' => $collection->id],
            [
                'team_id' => $collection->team_id,
                'environment_id' => $request->input('environment_id') ?: null,
                'is_public' => $request->boolean('is_public'),
                'public_slug' => $request->input('public_slug') ?: (Str::slug($collection->name).'-'.Str::lower(Str::random(6))),
                'version' => $request->input('version', '1.0.0'),
                'markdown_intro' => $request->input('markdown_intro'),
                'auth_info' => $request->input('auth_info'),
                'settings' => $request->input('settings', []),
            ]
        );

        $settings = $doc->settings ?? [];

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('documentations/logos', 'public');
            $settings['logo_path'] = $path;
        } elseif ($request->boolean('remove_logo')) {
            if (isset($settings['logo_path'])) {
                Storage::disk('public')->delete($settings['logo_path']);
                unset($settings['logo_path']);
            }
        }

        if ($request->hasFile('favicon')) {
            $path = $request->file('favicon')->store('documentations/favicons', 'public');
            $settings['favicon_path'] = $path;
        } elseif ($request->boolean('remove_favicon')) {
            if (isset($settings['favicon_path'])) {
                Storage::disk('public')->delete($settings['favicon_path']);
                unset($settings['favicon_path']);
            }
        }

        if ($request->hasFile('logo') || $request->hasFile('favicon') || $request->boolean('remove_logo') || $request->boolean('remove_favicon')) {
            $doc->settings = $settings;
            $doc->save();
        }

        // Update individual request descriptions if passed
        if ($request->has('requests_descriptions')) {
            foreach ($request->input('requests_descriptions') as $reqId => $desc) {
                ApiRequest::where('id', $reqId)->update(['description' => $desc]);
            }
        }

        return back();
    }

    public function storeExample(HttpRequest $request, ApiRequest $apiRequest)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'status_code' => 'required|integer|min:100|max:599',
            'body' => 'nullable|string',
        ]);

        $example = RequestResponseExample::create([
            'request_id' => $apiRequest->id,
            'name' => $request->input('name'),
            'status_code' => $request->input('status_code', 200),
            'headers' => $request->input('headers', []),
            'body' => $request->input('body'),
        ]);

        return back();
    }

    public function destroyExample(RequestResponseExample $example)
    {
        $example->delete();

        return back();
    }

    private function getPublicDocsList()
    {
        return CollectionDocumentation::where('is_public', true)
            ->with('collection')
            ->orderBy('updated_at', 'desc')
            ->get()
            ->filter(function ($doc) {
                return $doc->collection !== null && $doc->collection->deleted_at === null;
            })
            ->map(function ($doc) {
                return [
                    'id' => $doc->id,
                    'collection_id' => $doc->collection_id,
                    'public_slug' => $doc->public_slug,
                    'version' => $doc->version,
                    'name' => $doc->collection->name,
                ];
            })
            ->values();
    }

    public function publicIndex(HttpRequest $request)
    {
        $publicDocsList = $this->getPublicDocsList();

        if ($publicDocsList->isEmpty()) {
            return Inertia::render('Documentation/PublicViewer', [
                'documentation' => null,
                'collection' => null,
                'requests' => [],
                'environment' => null,
                'publicDocsList' => [],
            ]);
        }

        $targetCollectionId = $request->query('collection_id') ?: $publicDocsList[0]['collection_id'];

        $doc = CollectionDocumentation::where('collection_id', $targetCollectionId)
            ->where('is_public', true)
            ->first();

        if (! $doc) {
            $doc = CollectionDocumentation::where('collection_id', $publicDocsList[0]['collection_id'])
                ->where('is_public', true)
                ->firstOrFail();
        }

        return $this->renderDoc($doc, $publicDocsList);
    }

    public function viewPublic(string $collectionId, string $slug)
    {
        $doc = CollectionDocumentation::where('collection_id', $collectionId)
            ->where('public_slug', $slug)
            ->where('is_public', true)
            ->firstOrFail();

        return $this->renderDoc($doc, $this->getPublicDocsList());
    }

    private function renderDoc(CollectionDocumentation $doc, $publicDocsList)
    {
        $collection = Collection::where('id', $doc->collection_id)
            ->with(['folders'])
            ->firstOrFail();

        $requests = ApiRequest::where('collection_id', $collection->id)
            ->get()
            ->map(function ($req) {
                return [
                    'id' => $req->id,
                    'folder_id' => $req->folder_id,
                    'name' => $req->name,
                    'description' => $req->description,
                    'method' => $req->method,
                    'url' => $req->url,
                    'headers' => $req->headers,
                    'query_params' => $req->query_params,
                    'body' => $req->body,
                    'auth' => $req->auth,
                    'examples' => RequestResponseExample::where('request_id', $req->id)->get(),
                ];
            });

        $environment = null;
        if ($doc->environment_id) {
            $environment = Environment::where('id', $doc->environment_id)
                ->with(['variables' => function ($q) {
                    $q->where('enabled', true)->orderBy('key');
                }])
                ->first();
        }

        return Inertia::render('Documentation/PublicViewer', [
            'documentation' => $doc,
            'collection' => $collection,
            'requests' => $requests,
            'environment' => $environment,
            'publicDocsList' => $publicDocsList,
        ]);
    }
}
