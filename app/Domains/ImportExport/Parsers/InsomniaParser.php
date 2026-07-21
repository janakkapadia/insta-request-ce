<?php

namespace App\Domains\ImportExport\Parsers;

use App\Domains\ImportExport\Contracts\ImportParserInterface;
use App\Domains\ImportExport\DTOs\ImportParseResult;
use App\Domains\ImportExport\DTOs\ParsedFolder;
use App\Domains\ImportExport\DTOs\ParsedRequest;
use App\Domains\ImportExport\DTOs\ValidationMessage;
use Symfony\Component\Yaml\Yaml;

class InsomniaParser implements ImportParserInterface
{
    public function supports(string $format): bool
    {
        return $format === 'insomnia';
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

        // Insomnia v4 export format
        $resources = $data['resources'] ?? [];
        if (empty($resources)) {
            $messages[] = ValidationMessage::error('No resources found in Insomnia export.');

            return new ImportParseResult(pathinfo($filename, PATHINFO_FILENAME), null, [], [], $messages);
        }

        // Find workspace
        $workspace = null;
        foreach ($resources as $r) {
            if (($r['_type'] ?? '') === 'workspace') {
                $workspace = $r;
                break;
            }
        }

        $collectionName = $workspace['name'] ?? pathinfo($filename, PATHINFO_FILENAME);
        $collectionDescription = $workspace['description'] ?? null;

        // Index by _id
        $byId = [];
        foreach ($resources as $r) {
            $id = $r['_id'] ?? null;
            if ($id) {
                $byId[$id] = $r;
            }
        }

        // Collect folders and requests
        $folderMap = []; // id => ParsedFolder data
        $requestsByParent = []; // parent_id => [ParsedRequest]

        foreach ($resources as $r) {
            $type = $r['_type'] ?? '';
            if ($type === 'request_group') {
                $folderMap[$r['_id']] = [
                    'name' => $r['name'] ?? 'Untitled Folder',
                    'description' => $r['description'] ?? null,
                    'parent' => $r['parentId'] ?? null,
                ];
            } elseif ($type === 'request') {
                $parsed = $this->parseInsomniaRequest($r, $messages);
                if ($parsed) {
                    $parentId = $r['parentId'] ?? null;
                    $requestsByParent[$parentId][] = $parsed;
                }
            }
        }

        // Build folders recursively
        $workspaceId = $workspace['_id'] ?? null;

        $buildFolders = null;
        $buildFolders = function ($parentId) use (&$buildFolders, &$folderMap, $requestsByParent) {
            $result = [];
            foreach ($folderMap as $id => $data) {
                if (($data['parent'] === $parentId) && empty($data['processed'])) {
                    $folderMap[$id]['processed'] = true;
                    $result[] = new ParsedFolder(
                        name: $data['name'],
                        description: $data['description'],
                        requests: $requestsByParent[$id] ?? [],
                        folders: $buildFolders($id),
                    );
                }
            }

            return $result;
        };

        $folders = $buildFolders($workspaceId);

        // Catch orphaned folders
        foreach ($folderMap as $id => $data) {
            if (empty($data['processed'])) {
                $folderMap[$id]['processed'] = true;
                $folders[] = new ParsedFolder(
                    name: $data['name'],
                    description: $data['description'],
                    requests: $requestsByParent[$id] ?? [],
                    folders: $buildFolders($id),
                );
            }
        }

        // Root-level requests (parented to workspace)
        $rootRequests = [];
        $workspaceId = $workspace['_id'] ?? null;
        if ($workspaceId && isset($requestsByParent[$workspaceId])) {
            $rootRequests = $requestsByParent[$workspaceId];
        }

        return new ImportParseResult(
            collectionName: $collectionName,
            collectionDescription: $collectionDescription,
            folders: $folders,
            requests: $rootRequests,
            validationMessages: $messages,
        );
    }

    private function parseInsomniaRequest(array $r, array &$messages): ?ParsedRequest
    {
        $method = strtoupper($r['method'] ?? 'GET');
        $url = $r['url'] ?? '';

        $headers = [];
        foreach ($r['headers'] ?? [] as $h) {
            if (is_array($h) && isset($h['name'])) {
                $headers[] = ['key' => $h['name'], 'value' => $h['value'] ?? ''];
            }
        }

        $queryParams = [];
        foreach ($r['parameters'] ?? [] as $p) {
            if (is_array($p) && isset($p['name'])) {
                $queryParams[] = ['key' => $p['name'], 'value' => $p['value'] ?? ''];
            }
        }

        $body = [];
        if (isset($r['body'])) {
            $bodyData = $r['body'];
            if (is_array($bodyData) && isset($bodyData['text'])) {
                $body = ['text' => $bodyData['text']];
            } elseif (is_string($bodyData)) {
                $body = ['text' => $bodyData];
            }
        }

        $auth = [];
        if (isset($r['authentication']) && ! empty($r['authentication'])) {
            $auth = $r['authentication'];
        }

        return new ParsedRequest(
            name: $r['name'] ?? 'Untitled Request',
            method: $method, url: $url,
            headers: $headers, queryParams: $queryParams,
            body: $body, auth: $auth,
        );
    }

    private function decodeContent(string $content, array &$messages): ?array
    {
        $data = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
            return $data;
        }

        if (class_exists(Yaml::class)) {
            try {
                $data = Yaml::parse($content);
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
