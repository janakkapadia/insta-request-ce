<?php

namespace App\Domains\ImportExport\Exporters;

use App\Domains\Collections\Models\Collection;
use App\Domains\Documentation\Models\RequestResponseExample;
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
        $collection->load([
            'requests.examples',
            'folders.requests.examples',
            'documentation.environment.variables',
        ]);

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
            if (! $req->folder_id) {
                $this->addPath($paths, $req);
            }
        }

        $output = [
            'openapi' => '3.0.3',
            'info' => [
                'title' => $collection->name,
                'description' => $collection->documentation?->markdown_intro ?: ($collection->description ?? ''),
                'version' => $collection->documentation?->version ?: '1.0.0',
            ],
            'tags' => $tags,
            'paths' => $paths ?: new \stdClass,
        ];

        // Servers detection
        $servers = [];
        if ($collection->documentation?->environment) {
            $envVars = $collection->documentation->environment->variables ?? [];
            $serverVar = collect($envVars)->first(function ($v) {
                $k = strtolower(is_array($v) ? ($v['key'] ?? '') : ($v->key ?? ''));
                $val = is_array($v) ? ($v['value'] ?? '') : ($v->value ?? '');
                $enabled = is_array($v) ? ($v['enabled'] ?? true) : ($v->enabled ?? true);

                return $enabled && in_array($k, ['baseurl', 'base_url', 'url', 'host', 'api_url', 'apiurl']) && ! empty($val);
            });
            if ($serverVar) {
                $urlVal = is_array($serverVar) ? $serverVar['value'] : $serverVar->value;
                $servers[] = [
                    'url' => rtrim($urlVal, '/'),
                    'description' => $collection->documentation->environment->name ?? 'Default server',
                ];
            }
        }

        if (empty($servers)) {
            $allRequests = $collection->requests->concat($collection->folders->flatMap->requests);
            foreach ($allRequests as $r) {
                if (! empty($r->url) && preg_match('#^https?://[^/]+#i', $r->url, $matches)) {
                    $servers[] = ['url' => $matches[0]];
                    break;
                }
            }
        }

        if (! empty($servers)) {
            $output['servers'] = $servers;
        }

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
        $urlWithoutEnv = preg_replace('/^\{\{[^}]+\}\}/', '', $url);
        $parsed = parse_url($urlWithoutEnv);
        $path = $parsed['path'] ?? '';

        if (! $path || $path === '/') {
            $path = '/'.preg_replace('/[^a-z0-9\-]+/', '-', strtolower($req->name));
        }

        if (! str_starts_with($path, '/')) {
            $path = '/'.$path;
        }

        $path = preg_replace('/:([a-zA-Z0-9_]+)/', '{$1}', $path);

        $method = strtolower($req->method ?: 'get');

        $operation = [
            'summary' => $req->name,
            'operationId' => preg_replace('/[^a-zA-Z0-9]+/', '_', $req->name),
        ];

        if (! empty($req->description)) {
            $operation['description'] = $req->description;
        }

        if ($tag) {
            $operation['tags'] = [$tag];
        }

        $parameters = [];
        $seenParams = [];

        // Query params
        foreach ($req->query_params ?? [] as $p) {
            $key = $p['key'] ?? '';
            if ($key !== '' && ($p['enabled'] ?? true)) {
                $param = [
                    'name' => $key,
                    'in' => 'query',
                    'schema' => ['type' => 'string'],
                ];
                if (! empty($p['description'])) {
                    $param['description'] = $p['description'];
                }
                if (isset($p['value']) && $p['value'] !== '') {
                    $param['example'] = $p['value'];
                }
                $parameters[] = $param;
                $seenParams['query:'.$key] = true;
            }
        }

        // Path variables
        foreach ($req->path_variables ?? [] as $p) {
            $key = $p['key'] ?? '';
            if ($key !== '' && ($p['enabled'] ?? true)) {
                $param = [
                    'name' => $key,
                    'in' => 'path',
                    'required' => true,
                    'schema' => ['type' => 'string'],
                ];
                if (! empty($p['description'])) {
                    $param['description'] = $p['description'];
                }
                if (isset($p['value']) && $p['value'] !== '') {
                    $param['example'] = $p['value'];
                }
                $parameters[] = $param;
                $seenParams['path:'.$key] = true;
            }
        }

        // Auto-detect template path variables in $path that weren't in path_variables
        if (preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $path, $matches)) {
            foreach ($matches[1] as $pathParam) {
                if (! isset($seenParams['path:'.$pathParam])) {
                    $parameters[] = [
                        'name' => $pathParam,
                        'in' => 'path',
                        'required' => true,
                        'schema' => ['type' => 'string'],
                    ];
                    $seenParams['path:'.$pathParam] = true;
                }
            }
        }

        // Headers (excluding transport headers)
        foreach ($req->headers ?? [] as $h) {
            $key = $h['key'] ?? '';
            if ($key !== '' && ($h['enabled'] ?? true) && ! in_array(strtolower($key), ['content-type', 'accept'])) {
                $parameters[] = [
                    'name' => $key,
                    'in' => 'header',
                    'schema' => ['type' => 'string'],
                ];
            }
        }

        if (! empty($parameters)) {
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
        } elseif (is_string($body)) {
            $bodyText = $body;
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

        // Responses
        $responses = [];
        $examples = $req->relationLoaded('examples') ? $req->examples : null;
        if (! $examples) {
            $examples = RequestResponseExample::where('request_id', $req->id)->get();
        }

        if ($examples && $examples->isNotEmpty()) {
            foreach ($examples as $example) {
                $statusCode = (string) ($example->status_code ?: 200);
                $decoded = $example->body ? json_decode($example->body, true) : null;
                $responses[$statusCode] = [
                    'description' => $example->name ?: "Status {$statusCode} response",
                    'content' => [
                        'application/json' => [
                            'schema' => ['type' => 'object'],
                            'example' => $decoded ?? ($example->body ?? ''),
                        ],
                    ],
                ];
            }
        }

        if (empty($responses)) {
            $responses['200'] = ['description' => 'Successful response'];
        }

        $operation['responses'] = $responses;

        if (! isset($paths[$path])) {
            $paths[$path] = [];
        }
        $paths[$path][$method] = $operation;
    }
}
