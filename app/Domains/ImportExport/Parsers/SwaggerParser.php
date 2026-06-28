<?php

namespace App\Domains\ImportExport\Parsers;

use App\Domains\ImportExport\Contracts\ImportParserInterface;
use App\Domains\ImportExport\DTOs\ImportParseResult;
use App\Domains\ImportExport\DTOs\ParsedFolder;
use App\Domains\ImportExport\DTOs\ParsedRequest;
use App\Domains\ImportExport\DTOs\ValidationMessage;

class SwaggerParser implements ImportParserInterface
{
    public function supports(string $format): bool
    {
        return $format === 'swagger_2';
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

        $swagger = $data['swagger'] ?? '';
        if (!str_starts_with($swagger, '2')) {
            $messages[] = ValidationMessage::warning("Swagger version is \"{$swagger}\", expected 2.x.");
        }

        $info = $data['info'] ?? [];
        $collectionName = $info['title'] ?? pathinfo($filename, PATHINFO_FILENAME);
        $collectionDescription = $info['description'] ?? null;

        // Build base URL from host + basePath + schemes
        $scheme = ($data['schemes'] ?? ['https'])[0] ?? 'https';
        $host = $data['host'] ?? 'localhost';
        $basePath = rtrim($data['basePath'] ?? '', '/');
        $baseUrl = "{$scheme}://{$host}{$basePath}";

        $paths = $data['paths'] ?? [];
        $taggedRequests = [];
        $untaggedRequests = [];

        foreach ($paths as $path => $methods) {
            if (!is_array($methods)) {
                continue;
            }

            foreach ($methods as $method => $operation) {
                $httpMethods = ['get', 'post', 'put', 'patch', 'delete', 'options', 'head'];
                if (!in_array(strtolower($method), $httpMethods) || !is_array($operation)) {
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
            $folders[] = new ParsedFolder(name: $tag, requests: $reqs);
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

        $headers = [];
        $queryParams = [];
        $body = [];

        foreach ($operation['parameters'] ?? [] as $param) {
            if (!is_array($param)) {
                continue;
            }
            $in = $param['in'] ?? '';
            // Swagger 2.0: default can be at param level or inside schema
            $defaultValue = isset($param['default']) ? (string) $param['default'] : (isset($param['schema']['default']) ? (string) $param['schema']['default'] : '');
            $description = $param['description'] ?? '';
            if ($in === 'header') {
                $headers[] = ['key' => $param['name'] ?? '', 'value' => $defaultValue, 'enabled' => true, 'description' => $description];
            } elseif ($in === 'query') {
                $queryParams[] = ['key' => $param['name'] ?? '', 'value' => $defaultValue, 'enabled' => true, 'description' => $description];
            } elseif ($in === 'body' && isset($param['schema'])) {
                $body = ['text' => json_encode(['key' => 'value'], JSON_PRETTY_PRINT)];
                $hasContentType = false;
                foreach ($headers as $h) {
                    if (strtolower($h['key']) === 'content-type') {
                        $hasContentType = true;
                        break;
                    }
                }
                if (!$hasContentType) {
                    $consumes = $operation['consumes'] ?? ['application/json'];
                    $headers[] = ['key' => 'Content-Type', 'value' => $consumes[0], 'enabled' => true, 'description' => ''];
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

    private function decodeContent(string $content, array &$messages): ?array
    {
        $data = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
            return $data;
        }

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
