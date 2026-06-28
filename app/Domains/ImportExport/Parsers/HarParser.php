<?php

namespace App\Domains\ImportExport\Parsers;

use App\Domains\ImportExport\Contracts\ImportParserInterface;
use App\Domains\ImportExport\DTOs\ImportParseResult;
use App\Domains\ImportExport\DTOs\ParsedRequest;
use App\Domains\ImportExport\DTOs\ValidationMessage;

class HarParser implements ImportParserInterface
{
    public function supports(string $format): bool
    {
        return $format === 'har';
    }

    public function parse(string $content, string $filename): ImportParseResult
    {
        $messages = [];
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new ImportParseResult(
                pathinfo($filename, PATHINFO_FILENAME),
                null, [], [],
                [ValidationMessage::error('Invalid JSON: ' . json_last_error_msg())],
            );
        }

        $log = $data['log'] ?? $data;
        $entries = $log['entries'] ?? [];

        if (empty($entries)) {
            $messages[] = ValidationMessage::warning('No entries found in HAR file.');
        }

        $collectionName = pathinfo($filename, PATHINFO_FILENAME) ?: 'HAR Import';
        $requests = [];

        foreach ($entries as $entry) {
            $req = $entry['request'] ?? null;
            if (!$req || !is_array($req)) {
                continue;
            }

            $method = strtoupper($req['method'] ?? 'GET');
            $url = $req['url'] ?? '';

            if ($this->isStaticResource($url)) {
                continue;
            }

            $headers = [];
            foreach ($req['headers'] ?? [] as $header) {
                if (!is_array($header)) continue;
                $key = $header['name'] ?? '';
                if (str_starts_with($key, ':') || strtolower($key) === 'cookie') continue;
                $headers[] = ['key' => $key, 'value' => $header['value'] ?? ''];
            }

            $queryParams = [];
            foreach ($req['queryString'] ?? [] as $param) {
                if (!is_array($param)) continue;
                $queryParams[] = ['key' => $param['name'] ?? '', 'value' => $param['value'] ?? ''];
            }

            $body = [];
            if (isset($req['postData']['text'])) {
                $body = ['text' => $req['postData']['text']];
            }

            $parsedUrl = parse_url($url);
            $path = $parsedUrl['path'] ?? '/';
            $name = $method . ' ' . $path;

            $requests[] = new ParsedRequest(
                name: $name, method: $method, url: $url,
                headers: $headers, queryParams: $queryParams, body: $body,
            );
        }

        $messages[] = ValidationMessage::info(count($requests) . ' API requests extracted from ' . count($entries) . ' total HAR entries.');

        return new ImportParseResult(
            collectionName: $collectionName,
            collectionDescription: 'Imported from HAR file',
            folders: [], requests: $requests,
            validationMessages: $messages,
        );
    }

    private function isStaticResource(string $url): bool
    {
        $exts = ['css','js','png','jpg','jpeg','gif','svg','ico','woff','woff2','ttf','eot','map'];
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), $exts);
    }
}
