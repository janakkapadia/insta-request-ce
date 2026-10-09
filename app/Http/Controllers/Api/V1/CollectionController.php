<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Collections\Models\Collection;
use App\Domains\ImportExport\Services\ExportService;
use App\Domains\Teams\Models\Team;
use App\Enums\ExportFormat;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CollectionController extends Controller
{
    /**
     * List all collections the authenticated user can access.
     *
     * GET /api/v1/collections?team_id={uuid}
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'team_id' => 'nullable|uuid|exists:teams,id',
        ]);

        $user = $request->user();

        // Scope to a specific team when supplied, otherwise return all accessible collections
        if ($request->filled('team_id')) {
            $team = Team::find($request->input('team_id'));

            if (! $user->belongsToTeam($team)) {
                return response()->json(['message' => 'You are not a member of the specified team.'], 403);
            }

            $teamIds = [$team->id];
        } else {
            $teamIds = $user->teams()->pluck('teams.id')->all();
        }

        $collections = Collection::whereIn('team_id', $teamIds)
            ->with('documentation:collection_id,is_public,public_slug,version')
            ->orderBy('name')
            ->get()
            ->map(fn (Collection $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'description' => $c->description,
                'team_id' => $c->team_id,
                'is_public' => (bool) $c->documentation?->is_public,
                'public_slug' => $c->documentation?->public_slug,
                'version' => $c->documentation?->version,
                'public_url' => $c->documentation?->is_public
                    ? url("/docs/{$c->id}/{$c->documentation->public_slug}")
                    : null,
                'openapi_url' => $c->documentation?->is_public
                    ? url("/docs/{$c->id}/{$c->documentation->public_slug}/openapi.json")
                    : null,
            ]);

        return response()->json($collections);
    }

    /**
     * Export collection as OpenAPI 3 JSON.
     *
     * GET /api/v1/collections/{collection}/openapi
     */
    public function openapi(Request $request, Collection $collection): Response|JsonResponse
    {
        $user = $request->user();

        if (! $user->belongsToTeam($collection->team)) {
            return response()->json(['message' => 'This collection does not belong to one of your teams.'], 403);
        }

        $exportResult = app(ExportService::class)->generateExport($collection, ExportFormat::OpenApi3);

        return response($exportResult->content, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'inline; filename="'.$exportResult->filename.'"',
        ]);
    }
}
