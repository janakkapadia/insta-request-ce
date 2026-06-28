<?php

namespace App\Domains\Requests\Controllers;

use App\Domains\History\Models\RequestHistory;
use App\Http\Controllers\Controller;
use App\Domains\Requests\Services\RequestExecutionService;
use Illuminate\Http\Request;
use App\Domains\Requests\Models\Request as ApiRequest;
use Illuminate\Http\JsonResponse;

class RequestExecutionController extends Controller
{
    public function __construct(
        protected RequestExecutionService $executionService
    ) {
    }

    public function resolve(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'request_id' => 'nullable|uuid|exists:requests,id',
            'method' => 'required|string',
            'url' => 'required|string',
            'headers' => 'nullable|array',
            'query_params' => 'nullable|array',
            'path_variables' => 'nullable|array',
            'body' => 'nullable',
            'auth' => 'nullable|array',
            'environment_id' => 'nullable|uuid',
        ]);

        if (!empty($payload['request_id'])) {
            $apiRequest = ApiRequest::find($payload['request_id']);
            $team = $request->user()->currentTeam;
            abort_if(!$team || !$apiRequest || $apiRequest->collection->team_id !== $team->id, 403, 'Unauthorized access to this request');
        }

        $result = $this->executionService->resolve(
            $payload,
            $payload['environment_id'] ?? null
        );

        return response()->json($result);
    }

    public function saveHistory(Request $request): JsonResponse
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'request_id' => 'nullable|uuid|exists:requests,id',
            'method' => 'required|string',
            'url' => 'required|string',
            'status' => 'required|integer',
            'time_ms' => 'required|integer',
            'size_bytes' => 'required|integer',
            'request_payload' => 'required|array',
            'response_meta' => 'required|array',
        ]);

        if ($validator->fails()) {
            \Log::error('saveHistory validation failed', $validator->errors()->toArray());
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $payload = $validator->validated();

        $team = $request->user()->currentTeam;
        if ($team) {
            RequestHistory::create([
                'team_id' => $team->id,
                'user_id' => $request->user()->id,
                'request_id' => $payload['request_id'] ?? null,
                'method' => $payload['method'],
                'url' => $payload['url'],
                'status' => $payload['status'],
                'time_ms' => $payload['time_ms'],
                'size_bytes' => $payload['size_bytes'],
                'request_payload' => $payload['request_payload'],
                'response_meta' => $payload['response_meta'],
            ]);
        }

        return response()->json(['success' => true]);
    }

    public function execute(Request $request): JsonResponse
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'request_id' => 'nullable|uuid|exists:requests,id',
            'method' => 'required|string',
            'url' => 'required|string',
            'headers' => 'nullable|array',
            'query_params' => 'nullable|array',
            'path_variables' => 'nullable|array',
            'body' => 'nullable',
            'auth' => 'nullable|array',
            'environment_id' => 'nullable|uuid',
        ]);

        if ($validator->fails()) {
            \Log::error('execute validation failed', $validator->errors()->toArray());
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $payload = $validator->validated();

        if (!empty($payload['request_id'])) {
            $apiRequest = ApiRequest::find($payload['request_id']);
            $team = $request->user()->currentTeam;
            abort_if(!$team || !$apiRequest || $apiRequest->collection->team_id !== $team->id, 403, 'Unauthorized access to this request');
        }

        $result = $this->executionService->execute(
            $payload,
            $payload['environment_id'] ?? null
        );

        $team = $request->user()->currentTeam;
        if ($team) {
            $finalHeaders = [];
            if (isset($result['request_options']['headers'])) {
                foreach ($result['request_options']['headers'] as $k => $v) {
                    $finalHeaders[] = [
                        'key' => $k,
                        'value' => is_array($v) ? implode(', ', $v) : $v,
                        'enabled' => true,
                    ];
                }
            } else {
                $finalHeaders = $payload['headers'] ?? [];
            }

            RequestHistory::create([
                'team_id' => $team->id,
                'user_id' => $request->user()->id,
                'request_id' => $payload['request_id'] ?? null,
                'method' => $payload['method'],
                'url' => $result['resolved_url'] ?? $payload['url'],
                'status' => $result['status'],
                'time_ms' => $result['time_ms'],
                'size_bytes' => $result['size_bytes'],
                'request_payload' => [
                    'headers' => $finalHeaders,
                    'query_params' => $payload['query_params'] ?? [],
                    'path_variables' => $payload['path_variables'] ?? [],
                    'body' => $payload['body'] ?? null,
                ],
                'response_meta' => [
                    'status_text' => $result['status_text'] ?? '',
                    'headers' => $result['headers'] ?? [],
                    'body' => (isset($result['body']) && json_validate($result['body'])) 
                        ? ((mb_strlen($result['body']) <= 2097152) ? $result['body'] : mb_substr($result['body'], 0, 2097152) . '... (truncated)')
                        : null,
                ],
            ]);
        }

        return response()->json($result);
    }
}
