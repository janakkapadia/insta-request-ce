<?php

namespace App\Domains\Environments\Controllers;

use App\Domains\Environments\Models\Environment;
use App\Domains\Environments\Models\EnvironmentVariable;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EnvironmentsController extends Controller
{
    public function index()
    {
        $team = Auth::user()->currentTeam;
        $environments = Environment::where('team_id', $team->id)
            ->with('variables')
            ->orderBy('name')
            ->get();

        return inertia('Environments/Index', [
            'environments' => $environments,
        ]);
    }

    public function apiList()
    {
        $team = Auth::user()->currentTeam;
        $environments = Environment::where('team_id', $team->id)
            ->with('variables')
            ->orderBy('name')
            ->get();

        return response()->json($environments);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'variables' => 'nullable|array',
            'variables.*.key' => 'required|string|max:255',
            'variables.*.value' => 'nullable|string',
            'variables.*.enabled' => 'nullable|boolean',
        ]);

        $team = Auth::user()->currentTeam;

        $environment = DB::transaction(function () use ($validated, $team) {
            $environment = Environment::create([
                'team_id' => $team->id,
                'name' => $validated['name'],
            ]);

            if (!empty($validated['variables'])) {
                foreach ($validated['variables'] as $var) {
                    $environment->variables()->create([
                        'key' => $var['key'],
                        'value' => $var['value'] ?? '',
                        'enabled' => $var['enabled'] ?? true,
                    ]);
                }
            }

            return $environment;
        });

        if ($request->wantsJson()) {
            return response()->json($environment->load('variables'));
        }

        return redirect()->back();
    }

    public function update(Request $request, Environment $environment)
    {
        // Ensure user is authorized to edit the team's environments
        if ($environment->team_id !== Auth::user()->current_team_id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'variables' => 'nullable|array',
            'variables.*.id' => 'nullable|uuid',
            'variables.*.key' => 'required|string|max:255',
            'variables.*.value' => 'nullable|string',
            'variables.*.enabled' => 'nullable|boolean',
        ]);

        DB::transaction(function () use ($validated, $environment) {
            $environment->update([
                'name' => $validated['name'],
            ]);

            $existingIds = [];
            if (!empty($validated['variables'])) {
                foreach ($validated['variables'] as $var) {
                    if (!empty($var['id'])) {
                        $existingIds[] = $var['id'];
                        EnvironmentVariable::where('id', $var['id'])
                            ->where('environment_id', $environment->id)
                            ->update([
                                'key' => $var['key'],
                                'value' => $var['value'] ?? '',
                                'enabled' => $var['enabled'] ?? true,
                            ]);
                    } else {
                        $newVar = $environment->variables()->create([
                            'key' => $var['key'],
                            'value' => $var['value'] ?? '',
                            'enabled' => $var['enabled'] ?? true,
                        ]);
                        $existingIds[] = $newVar->id;
                    }
                }
            }

            // Delete variables that are no longer present in the updated list
            $environment->variables()->whereNotIn('id', $existingIds)->delete();
        });

        if ($request->wantsJson()) {
            return response()->json($environment->load('variables'));
        }

        return redirect()->back();
    }

    public function destroy(Request $request, Environment $environment)
    {
        if ($environment->team_id !== Auth::user()->current_team_id) {
            abort(403);
        }

        $environment->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Environment deleted']);
        }

        return redirect()->back();
    }

    public function replaceValue(Request $request, Environment $environment)
    {
        if ($environment->team_id !== Auth::user()->current_team_id) {
            abort(403);
        }

        $validated = $request->validate([
            'key' => 'required|string',
            'value' => 'required|string',
            'collection_id' => 'required|uuid|exists:collections,id',
        ]);

        $key = $validated['key'];
        $value = $validated['value'];
        $collectionId = $validated['collection_id'];

        $team = Auth::user()->currentTeam;
        
        $collection = \App\Domains\Collections\Models\Collection::where('team_id', $team->id)
            ->findOrFail($collectionId);

        $replacement = '{' . $key . '}';

        $requests = \App\Domains\Requests\Models\Request::where('collection_id', $collection->id)->get();

        foreach ($requests as $apiRequest) {
            $changed = false;

            if (!empty($apiRequest->url) && str_contains($apiRequest->url, $value)) {
                $apiRequest->url = str_replace($value, $replacement, $apiRequest->url);
                $changed = true;
            }

            $headers = $apiRequest->headers;
            if ($this->replaceInArray($headers, $value, $replacement)) {
                $apiRequest->headers = $headers;
                $changed = true;
            }

            $queryParams = $apiRequest->query_params;
            if ($this->replaceInArray($queryParams, $value, $replacement)) {
                $apiRequest->query_params = $queryParams;
                $changed = true;
            }

            $pathVariables = $apiRequest->path_variables;
            if ($this->replaceInArray($pathVariables, $value, $replacement)) {
                $apiRequest->path_variables = $pathVariables;
                $changed = true;
            }

            $auth = $apiRequest->auth;
            if ($this->replaceInArray($auth, $value, $replacement)) {
                $apiRequest->auth = $auth;
                $changed = true;
            }

            $body = $apiRequest->body;
            if (is_string($body)) {
                if (str_contains($body, $value)) {
                    $apiRequest->body = str_replace($value, $replacement, $body);
                    $changed = true;
                }
            } elseif (is_array($body)) {
                if ($this->replaceInArray($body, $value, $replacement)) {
                    $apiRequest->body = $body;
                    $changed = true;
                }
            }

            if ($changed) {
                $apiRequest->save();
            }
        }

        return redirect()->back();
    }

    private function replaceInArray(&$array, $searchValue, $replaceValue)
    {
        $changed = false;
        if (!is_array($array)) return false;
        
        foreach ($array as $key => &$val) {
            if (is_array($val)) {
                if ($this->replaceInArray($val, $searchValue, $replaceValue)) {
                    $changed = true;
                }
            } elseif (is_string($val)) {
                if (str_contains($val, $searchValue)) {
                    $val = str_replace($searchValue, $replaceValue, $val);
                    $changed = true;
                }
            }
        }
        return $changed;
    }
}
