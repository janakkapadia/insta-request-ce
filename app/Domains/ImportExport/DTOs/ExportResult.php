<?php

namespace App\Domains\ImportExport\DTOs;

class ExportResult
{
    public function __construct(
        public readonly string $content,
        public readonly string $filename,
        public readonly string $mimeType,
    ) {}
}
