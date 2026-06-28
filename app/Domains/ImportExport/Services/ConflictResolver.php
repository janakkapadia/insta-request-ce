<?php

namespace App\Domains\ImportExport\Services;

use App\Domains\Collections\Models\Collection;
use App\Domains\ImportExport\DTOs\ConflictItem;
use App\Domains\ImportExport\DTOs\ImportParseResult;
use App\Domains\ImportExport\DTOs\ParsedRequest;

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

        // Index existing requests by name+method for fast lookup
        $existingIndex = [];
        foreach ($collection->requests as $req) {
            $key = $this->makeKey($req->name, $req->method);
            $existingIndex[$key] = $req;
        }
        foreach ($collection->folders as $folder) {
            foreach ($folder->requests as $req) {
                $key = $this->makeKey($req->name, $req->method);
                $existingIndex[$key] = $req;
            }
        }

        // Check root-level parsed requests
        foreach ($parsed->requests as $parsedReq) {
            $conflict = $this->checkConflict($parsedReq, $existingIndex);
            if ($conflict) {
                $conflicts[] = $conflict;
            }
        }

        // Check folder-level parsed requests
        foreach ($parsed->folders as $folder) {
            foreach ($folder->requests as $parsedReq) {
                $conflict = $this->checkConflict($parsedReq, $existingIndex);
                if ($conflict) {
                    $conflicts[] = $conflict;
                }
            }
        }

        return $conflicts;
    }

    private function checkConflict(ParsedRequest $parsed, array $existingIndex): ?ConflictItem
    {
        $key = $this->makeKey($parsed->name, $parsed->method);

        if (!isset($existingIndex[$key])) {
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

    private function makeKey(string $name, string $method): string
    {
        return strtolower(trim($name)) . '::' . strtoupper($method);
    }
}
