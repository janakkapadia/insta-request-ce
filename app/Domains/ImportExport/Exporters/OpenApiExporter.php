<?php

namespace App\Domains\ImportExport\Exporters;

use App\Domains\Collections\Models\Collection;
use App\Domains\ImportExport\Contracts\ExportGeneratorInterface;
use App\Domains\ImportExport\DTOs\ExportResult;

class OpenApiExporter implements ExportGeneratorInterface
{
    public function supports(string $format): bool
    {
        return $format === 'openapi_3';
    }

    public function generate(Collection $collection): ExportResult
    {
        $collection->load(['requests', 'folders.requests']);

        $paths = [];
        $tags = [];

        // Folder requests — folder name becomes tag
        foreach ($collection->folders as $folder) {
            $tags[] = ['name' => $folder->name, 'description' => $folder->description ?? ''];
            foreach ($folder->requests as $req) {
                $this->addPath($paths, $req, $folder->name);
            }
        }

        // Root-level requests — no tag
        foreach ($collection->requests as $req) {
            if (!$req->folder_id) {
                $this->addPath($paths, $req);
            }
        }

        $output = [
            'openapi' => '3.0.3',
            'info' => [
                'title' => $collection->name,
                'description' => $collection->description ?? '',
                'version' => '1.0.0',
            ],
            'tags' => $tags,
            'paths' => $paths ?: new \stdClass(),
        ];

        $json = json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($collection->name));

        return new ExportResult(
            content: $json,
            filename: "{$slug}.openapi.json",
            mimeType: 'application/json',
        );
    }

    private function addPath(array &$paths, $req, ?string $tag = null): void
    {
        $url = $req->url ?? '';
        $parsed = parse_url($url);
        $path = $parsed['path'] ?? '/' . preg_replace('/[^a-z0-9\/\-_{}]+/i', '', strtolower($req->name));

        if (!$path || $path === '/') {
            $path = '/' . preg_replace('/[^a-z0-9\-]+/', '-', strtolower($req->name));
        }

        $method = strtolower($req->method);

        $operation = [
            'summary' => $req->name,
            'operationId' => preg_replace('/[^a-zA-Z0-9]+/', '_', $req->name),
            'responses' => [
                '200' => ['description' => 'Successful response'],
            ],
        ];

        if ($tag) {
            $operation['tags'] = [$tag];
        }

        // Query params
        $parameters = [];
        foreach ($req->query_params ?? [] as $p) {
            $parameters[] = [
                'name' => $p['key'] ?? '',
                'in' => 'query',
                'schema' => ['type' => 'string'],
            ];
        }
        if (!empty($parameters)) {
            $operation['parameters'] = $parameters;
        }

        // Request body
        $body = $req->body ?? [];
        
        $bodyText = '';
        $contentType = 'application/json';

        if (is_array($body) && isset($body['mode'])) {
            if ($body['mode'] === 'raw' && isset($body['raw'])) {
                $bodyText = is_array($body['raw']) ? ($body['raw']['content'] ?? '') : $body['raw'];
                $language = is_array($body['raw']) ? ($body['raw']['language'] ?? 'json') : 'json';
                if ($language !== 'json') {
                    $contentType = 'text/plain';
                }
            } elseif ($body['mode'] === 'graphql' && isset($body['graphql'])) {
                $bodyText = is_array($body['graphql']) ? ($body['graphql']['query'] ?? '') : '';
                $contentType = 'application/graphql';
            }
        } elseif (is_array($body) && isset($body['text'])) {
            $bodyText = $body['text'];
        }

        if ($bodyText && in_array($method, ['post', 'put', 'patch'])) {
            $decoded = json_decode($bodyText, true);
            $operation['requestBody'] = [
                'content' => [
                    $contentType => [
                        'schema' => ['type' => 'object'],
                        'example' => $decoded ?: $bodyText,
                    ],
                ],
            ];
        }

        if (!isset($paths[$path])) {
            $paths[$path] = [];
        }
        $paths[$path][$method] = $operation;
    }
}
