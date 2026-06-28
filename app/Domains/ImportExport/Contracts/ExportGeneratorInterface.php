<?php

namespace App\Domains\ImportExport\Contracts;

use App\Domains\Collections\Models\Collection;
use App\Domains\ImportExport\DTOs\ExportResult;

interface ExportGeneratorInterface
{
    public function supports(string $format): bool;

    public function generate(Collection $collection): ExportResult;
}
