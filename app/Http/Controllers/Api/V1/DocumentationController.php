<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Collections\Models\Collection;
use App\Domains\Documentation\Models\CollectionDocumentation;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DocumentationController extends Controller
{
    /**
     * Publish or update documentation visibility for a collection.
     *
     * PUT /api/v1/collections/{collection}/documentation
     */
    public function update(Request $request, Collection $collection): JsonResponse
    {
        $user = $request->user();

        // Explicit team ownership check (fixes the IDOR gap in the web controller)
        if (! $user->belongsToTeam($collection->team)) {
            return response()->json(['message' => 'This collection does not belong to one of your teams.'], 403);
        }

        $request->validate([
            'is_public' => 'required|boolean',
            'public_slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('collection_documentations', 'public_slug')
                    ->ignore($collection->id, 'collection_id'),
            ],
            'version' => 'nullable|string|max:50',
            'environment_id' => 'nullable|uuid|exists:environments,id',
        ]);

        $slug = $request->input('public_slug')
            ?: Str::slug($collection->name).'-'.Str::lower(Str::random(6));

        $doc = CollectionDocumentation::updateOrCreate(
            ['collection_id' => $collection->id],
            [
                'team_id' => $collection->team_id,
                'is_public' => $request->boolean('is_public'),
                'public_slug' => $slug,
                'version' => $request->input('version', $this->currentVersion($collection) ?? '1.0.0'),
                'environment_id' => $request->input('environment_id') ?: null,
            ]
        );

        return response()->json([
            'collection_id' => $collection->id,
            'is_public' => $doc->is_public,
            'public_slug' => $doc->public_slug,
            'version' => $doc->version,
            'public_url' => $doc->is_public
                ? url("/docs/{$collection->id}/{$doc->public_slug}")
                : null,
        ]);
    }

    private function currentVersion(Collection $collection): ?string
    {
        return $collection->documentation?->version;
    }
}
