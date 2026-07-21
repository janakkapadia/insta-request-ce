<?php

namespace App\Domains\ImportExport\Exporters;

use App\Domains\Collections\Models\Collection;
use App\Domains\ImportExport\Contracts\ExportGeneratorInterface;
use App\Domains\ImportExport\DTOs\ExportResult;

class PostmanV2Exporter implements ExportGeneratorInterface
{
    public function supports(string $format): bool
    {
        return $format === 'postman_v2';
    }

    public function generate(Collection $collection): ExportResult
    {
        $collection->load(['requests', 'folders.requests']);

        $items = [];

        // Folders with their requests
        foreach ($collection->folders as $folder) {
            $folderItems = [];
            foreach ($folder->requests as $req) {
                $folderItems[] = $this->buildRequestItem($req);
            }
            $items[] = [
                'name' => $folder->name,
                'description' => $folder->description ?? '',
                'item' => $folderItems,
            ];
        }

        // Root-level requests (no folder)
        foreach ($collection->requests as $req) {
            if (! $req->folder_id) {
                $items[] = $this->buildRequestItem($req);
            }
        }

        $output = [
            'info' => [
                '_postman_id' => $collection->id,
                'name' => $collection->name,
                'description' => $collection->description ?? '',
                'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
            ],
            'item' => $items,
        ];

        $json = json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($collection->name));

        return new ExportResult(
            content: $json,
            filename: "{$slug}.postman_collection.json",
            mimeType: 'application/json',
        );
    }

    private function buildRequestItem($req): array
    {
        $url = $req->url ?? '';
        $parsedUrl = parse_url($url);

        $urlObj = [
            'raw' => $url,
            'protocol' => $parsedUrl['scheme'] ?? 'https',
            'host' => isset($parsedUrl['host']) ? explode('.', $parsedUrl['host']) : [],
            'path' => isset($parsedUrl['path']) ? array_values(array_filter(explode('/', $parsedUrl['path']))) : [],
        ];

        // Add query params
        if (! empty($req->query_params)) {
            $urlObj['query'] = array_map(function ($p) {
                $item = [
                    'key' => $p['key'] ?? '',
                    'value' => $p['value'] ?? '',
                ];
                if (isset($p['enabled']) && ! $p['enabled']) {
                    $item['disabled'] = true;
                }
                if (! empty($p['description'])) {
                    $item['description'] = $p['description'];
                }

                return $item;
            }, $req->query_params);
        }

        $requestObj = [
            'method' => $req->method,
            'header' => array_map(function ($h) {
                $item = [
                    'key' => $h['key'] ?? '',
                    'value' => $h['value'] ?? '',
                    'type' => 'text',
                ];
                if (isset($h['enabled']) && ! $h['enabled']) {
                    $item['disabled'] = true;
                }
                if (! empty($h['description'])) {
                    $item['description'] = $h['description'];
                }

                return $item;
            }, $req->headers ?? []),
            'url' => $urlObj,
        ];

        if (! empty($req->description)) {
            $requestObj['description'] = $req->description;
        }

        // Add body
        $body = $req->body ?? [];
        if (! empty($body)) {
            if (isset($body['mode'])) {
                $postmanBody = ['mode' => $body['mode']];

                if ($body['mode'] === 'raw' && isset($body['raw'])) {
                    $postmanBody['raw'] = is_array($body['raw']) ? ($body['raw']['content'] ?? '') : $body['raw'];
                    if (is_array($body['raw']) && isset($body['raw']['language'])) {
                        $postmanBody['options'] = ['raw' => ['language' => $body['raw']['language']]];
                    }
                } elseif ($body['mode'] === 'formdata' && isset($body['formdata'])) {
                    $postmanBody['formdata'] = $body['formdata'];
                } elseif ($body['mode'] === 'urlencoded' && isset($body['urlencoded'])) {
                    $postmanBody['urlencoded'] = $body['urlencoded'];
                } elseif ($body['mode'] === 'graphql' && isset($body['graphql'])) {
                    $postmanBody['graphql'] = $body['graphql'];
                }

                $requestObj['body'] = $postmanBody;
            } elseif (isset($body['text'])) {
                $requestObj['body'] = [
                    'mode' => 'raw',
                    'raw' => $body['text'],
                    'options' => ['raw' => ['language' => 'json']],
                ];
            }
        }

        // Add auth
        if (! empty($req->auth)) {
            $requestObj['auth'] = $req->auth;
        }

        return [
            'name' => $req->name ?? 'Untitled Request',
            'request' => $requestObj,
            'response' => [],
        ];
    }
}
