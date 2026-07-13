<?php

namespace App\Domains\Environments\Controllers;

use App\Domains\Environments\Models\Environment;
use App\Domains\Environments\Models\EnvironmentVariable;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

    public function export(Environment $environment)
    {
        if ($environment->team_id !== Auth::user()->current_team_id) {
            abort(403);
        }

        $environment->load('variables');

        $exportData = [
            'id' => $environment->id,
            'name' => $environment->name,
            'values' => $environment->variables->map(function ($v) {
                return [
                    'key' => $v->key,
                    'value' => $v->value,
                    'type' => 'default',
                    'enabled' => (bool) $v->enabled,
                ];
            })->values()->toArray(),
            '_postman_variable_scope' => 'environment',
            '_postman_exported_at' => now()->toIso8601String(),
            '_postman_exported_using' => 'Jackman/1.0.0',
        ];

        $filename = Str::slug($environment->name) . '.postman_environment.json';

        return response()->streamDownload(function () use ($exportData) {
            echo json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }, $filename, [
            'Content-Type' => 'application/json',
        ]);
    }

    public function import(Request $request)
    {
        $team = Auth::user()->currentTeam;
        if (!$team) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'No active team'], 403);
            }
            return back()->withErrors(['error' => 'No active team']);
        }

        $request->validate([
            'file' => 'required_without:content|file|max:10240',
            'content' => 'required_without:file|nullable|string',
        ]);

        $content = $request->hasFile('file')
            ? $request->file('file')->get()
            : $request->input('content');

        $data = json_decode($content, true);

        if (!is_array($data)) {
            $msg = 'Invalid JSON environment format.';
            if ($request->wantsJson()) {
                return response()->json(['error' => $msg], 422);
            }
            return back()->withErrors(['error' => $msg]);
        }

        $name = $data['name'] ?? null;
        if (!$name) {
            if ($request->hasFile('file')) {
                $filename = $request->file('file')->getClientOriginalName();
                $name = pathinfo($filename, PATHINFO_FILENAME);
                $name = preg_replace('/\.postman_environment$/i', '', $name);
            } else {
                $name = 'Imported Environment';
            }
        }

        $variablesList = [];
        if (isset($data['values']) && is_array($data['values'])) {
            $variablesList = $data['values'];
        } elseif (isset($data['variables']) && is_array($data['variables'])) {
            $variablesList = $data['variables'];
        } elseif (isset($data['environment']) && is_array($data['environment'])) {
            $variablesList = $data['environment'];
        } elseif (array_is_list($data)) {
            $variablesList = $data;
        } else {
            foreach ($data as $k => $v) {
                if (is_string($k) && !in_array($k, ['id', 'name', '_postman_variable_scope', '_postman_exported_at', '_postman_exported_using', '_type'])) {
                    $variablesList[] = [
                        'key' => $k,
                        'value' => is_scalar($v) ? (string) $v : json_encode($v),
                        'enabled' => true,
                    ];
                }
            }
        }

        $environment = DB::transaction(function () use ($name, $variablesList, $team) {
            $env = Environment::create([
                'team_id' => $team->id,
                'name' => $name,
            ]);

            foreach ($variablesList as $var) {
                if (!is_array($var)) {
                    continue;
                }
                $key = $var['key'] ?? $var['name'] ?? null;
                if (!$key || !is_string($key) || trim($key) === '') {
                    continue;
                }
                $value = $var['value'] ?? '';
                if (!is_scalar($value)) {
                    $value = json_encode($value);
                }
                $enabled = isset($var['enabled']) ? (bool) $var['enabled'] : true;

                $env->variables()->create([
                    'key' => trim($key),
                    'value' => (string) $value,
                    'enabled' => $enabled,
                ]);
            }

            return $env;
        });

        if ($request->wantsJson()) {
            return response()->json($environment->load('variables'));
        }

        return redirect()->back()->with('flash', [
            'message' => 'Environment imported successfully',
            'environment' => $environment->load('variables'),
        ]);
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
