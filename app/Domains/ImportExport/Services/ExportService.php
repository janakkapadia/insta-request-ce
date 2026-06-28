<?php

namespace App\Domains\ImportExport\Services;

use App\Domains\Collections\Models\Collection;
use App\Domains\ImportExport\Contracts\ExportGeneratorInterface;
use App\Domains\ImportExport\DTOs\ExportResult;
use App\Domains\ImportExport\Exporters\CurlExporter;
use App\Domains\ImportExport\Exporters\HarExporter;
use App\Domains\ImportExport\Exporters\OpenApiExporter;
use App\Domains\ImportExport\Exporters\PostmanV2Exporter;
use App\Domains\ImportExport\Models\Export;
use App\Enums\ExportFormat;
use App\Enums\ExportStatus;

class ExportService
{
    /** @var array<ExportGeneratorInterface> */
    private array $generators;

    public function __construct()
    {
        $this->generators = [
            new PostmanV2Exporter(),
            new CurlExporter(),
            new OpenApiExporter(),
            new HarExporter(),
        ];
    }

    public function export(
        Collection $collection,
        ExportFormat $format,
        string $teamId,
        string $userId,
    ): Export {
        $export = Export::create([
            'team_id' => $teamId,
            'user_id' => $userId,
            'target_format' => $format,
            'collection_id' => $collection->id,
            'filename' => '',
            'status' => ExportStatus::Processing,
        ]);

        try {
            $result = $this->generateExport($collection, $format);

            // Store the file
            $path = "exports/{$export->id}/{$result->filename}";
            \Illuminate\Support\Facades\Storage::put($path, $result->content);

            $export->update([
                'filename' => $result->filename,
                'file_path' => $path,
                'status' => ExportStatus::Completed,
            ]);
        } catch (\Exception $e) {
            $export->update([
                'status' => ExportStatus::Failed,
                'error_message' => $e->getMessage(),
            ]);
        }

        return $export->fresh();
    }

    public function generateExport(Collection $collection, ExportFormat $format): ExportResult
    {
        foreach ($this->generators as $generator) {
            if ($generator->supports($format->value)) {
                return $generator->generate($collection);
            }
        }

        throw new \InvalidArgumentException("No exporter found for format: {$format->value}");
    }
}
