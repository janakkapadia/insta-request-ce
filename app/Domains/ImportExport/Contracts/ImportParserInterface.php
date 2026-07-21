<?php

namespace App\Domains\ImportExport\Contracts;

use App\Domains\ImportExport\DTOs\ImportParseResult;

interface ImportParserInterface
{
    /**
     * Check if this parser supports the given format string.
     */
    public function supports(string $format): bool;

    /**
     * Parse the given file content into a normalized ImportParseResult.
     *
     * @param  string  $content  Raw file content
     * @param  string  $filename  Original filename (used for naming and format hints)
     */
    public function parse(string $content, string $filename): ImportParseResult;
}
