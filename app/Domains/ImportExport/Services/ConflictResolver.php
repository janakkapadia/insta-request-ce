<?php

namespace App\Domains\ImportExport\Services;

use App\Domains\Collections\Models\Collection;
use App\Domains\ImportExport\DTOs\ConflictItem;
use App\Domains\ImportExport\DTOs\ImportParseResult;
use App\Domains\ImportExport\DTOs\ParsedRequest;
use App\Domains\Requests\Models\Request;

class ConflictResolver
{
    /**
     * Find conflicts between parsed data and an existing collection.
     *
     * @return array<ConflictItem>
     */
    public function findConflicts(ImportParseResult $parsed, Collection $collection): array
    {
        $collection->load(['requests', 'folders.requests']);
        $conflicts = [];

        // Index existing requests by fast lookup key
        $existingIndex = [];
        foreach ($this->getAllExistingRequests($collection) as $req) {
            $key = $this->makeKey($req->name, $req->method, $req->url);
            $existingIndex[$key] = $req;
        }

        foreach ($this->getAllParsedRequests($parsed) as $parsedReq) {
            $conflict = $this->checkConflict($parsedReq, $existingIndex);
            if ($conflict) {
                $conflicts[] = $conflict;
            }
        }

        return $conflicts;
    }

    private function checkConflict(ParsedRequest $parsed, array $existingIndex): ?ConflictItem
    {
        $key = $this->makeKey($parsed->name, $parsed->method, $parsed->url);

        if (! isset($existingIndex[$key])) {
            return null;
        }

        $existing = $existingIndex[$key];

        return new ConflictItem(
            requestName: $parsed->name,
            method: $parsed->method,
            url: $parsed->url,
            existingRequestId: $existing->id,
            incomingData: $parsed->toArray(),
            existingData: [
                'name' => $existing->name,
                'method' => $existing->method,
                'url' => $existing->url,
                'headers' => $existing->headers ?? [],
                'query_params' => $existing->query_params ?? [],
                'body' => $existing->body ?? [],
            ],
        );
    }

    /**
     * Find existing requests in the collection that are not present in the parsed import data (to be deleted/pruned).
     *
     * @return array<ConflictItem>
     */
    public function findDeletions(ImportParseResult $parsed, Collection $collection): array
    {
        $collection->load(['requests', 'folders.requests']);

        // Index incoming requests by fast lookup key
        $incomingIndex = [];
        foreach ($this->getAllParsedRequests($parsed) as $parsedReq) {
            $key = $this->makeKey($parsedReq->name, $parsedReq->method, $parsedReq->url);
            $incomingIndex[$key] = true;
        }

        $deletions = [];
        foreach ($this->getAllExistingRequests($collection) as $existing) {
            $key = $this->makeKey($existing->name, $existing->method, $existing->url);
            if (! isset($incomingIndex[$key])) {
                $deletions[] = new ConflictItem(
                    requestName: $existing->name,
                    method: $existing->method,
                    url: $existing->url,
                    existingRequestId: $existing->id,
                    incomingData: [],
                    existingData: [
                        'name' => $existing->name,
                        'method' => $existing->method,
                        'url' => $existing->url,
                        'headers' => $existing->headers ?? [],
                        'query_params' => $existing->query_params ?? [],
                        'body' => $existing->body ?? [],
                    ],
                );
            }
        }

        return $deletions;
    }

    /**
     * Get all unique existing requests in the collection across root and all folders recursively.
     *
     * @return array<int, Request>
     */
    private function getAllExistingRequests(Collection $collection): array
    {
        $unique = [];
        if ($collection->relationLoaded('requests') || $collection->requests) {
            foreach ($collection->requests as $req) {
                $unique[$req->id] = $req;
            }
        }
        if ($collection->relationLoaded('folders') || $collection->folders) {
            $this->extractFolderRequests($collection->folders, $unique);
        }

        return array_values($unique);
    }

    private function extractFolderRequests($folders, array &$unique): void
    {
        foreach ($folders as $folder) {
            if ($folder->relationLoaded('requests') || $folder->requests) {
                foreach ($folder->requests as $req) {
                    $unique[$req->id] = $req;
                }
            }
            if ($folder->relationLoaded('folders') || $folder->folders) {
                $this->extractFolderRequests($folder->folders, $unique);
            }
        }
    }

    /**
     * Get all parsed requests across root and all folders recursively.
     *
     * @return array<int, ParsedRequest>
     */
    private function getAllParsedRequests(ImportParseResult $parsed): array
    {
        $all = [];
        foreach ($parsed->requests as $req) {
            $all[] = $req;
        }
        if ($parsed->folders) {
            $this->extractParsedFolderRequests($parsed->folders, $all);
        }

        return $all;
    }

    private function extractParsedFolderRequests(array $folders, array &$all): void
    {
        foreach ($folders as $folder) {
            foreach ($folder->requests as $req) {
                $all[] = $req;
            }
            if (! empty($folder->folders)) {
                $this->extractParsedFolderRequests($folder->folders, $all);
            }
        }
    }

    private function makeKey(string $name, string $method, ?string $url = null): string
    {
        return RequestMatcher::makeKey($name, $method, $url);
    }
}
