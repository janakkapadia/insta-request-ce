<?php

namespace App\Domains\ImportExport\Controllers;

use App\Domains\Collections\Models\Collection;
use App\Domains\ImportExport\Models\Import;
use App\Domains\ImportExport\Services\ConflictResolver;
use App\Domains\ImportExport\Services\ImportService;
use App\Enums\ImportFormat;
use App\Enums\MergeStrategy;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ImportController extends Controller
{
    public function __construct(
        private readonly ImportService $importService,
        private readonly ConflictResolver $conflictResolver,
    ) {}

    /**
     * Upload a file for import and return a preview.
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required_without_all:content,url',
            'content' => 'required_without_all:file,url|nullable|string',
            'url' => 'required_without_all:file,content|nullable|url|max:2048',
            'filename' => 'nullable|string|max:255',
            'format' => 'nullable|string',
        ]);

        $team = $request->user()->currentTeam;
        if (!$team) {
            return back()->withErrors(['error' => 'No active team']);
        }

        // Get content from file upload, URL, or raw text (for cURL paste)
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $content = $file->get();
            $filename = $file->getClientOriginalName();
        } elseif ($request->input('url')) {
            $url = $request->input('url');
            try {
                $response = Http::timeout(15)->get($url);
                if (!$response->successful()) {
                    return back()->withErrors([
                        'error' => 'Failed to fetch URL: HTTP ' . $response->status(),
                    ]);
                }
                $content = $response->body();
                $filename = $request->input('filename', basename(parse_url($url, PHP_URL_PATH)) ?: 'import-from-url.json');
            } catch (\Exception $e) {
                return back()->withErrors([
                    'error' => 'Failed to fetch URL: ' . $e->getMessage(),
                ]);
            }
        } else {
            $content = $request->input('content');
            $filename = $request->input('filename', 'import.txt');
        }

        // Detect or validate format
        $format = null;
        if ($request->input('format')) {
            $format = ImportFormat::tryFrom($request->input('format'));
        }
        if (!$format) {
            $format = $this->importService->detectFormat($content, $filename);
        }
        if (!$format) {
            return back()->withErrors([
                'error' => 'Could not detect import format. Please select a format manually.',
            ]);
        }

        $import = $this->importService->uploadAndPreview(
            content: $content,
            filename: $filename,
            teamId: $team->id,
            userId: $request->user()->id,
            format: $format,
        );

        // Check for conflicts if a target collection is specified
        $conflicts = [];
        if ($request->input('target_collection_id')) {
            $collection = Collection::find($request->input('target_collection_id'));
            if ($collection) {
                $parsed = \App\Domains\ImportExport\DTOs\ImportParseResult::fromArray($import->parsed_data);
                $conflictItems = $this->conflictResolver->findConflicts($parsed, $collection);
                $conflicts = array_map(fn ($c) => $c->toArray(), $conflictItems);
            }
        }

        return back()->with('flash', [
            'import' => $import,
            'preview' => $import->parsed_data,
            'conflicts' => $conflicts,
        ]);
    }

    /**
     * Get the preview for a pending import.
     */
    public function preview(Request $request, Import $import)
    {
        $team = $request->user()->currentTeam;
        if ($import->team_id !== $team->id) {
            abort(403);
        }

        // Check for conflicts against a target collection
        $conflicts = [];
        $targetId = $request->query('target_collection_id');
        if ($targetId) {
            $collection = Collection::find($targetId);
            if ($collection && $collection->team_id === $team->id) {
                $parsed = \App\Domains\ImportExport\DTOs\ImportParseResult::fromArray($import->parsed_data);
                $conflictItems = $this->conflictResolver->findConflicts($parsed, $collection);
                $conflicts = array_map(fn ($c) => $c->toArray(), $conflictItems);
            }
        }

        return back()->with('flash', [
            'import' => $import,
            'preview' => $import->parsed_data,
            'conflicts' => $conflicts,
        ]);
    }

    /**
     * Confirm an import — persist parsed data into collections.
     */
    public function confirm(Request $request, Import $import)
    {
        $team = $request->user()->currentTeam;
        if ($import->team_id !== $team->id) {
            abort(403);
        }

        $validated = $request->validate([
            'merge_strategy' => 'required|string|in:create_new,merge_replace,merge_skip',
            'target_collection_id' => 'nullable|uuid|exists:collections,id',
            'target_folder_id' => 'nullable|uuid|exists:collection_folders,id',
            'selections' => 'nullable|array',
            'selections.*' => 'string',
        ]);

        $strategy = MergeStrategy::from($validated['merge_strategy']);
        $targetId = $validated['target_collection_id'] ?? null;
        $targetFolderId = $validated['target_folder_id'] ?? null;
        $selections = $validated['selections'] ?? null;

        $import = $this->importService->confirmImport($import, $strategy, $targetId, $targetFolderId, $selections);

        return back()->with('flash', [
            'import' => $import,
            'success' => $import->status->value === 'completed',
        ]);
    }
}
