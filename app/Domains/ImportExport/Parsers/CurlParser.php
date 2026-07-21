<?php

namespace App\Domains\ImportExport\Parsers;

use App\Domains\ImportExport\Contracts\ImportParserInterface;
use App\Domains\ImportExport\DTOs\ImportParseResult;
use App\Domains\ImportExport\DTOs\ParsedRequest;
use App\Domains\ImportExport\DTOs\ValidationMessage;

class CurlParser implements ImportParserInterface
{
    public function supports(string $format): bool
    {
        return $format === 'curl';
    }

    public function parse(string $content, string $filename): ImportParseResult
    {
        $messages = [];
        $requests = [];
        $collectionName = pathinfo($filename, PATHINFO_FILENAME) ?: 'cURL Import';

        // Remove backslash-newlines
        $normalized = preg_replace('/\\\\\s*\n\s*/', ' ', $content);

        // Find the start of each curl command
        preg_match_all('/^\s*curl\s/im', $normalized, $matches, PREG_OFFSET_CAPTURE);

        if (empty($matches[0])) {
            // Fallback: try finding `curl ` anywhere if not at the start of line
            preg_match_all('/curl\s/i', $normalized, $matches, PREG_OFFSET_CAPTURE);
        }

        if (empty($matches[0])) {
            $messages[] = ValidationMessage::error('No cURL commands found in the input.');

            return new ImportParseResult($collectionName, null, [], [], $messages);
        }

        $offsets = array_column($matches[0], 1);
        $offsets[] = strlen($normalized);

        $index = 1;
        for ($i = 0; $i < count($offsets) - 1; $i++) {
            $start = $offsets[$i];
            $length = $offsets[$i + 1] - $start;
            $curlCommand = substr($normalized, $start, $length);

            $parsed = $this->parseCurlCommand(trim($curlCommand), $index, $messages);
            if ($parsed) {
                $requests[] = $parsed;
                $index++;
            }
        }

        return new ImportParseResult(
            collectionName: $collectionName,
            collectionDescription: 'Imported from cURL commands',
            folders: [],
            requests: $requests,
            validationMessages: $messages,
        );
    }

    private function parseCurlCommand(string $command, int $index, array &$messages): ?ParsedRequest
    {
        $method = 'GET';
        $url = '';
        $headers = [];
        $body = [];

        // Extract method via -X / --request
        if (preg_match('/(?:-X|--request)\s+["\']?(\w+)["\']?/', $command, $m)) {
            $method = strtoupper($m[1]);
        }

        // Extract URL — handle quoted and unquoted
        // First try the URL after curl (ignoring flags)
        if (preg_match('/curl\s+(?:(?:-[A-Za-z]|-{2}[a-z-]+)\s+(?:"[^"]*"|\'[^\']*\'|\S+)\s+)*["\']?(https?:\/\/[^\s"\']+)["\']?/', $command, $m)) {
            $url = $m[1];
        } elseif (preg_match('/["\']?(https?:\/\/[^\s"\']+)["\']?/', $command, $m)) {
            $url = $m[1];
        }

        // Extract headers via -H / --header
        preg_match_all('/(?:-H|--header)\s+["\']([^"\']+)["\']/', $command, $headerMatches);
        foreach ($headerMatches[1] as $headerStr) {
            $parts = explode(':', $headerStr, 2);
            if (count($parts) === 2) {
                $headers[] = [
                    'key' => trim($parts[0]),
                    'value' => trim($parts[1]),
                ];
            }
        }

        // Extract body via -d / --data / --data-raw
        if (preg_match('/(?:-d|--data|--data-raw)\s+(?:(["\'])(.*?)\1|([^\s]+))/s', $command, $m)) {
            $bodyText = ! empty($m[1]) ? $m[2] : ($m[3] ?? '');

            // Try to format it as pretty JSON if it's valid JSON
            $decoded = json_decode($bodyText);
            if (json_last_error() === JSON_ERROR_NONE && (is_object($decoded) || is_array($decoded))) {
                $bodyText = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }

            $body = ['text' => $bodyText];
            // If we have a body but no explicit method, it's POST
            if (! preg_match('/(?:-X|--request)/', $command)) {
                $method = 'POST';
            }
        }

        if (! $url) {
            $messages[] = ValidationMessage::warning("Could not extract URL from cURL command #{$index}.");

            return null;
        }

        // Generate name from URL path
        $parsedUrl = parse_url($url);
        $path = $parsedUrl['path'] ?? '/';
        $name = $method.' '.$path;

        return new ParsedRequest(
            name: $name,
            method: $method,
            url: $url,
            headers: $headers,
            queryParams: [],
            body: $body,
        );
    }
}
