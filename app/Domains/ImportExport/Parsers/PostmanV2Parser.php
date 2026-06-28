<?php

namespace App\Domains\ImportExport\Parsers;

use App\Domains\ImportExport\Contracts\ImportParserInterface;
use App\Domains\ImportExport\DTOs\ImportParseResult;
use App\Domains\ImportExport\DTOs\ParsedFolder;
use App\Domains\ImportExport\DTOs\ParsedRequest;
use App\Domains\ImportExport\DTOs\ValidationMessage;

class PostmanV2Parser implements ImportParserInterface
{
    public function supports(string $format): bool
    {
        return $format === 'postman_v2';
    }

    public function parse(string $content, string $filename): ImportParseResult
    {
        $data = json_decode($content, true);
        $messages = [];

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new ImportParseResult(
                collectionName: pathinfo($filename, PATHINFO_FILENAME),
                collectionDescription: null,
                folders: [],
                requests: [],
                validationMessages: [ValidationMessage::error('Invalid JSON: ' . json_last_error_msg())],
            );
        }

        $info = $data['info'] ?? [];
        $collectionName = $info['name'] ?? pathinfo($filename, PATHINFO_FILENAME);
        $collectionDescription = $info['description'] ?? null;

        // Validate schema version
        $schema = $info['schema'] ?? '';
        if ($schema && !str_contains($schema, 'v2')) {
            $messages[] = ValidationMessage::warning("Postman schema version may not be v2: {$schema}");
        }

        $items = $data['item'] ?? [];
        $folders = [];
        $requests = [];

        foreach ($items as $item) {
            if (isset($item['item'])) {
                // This is a folder
                $folders[] = $this->parseFolder($item, $messages);
            } else {
                // This is a request
                $parsed = $this->parseRequest($item, $messages);
                if ($parsed) {
                    $requests[] = $parsed;
                }
            }
        }

        return new ImportParseResult(
            collectionName: $collectionName,
            collectionDescription: $collectionDescription,
            folders: $folders,
            requests: $requests,
            validationMessages: $messages,
        );
    }

    private function parseFolder(array $item, array &$messages): ParsedFolder
    {
        $folderRequests = [];
        $childFolders = [];
        foreach ($item['item'] ?? [] as $child) {
            if (isset($child['item'])) {
                // Nested folders
                $childFolders[] = $this->parseFolder($child, $messages);
            } else {
                $parsed = $this->parseRequest($child, $messages);
                if ($parsed) {
                    $folderRequests[] = $parsed;
                }
            }
        }

        return new ParsedFolder(
            name: $item['name'] ?? 'Untitled Folder',
            description: $item['description'] ?? null,
            requests: $folderRequests,
            folders: $childFolders,
        );
    }

    private function parseRequest(array $item, array &$messages): ?ParsedRequest
    {
        $req = $item['request'] ?? null;
        if (!$req) {
            $messages[] = ValidationMessage::warning("Item \"{$item['name']}\" has no request data.", $item['name'] ?? '');

            return null;
        }

        // Handle request being a string (simple URL)
        if (is_string($req)) {
            return new ParsedRequest(
                name: $item['name'] ?? 'Untitled',
                method: 'GET',
                url: $req,
            );
        }

        $method = is_string($req['method'] ?? null) ? strtoupper($req['method']) : 'GET';

        // Parse URL
        $url = '';
        if (is_string($req['url'] ?? null)) {
            $url = $req['url'];
        } elseif (is_array($req['url'] ?? null)) {
            $url = $req['url']['raw'] ?? '';
        }

        // Parse headers
        $headers = [];
        foreach ($req['header'] ?? [] as $header) {
            if (is_array($header) && isset($header['key'])) {
                $headers[] = [
                    'key' => $header['key'],
                    'value' => $header['value'] ?? '',
                ];
            }
        }

        // Parse query params from URL object
        $queryParams = [];
        if (is_array($req['url'] ?? null) && isset($req['url']['query'])) {
            foreach ($req['url']['query'] as $param) {
                if (is_array($param) && isset($param['key'])) {
                    $queryParams[] = [
                        'key' => $param['key'],
                        'value' => $param['value'] ?? '',
                    ];
                }
            }
        }

        // Parse body
        $body = [];
        if (isset($req['body'])) {
            $bodyData = $req['body'];
            $mode = $bodyData['mode'] ?? 'raw';
            $body = ['mode' => $mode];
            
            if ($mode === 'raw') {
                $language = $bodyData['options']['raw']['language'] ?? 'json';
                $body['raw'] = [
                    'language' => $language,
                    'content' => is_string($bodyData['raw'] ?? '') ? $bodyData['raw'] : ''
                ];
            } elseif ($mode === 'formdata') {
                $body['formdata'] = $bodyData['formdata'] ?? [];
            } elseif ($mode === 'urlencoded') {
                $body['urlencoded'] = $bodyData['urlencoded'] ?? [];
            } elseif ($mode === 'graphql') {
                $body['graphql'] = $bodyData['graphql'] ?? [];
            }
        }

        // Parse auth
        $auth = [];
        if (isset($req['auth'])) {
            $auth = $req['auth'];
        }

        return new ParsedRequest(
            name: $item['name'] ?? 'Untitled Request',
            method: $method,
            url: $url,
            headers: $headers,
            queryParams: $queryParams,
            body: $body,
            auth: $auth,
        );
    }
}
