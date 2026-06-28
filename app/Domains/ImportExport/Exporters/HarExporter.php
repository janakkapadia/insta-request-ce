<?php

namespace App\Domains\ImportExport\Exporters;

use App\Domains\Collections\Models\Collection;
use App\Domains\ImportExport\Contracts\ExportGeneratorInterface;
use App\Domains\ImportExport\DTOs\ExportResult;

class HarExporter implements ExportGeneratorInterface
{
    public function supports(string $format): bool
    {
        return $format === 'har';
    }

    public function generate(Collection $collection): ExportResult
    {
        $collection->load(['requests', 'folders.requests']);
        $entries = [];

        $allRequests = collect();
        foreach ($collection->folders as $folder) {
            $allRequests = $allRequests->merge($folder->requests);
        }
        $allRequests = $allRequests->merge(
            $collection->requests->filter(fn ($r) => !$r->folder_id)
        );

        foreach ($allRequests as $req) {
            $entries[] = $this->buildEntry($req);
        }

        $output = [
            'log' => [
                'version' => '1.2',
                'creator' => [
                    'name' => 'Postman Clone',
                    'version' => '1.0.0',
                ],
                'entries' => $entries,
            ],
        ];

        $json = json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($collection->name));

        return new ExportResult(
            content: $json,
            filename: "{$slug}.har",
            mimeType: 'application/json',
        );
    }

    private function buildEntry($req): array
    {
        $url = $req->url ?? '';
        $headers = array_map(fn ($h) => [
            'name' => $h['key'] ?? '',
            'value' => $h['value'] ?? '',
        ], $req->headers ?? []);

        $queryString = array_map(fn ($p) => [
            'name' => $p['key'] ?? '',
            'value' => $p['value'] ?? '',
        ], $req->query_params ?? []);

        $requestObj = [
            'method' => $req->method,
            'url' => $url,
            'httpVersion' => 'HTTP/1.1',
            'headers' => $headers,
            'queryString' => $queryString,
            'cookies' => [],
            'headersSize' => -1,
            'bodySize' => -1,
        ];

        $body = $req->body ?? [];
        $bodyText = $body['text'] ?? '';
        if ($bodyText) {
            $requestObj['postData'] = [
                'mimeType' => 'application/json',
                'text' => $bodyText,
            ];
        }

        return [
            'startedDateTime' => now()->toIso8601String(),
            'time' => 0,
            'request' => $requestObj,
            'response' => [
                'status' => 0,
                'statusText' => '',
                'httpVersion' => 'HTTP/1.1',
                'headers' => [],
                'cookies' => [],
                'content' => ['size' => 0, 'mimeType' => 'application/json'],
                'redirectURL' => '',
                'headersSize' => -1,
                'bodySize' => -1,
            ],
            'cache' => [],
            'timings' => ['send' => 0, 'wait' => 0, 'receive' => 0],
            'comment' => $req->name,
        ];
    }
}
