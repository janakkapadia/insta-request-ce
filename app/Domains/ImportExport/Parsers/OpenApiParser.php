<?php

namespace App\Domains\ImportExport\Parsers;

use App\Domains\ImportExport\Contracts\ImportParserInterface;
use App\Domains\ImportExport\DTOs\ImportParseResult;
use App\Domains\ImportExport\DTOs\ParsedFolder;
use App\Domains\ImportExport\DTOs\ParsedRequest;
use App\Domains\ImportExport\DTOs\ValidationMessage;

class OpenApiParser implements ImportParserInterface
{
    public function supports(string $format): bool
    {
        return $format === 'openapi_3';
    }

    public function parse(string $content, string $filename): ImportParseResult
    {
        $messages = [];

        $data = $this->decodeContent($content, $messages);
        if ($data === null) {
            return new ImportParseResult(
                pathinfo($filename, PATHINFO_FILENAME),
                null, [], [], $messages,
            );
        }

        $version = $data['openapi'] ?? '';
        if (!str_starts_with($version, '3')) {
            $messages[] = ValidationMessage::warning("OpenAPI version is \"{$version}\", expected 3.x.");
        }

        $info = $data['info'] ?? [];
        $collectionName = $info['title'] ?? pathinfo($filename, PATHINFO_FILENAME);
        $collectionDescription = $info['description'] ?? null;

        $paths = $data['paths'] ?? [];
        $servers = $data['servers'] ?? [];
        $baseUrl = !empty($servers) ? rtrim($servers[0]['url'] ?? '', '/') : '';

        // Group by tags → folders
        $taggedRequests = [];
        $untaggedRequests = [];

        foreach ($paths as $path => $methods) {
            if (!is_array($methods)) {
                continue;
            }

            foreach ($methods as $method => $operation) {
                $httpMethods = ['get', 'post', 'put', 'patch', 'delete', 'options', 'head'];
                if (!in_array(strtolower($method), $httpMethods)) {
                    continue;
                }
                if (!is_array($operation)) {
                    continue;
                }

                $request = $this->parseOperation($path, $method, $operation, $baseUrl, $messages);

                $tags = $operation['tags'] ?? [];
                if (!empty($tags)) {
                    $tag = $tags[0];
                    $taggedRequests[$tag][] = $request;
                } else {
                    $untaggedRequests[] = $request;
                }
            }
        }

        $folders = [];
        foreach ($taggedRequests as $tag => $reqs) {
            $folders[] = new ParsedFolder(
                name: $tag,
                description: null,
                requests: $reqs,
            );
        }

        return new ImportParseResult(
            collectionName: $collectionName,
            collectionDescription: $collectionDescription,
            folders: $folders,
            requests: $untaggedRequests,
            validationMessages: $messages,
        );
    }

    private function parseOperation(string $path, string $method, array $operation, string $baseUrl, array &$messages): ParsedRequest
    {
        $name = $operation['summary'] ?? $operation['operationId'] ?? strtoupper($method) . ' ' . $path;
        $url = $baseUrl . $path;

        // Parse parameters into headers and query params
        $headers = [];
        $queryParams = [];
        foreach ($operation['parameters'] ?? [] as $param) {
            if (!is_array($param)) {
                continue;
            }
            $in = $param['in'] ?? '';
            $defaultValue = isset($param['schema']['default'])
                ? (is_array($param['schema']['default']) ? json_encode($param['schema']['default'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : (string) $param['schema']['default'])
                : '';
            $description = $param['description'] ?? '';
            if ($in === 'header') {
                $headers[] = ['key' => $param['name'] ?? '', 'value' => $defaultValue, 'enabled' => true, 'description' => $description];
            } elseif ($in === 'query') {
                $queryParams[] = ['key' => $param['name'] ?? '', 'value' => $defaultValue, 'enabled' => true, 'description' => $description];
            }
        }

        // Parse request body
        $body = [];
        if (isset($operation['requestBody']['content'])) {
            $contentTypes = $operation['requestBody']['content'];
            // Prefer application/json
            if (isset($contentTypes['application/json'])) {
                $schema = $contentTypes['application/json']['schema'] ?? [];
                $example = $contentTypes['application/json']['example'] ?? $schema['example'] ?? null;
                $sample = $example !== null ? $example : $this->generateSample($schema);
                
                $contentStr = is_string($sample) ? $sample : json_encode($sample, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                
                $body = [
                    'mode' => 'raw',
                    'raw' => [
                        'language' => 'json',
                        'content' => $contentStr,
                    ]
                ];
                // Add content-type header
                $hasContentType = false;
                foreach ($headers as $h) {
                    if (strtolower($h['key']) === 'content-type') {
                        $hasContentType = true;
                        break;
                    }
                }
                if (!$hasContentType) {
                    $headers[] = ['key' => 'Content-Type', 'value' => 'application/json'];
                }
            }
        }

        return new ParsedRequest(
            name: $name,
            method: strtoupper($method),
            url: $url,
            headers: $headers,
            queryParams: $queryParams,
            body: $body,
        );
    }

    /**
     * Generate a simple sample object from a JSON Schema.
     */
    private function generateSample(array $schema): mixed
    {
        $type = $schema['type'] ?? 'object';
        if (isset($schema['example'])) {
            return $schema['example'];
        }

        return match ($type) {
            'object' => $this->generateObjectSample($schema),
            'array' => [$this->generateSample($schema['items'] ?? [])],
            'string' => $schema['enum'][0] ?? 'string',
            'integer', 'number' => 0,
            'boolean' => true,
            default => null,
        };
    }

    private function generateObjectSample(array $schema): array
    {
        $result = [];
        foreach ($schema['properties'] ?? [] as $name => $prop) {
            $result[$name] = $this->generateSample($prop);
        }

        return $result ?: ['key' => 'value'];
    }

    private function decodeContent(string $content, array &$messages): ?array
    {
        // Try JSON first
        $data = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
            return $data;
        }

        // Try YAML if symfony/yaml is available
        if (class_exists(\Symfony\Component\Yaml\Yaml::class)) {
            try {
                $data = \Symfony\Component\Yaml\Yaml::parse($content);
                if (is_array($data)) {
                    return $data;
                }
            } catch (\Exception $e) {
                // Fall through
            }
        }

        $messages[] = ValidationMessage::error('Could not parse file as JSON or YAML.');

        return null;
    }
}
