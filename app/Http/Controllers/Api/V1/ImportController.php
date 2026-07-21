<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Collections\Models\Collection;
use App\Domains\ImportExport\Services\ImportService;
use App\Domains\Teams\Models\Team;
use App\Enums\ImportFormat;
use App\Enums\ImportStatus;
use App\Enums\MergeStrategy;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;

class ImportController extends Controller
{
    public function __construct(private readonly ImportService $importService) {}

    /**
     * One-shot import: upload + confirm in a single API call.
     *
     * POST /api/v1/imports
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required_without_all:content,url|nullable|file',
            'content' => 'required_without_all:file,url|nullable|string',
            'url' => 'required_without_all:file,content|nullable|url|max:2048',
            'filename' => 'nullable|string|max:255',
            'format' => 'nullable|string',
            'merge_strategy' => ['required', Rule::in(array_column(MergeStrategy::cases(), 'value'))],
            'collection_id' => 'required_if:merge_strategy,merge_replace,merge_skip,mirror|nullable|uuid|exists:collections,id',
            'team_id' => 'nullable|uuid|exists:teams,id',
            'target_folder_id' => 'nullable|uuid|exists:collection_folders,id',
        ]);

        $user = $request->user();
        $strategy = MergeStrategy::from($request->input('merge_strategy'));

        // ── Resolve & authorise the team ─────────────────────────────────────
        $team = $this->resolveTeam($request, $user);

        if (! $team) {
            return response()->json([
                'message' => 'Could not determine target team. Provide team_id or a collection_id that belongs to one of your teams.',
            ], 422);
        }

        if (! $user->belongsToTeam($team)) {
            return response()->json(['message' => 'You are not a member of the specified team.'], 403);
        }

        // ── If collection_id supplied, verify team ownership ──────────────────
        if ($request->filled('collection_id')) {
            $collection = Collection::find($request->input('collection_id'));
            if (! $collection || $collection->team_id !== $team->id) {
                return response()->json(['message' => 'Collection not found or does not belong to the specified team.'], 403);
            }
        }

        // ── Resolve import content ────────────────────────────────────────────
        [$content, $filename] = $this->resolveContent($request);

        if ($content === null) {
            return response()->json(['message' => 'Could not fetch content from the provided URL.'], 422);
        }

        // ── Detect / validate format ──────────────────────────────────────────
        $format = ImportFormat::tryFrom($request->input('format', ''));
        if (! $format) {
            $format = $this->importService->detectFormat($content, $filename);
        }

        if (! $format) {
            return response()->json([
                'message' => 'Could not detect import format. Supported formats: openapi3, swagger2, postman_v2, curl, har, insomnia.',
            ], 422);
        }

        // ── Upload & preview (parse + create Import record) ───────────────────
        try {
            $import = $this->importService->uploadAndPreview(
                content: $content,
                filename: $filename,
                teamId: $team->id,
                userId: $user->id,
                format: $format,
            );
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Failed to parse the provided file.',
                'error' => $e->getMessage(),
            ], 422);
        }

        // ── Confirm import ────────────────────────────────────────────────────
        $import = $this->importService->confirmImport(
            import: $import,
            strategy: $strategy,
            targetCollectionId: $request->input('collection_id'),
            targetFolderId: $request->input('target_folder_id'),
        );

        // Surface failures as non-2xx — this is the fix for the silent-failure trap
        if ($import->status === ImportStatus::Failed) {
            return response()->json([
                'status' => 'failed',
                'import_id' => $import->id,
                'error' => $import->error_message,
            ], 422);
        }

        // ── Success ───────────────────────────────────────────────────────────
        $collection = Collection::find($import->target_collection_id);

        return response()->json([
            'status' => 'completed',
            'import_id' => $import->id,
            'collection_id' => $import->target_collection_id,
            'collection_name' => $collection?->name,
            'format' => $import->source_format->value,
            'summary' => $import->summary,
        ], 201);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function resolveTeam(Request $request, $user): ?Team
    {
        // If collection_id is provided, derive team from the collection
        if ($request->filled('collection_id')) {
            $collection = Collection::find($request->input('collection_id'));
            if ($collection) {
                return Team::find($collection->team_id);
            }
        }

        // Explicit team_id
        if ($request->filled('team_id')) {
            return Team::find($request->input('team_id'));
        }

        // Fall back to the user's current team
        return $user->currentTeam;
    }

    private function resolveContent(Request $request): array
    {
        if ($request->hasFile('file')) {
            $file = $request->file('file');

            return [$file->get(), $file->getClientOriginalName()];
        }

        if ($request->filled('url')) {
            $url = $request->input('url');
            try {
                $response = Http::timeout(15)->get($url);
                if (! $response->successful()) {
                    return [null, ''];
                }
                $filename = $request->input('filename') ?: (basename(parse_url($url, PHP_URL_PATH)) ?: 'import-from-url.json');

                return [$response->body(), $filename];
            } catch (\Exception) {
                return [null, ''];
            }
        }

        $filename = $request->input('filename', 'import.txt');

        return [$request->input('content'), $filename];
    }
}
