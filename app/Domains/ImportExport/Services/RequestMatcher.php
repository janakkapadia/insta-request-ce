<?php

namespace App\Domains\ImportExport\Services;

class RequestMatcher
{
    /**
     * Generate a stable merge key for matching existing and incoming requests.
     * Prioritizes HTTP method + normalized path/URL when available.
     * Falls back to name + HTTP method when URL is empty (e.g. cURL/untitled requests).
     */
    public static function makeKey(?string $name, ?string $method, ?string $url = null): string
    {
        $method = strtoupper(trim((string) $method)) ?: 'GET';
        $normalizedUrl = self::normalizeUrl($url);

        if ($normalizedUrl !== '') {
            return $method . '::' . $normalizedUrl;
        }

        return strtolower(trim((string) $name)) . '::' . $method;
    }

    /**
     * Normalize a URL string by stripping query parameters, hash fragments,
     * URL scheme prefixes (`http://`, `https://`), and trailing slashes.
     */
    public static function normalizeUrl(?string $url): string
    {
        if ($url === null || trim($url) === '') {
            return '';
        }

        $url = trim($url);

        // Strip query string and hash fragment
        if (($pos = strpos($url, '?')) !== false) {
            $url = substr($url, 0, $pos);
        }
        if (($pos = strpos($url, '#')) !== false) {
            $url = substr($url, 0, $pos);
        }

        // Strip scheme (e.g. http://, https://, ws://, wss://, custom://, or //)
        $url = preg_replace('/^[a-z0-9+\.-]+:\/\//i', '', $url);
        $url = preg_replace('/^\/\//', '', $url);

        // Trim trailing slashes (unless the URL itself is just slashes after scheme removal)
        $rtrimmed = rtrim($url, '/');
        if ($rtrimmed === '' && $url !== '') {
            $url = '/';
        } else {
            $url = $rtrimmed;
        }

        return strtolower($url);
    }
}
