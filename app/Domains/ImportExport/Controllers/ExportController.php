<?php

namespace App\Domains\ImportExport\Controllers;

use App\Domains\Collections\Models\Collection;
use App\Domains\ImportExport\Models\Export;
use App\Domains\ImportExport\Services\ExportService;
use App\Enums\ExportFormat;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ExportController extends Controller
{
    public function __construct(
        private readonly ExportService $exportService,
    ) {}

    /**
     * Create an export of a collection in the specified format.
     */
    public function export(Request $request)
    {
        $validated = $request->validate([
            'collection_id' => 'required|uuid|exists:collections,id',
            'format' => 'required|string|in:postman_v2,openapi_3,curl,har',
        ]);

        $team = $request->user()->currentTeam;
        if (! $team) {
            return back()->withErrors(['error' => 'No active team']);
        }

        $collection = Collection::findOrFail($validated['collection_id']);
        if ($collection->team_id !== $team->id) {
            abort(403);
        }

        $format = ExportFormat::from($validated['format']);

        $export = $this->exportService->export(
            collection: $collection,
            format: $format,
            teamId: $team->id,
            userId: $request->user()->id,
        );

        if ($export->status->value === 'failed') {
            return back()->withErrors([
                'error' => $export->error_message ?? 'Export failed.',
            ]);
        }

        return Inertia::location(route('export.download', $export->id));
    }

    /**
     * Download the generated export file.
     */
    public function download(Request $request, Export $export)
    {
        $team = $request->user()->currentTeam;
        if ($export->team_id !== $team->id) {
            abort(403);
        }

        if (! $export->file_path || ! Storage::exists($export->file_path)) {
            abort(404, 'Export file not found.');
        }

        return Storage::download(
            $export->file_path,
            $export->filename,
            ['Content-Type' => $export->target_format->mimeType()],
        );
    }
}
