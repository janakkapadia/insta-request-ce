<?php

namespace App\Domains\ImportExport\Exporters;

use App\Domains\Collections\Models\Collection;
use App\Domains\ImportExport\Contracts\ExportGeneratorInterface;
use App\Domains\ImportExport\DTOs\ExportResult;

class CurlExporter implements ExportGeneratorInterface
{
    public function supports(string $format): bool
    {
        return $format === 'curl';
    }

    public function generate(Collection $collection): ExportResult
    {
        $collection->load(['requests', 'folders.requests']);
        $lines = [];
        $lines[] = "# {$collection->name}";
        $lines[] = '# Exported cURL commands';
        $lines[] = '';

        // Folders with requests
        foreach ($collection->folders as $folder) {
            $lines[] = "# --- {$folder->name} ---";
            foreach ($folder->requests as $req) {
                $lines[] = $this->buildCurl($req);
                $lines[] = '';
            }
        }

        // Root-level requests
        foreach ($collection->requests as $req) {
            if (! $req->folder_id) {
                $lines[] = $this->buildCurl($req);
                $lines[] = '';
            }
        }

        $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($collection->name));

        return new ExportResult(
            content: implode("\n", $lines),
            filename: "{$slug}.curl.txt",
            mimeType: 'text/plain',
        );
    }

    private function buildCurl($req): string
    {
        $parts = ['curl'];

        if ($req->method !== 'GET') {
            $parts[] = '-X '.$req->method;
        }

        $url = $req->url ?? '';
        // Append query params
        if (! empty($req->query_params)) {
            $separator = str_contains($url, '?') ? '&' : '?';
            $params = array_map(
                fn ($p) => urlencode($p['key'] ?? '').'='.urlencode($p['value'] ?? ''),
                $req->query_params,
            );
            $url .= $separator.implode('&', $params);
        }
        $parts[] = "'".addcslashes($url, "'")."'";

        // Headers
        foreach ($req->headers ?? [] as $h) {
            $key = $h['key'] ?? '';
            $value = $h['value'] ?? '';
            $parts[] = "-H '".addcslashes("{$key}: {$value}", "'")."'";
        }

        // Body
        $body = $req->body ?? [];
        $bodyText = $body['text'] ?? '';
        if ($bodyText) {
            $parts[] = "-d '".addcslashes($bodyText, "'")."'";
        }

        return implode(" \\\n  ", $parts);
    }
}
