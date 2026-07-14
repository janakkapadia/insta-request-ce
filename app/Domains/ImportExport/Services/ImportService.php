<?php

namespace App\Domains\ImportExport\Services;

use App\Domains\Collections\Models\Collection;
use App\Domains\Collections\Models\CollectionFolder;
use App\Domains\ImportExport\Contracts\ImportParserInterface;
use App\Domains\ImportExport\DTOs\ImportParseResult;
use App\Domains\ImportExport\DTOs\ParsedFolder;
use App\Domains\ImportExport\DTOs\ParsedRequest;
use App\Domains\ImportExport\Models\Import;
use App\Domains\ImportExport\Parsers\CurlParser;
use App\Domains\ImportExport\Parsers\HarParser;
use App\Domains\ImportExport\Parsers\InsomniaParser;
use App\Domains\ImportExport\Parsers\OpenApiParser;
use App\Domains\ImportExport\Parsers\PostmanV2Parser;
use App\Domains\ImportExport\Parsers\SwaggerParser;
use App\Domains\Requests\Models\Request as ApiRequest;
use App\Enums\ImportFormat;
use App\Enums\ImportStatus;
use App\Enums\MergeStrategy;

class ImportService
{
    /** @var array<ImportParserInterface> */
    private array $parsers;

    public function __construct()
    {
        $this->parsers = [
            new PostmanV2Parser(),
            new CurlParser(),
            new OpenApiParser(),
            new SwaggerParser(),
            new HarParser(),
            new InsomniaParser(),
        ];
    }

    /**
     * Detect the format from file content and name.
     */
    public function detectFormat(string $content, string $filename): ?ImportFormat
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $data = json_decode($content, true);

        if (is_array($data)) {
            // Postman v2
            if (isset($data['info']['schema']) && str_contains($data['info']['schema'], 'postman')) {
                return ImportFormat::PostmanV2;
            }
            if (isset($data['info']['_postman_id'])) {
                return ImportFormat::PostmanV2;
            }

            // OpenAPI 3
            if (isset($data['openapi']) && str_starts_with($data['openapi'], '3')) {
                return ImportFormat::OpenApi3;
            }

            // Swagger 2
            if (isset($data['swagger']) && str_starts_with($data['swagger'], '2')) {
                return ImportFormat::Swagger2;
            }

            // HAR
            if (isset($data['log']['entries']) || $ext === 'har') {
                return ImportFormat::Har;
            }

            // Insomnia
            if (isset($data['_type']) && $data['_type'] === 'export') {
                return ImportFormat::Insomnia;
            }
            if (isset($data['resources'])) {
                return ImportFormat::Insomnia;
            }
        }

        // cURL detection
        if (preg_match('/^\s*curl\s+/im', $content)) {
            return ImportFormat::Curl;
        }

        // Extension-based fallback
        return match ($ext) {
            'har' => ImportFormat::Har,
            'curl', 'sh', 'bash' => ImportFormat::Curl,
            default => null,
        };
    }

    /**
     * Parse content using the appropriate parser.
     */
    public function parseContent(string $content, string $filename, ImportFormat $format): ImportParseResult
    {
        foreach ($this->parsers as $parser) {
            if ($parser->supports($format->value)) {
                return $parser->parse($content, $filename);
            }
        }

        throw new \InvalidArgumentException("No parser found for format: {$format->value}");
    }

    /**
     * Upload and preview — creates import record and returns parsed preview.
     */
    public function uploadAndPreview(
        string $content,
        string $filename,
        string $teamId,
        string $userId,
        ImportFormat $format,
    ): Import {
        $parsed = $this->parseContent($content, $filename, $format);

        $import = Import::create([
            'team_id' => $teamId,
            'user_id' => $userId,
            'source_format' => $format,
            'original_filename' => $filename,
            'file_hash' => hash('sha256', $content),
            'status' => ImportStatus::Previewing,
            'summary' => $parsed->summary(),
            'validation_report' => array_map(fn ($m) => $m->toArray(), $parsed->validationMessages),
            'parsed_data' => $parsed->toArray(),
        ]);

        return $import;
    }

    /**
     * Confirm import — persists parsed data into collections.
     */
    public function confirmImport(Import $import, MergeStrategy $strategy, ?string $targetCollectionId = null, ?string $targetFolderId = null, ?array $selections = null): Import
    {
        $import->update([
            'status' => ImportStatus::Processing,
            'merge_strategy' => $strategy,
            'target_collection_id' => $targetCollectionId,
        ]);

        try {
            $parsed = ImportParseResult::fromArray($import->parsed_data);

            if ($selections !== null) {
                $filteredRequests = [];
                foreach ($parsed->requests as $i => $req) {
                    if (in_array("root:{$i}", $selections)) {
                        $filteredRequests[] = $req;
                    }
                }

                $filteredFolders = $this->filterFolders($parsed->folders, $selections);

                $parsed = new ImportParseResult(
                    collectionName: $parsed->collectionName,
                    collectionDescription: $parsed->collectionDescription,
                    folders: $filteredFolders,
                    requests: $filteredRequests,
                    validationMessages: $parsed->validationMessages
                );
            }

            if ($strategy === MergeStrategy::CreateNew || !$targetCollectionId) {
                $collection = $this->createNewCollection($parsed, $import->team_id);
            } else {
                $collection = Collection::findOrFail($targetCollectionId);
                $this->mergeIntoCollection($parsed, $collection, $strategy, $targetFolderId);
            }

            $import->update([
                'status' => ImportStatus::Completed,
                'target_collection_id' => $collection->id,
            ]);
        } catch (\Exception $e) {
            $import->update([
                'status' => ImportStatus::Failed,
                'error_message' => $e->getMessage(),
            ]);
        }

        return $import->fresh();
    }

    private function filterFolders(array $folders, array $selections, string $prefix = 'folder'): array
    {
        $filtered = [];
        foreach ($folders as $i => $folder) {
            $folderPrefix = "{$prefix}:{$i}";
            
            $folderReqs = [];
            foreach ($folder->requests as $ri => $req) {
                if (in_array("{$folderPrefix}:req:{$ri}", $selections)) {
                    $folderReqs[] = $req;
                }
            }
            
            $childFolders = $this->filterFolders($folder->folders, $selections, $folderPrefix);
            
            if (in_array($folderPrefix, $selections) || !empty($folderReqs) || !empty($childFolders)) {
                $filtered[] = new ParsedFolder(
                    name: $folder->name,
                    description: $folder->description,
                    requests: $folderReqs,
                    folders: $childFolders
                );
            }
        }
        return $filtered;
    }

    private function createNewCollection(ImportParseResult $parsed, string $teamId): Collection
    {
        $collection = Collection::create([
            'team_id' => $teamId,
            'name' => $parsed->collectionName,
            'description' => $parsed->collectionDescription,
        ]);

        // Create root-level requests
        foreach ($parsed->requests as $req) {
            $this->createRequest($req, $collection->id);
        }

        // Create folders and their requests
        foreach ($parsed->folders as $folder) {
            $this->createFolderWithRequests($folder, $collection->id);
        }

        return $collection;
    }

    private function mergeIntoCollection(ImportParseResult $parsed, Collection $collection, MergeStrategy $strategy, ?string $targetFolderId = null): void
    {
        if ($strategy === MergeStrategy::Mirror) {
            $collection->update([
                'name' => $parsed->collectionName,
                'description' => $parsed->collectionDescription,
            ]);
        }

        $collection->load(['requests', 'folders.requests']);

        if ($strategy === MergeStrategy::Mirror) {
            $incomingIndex = [];
            foreach ($parsed->requests as $req) {
                $incomingIndex[$this->makeKey($req->name, $req->method, $req->url)] = true;
            }
            $this->collectIncomingKeysFromFolders($parsed->folders, $incomingIndex);

            foreach ($collection->requests as $req) {
                if (!isset($incomingIndex[$this->makeKey($req->name, $req->method, $req->url)])) {
                    $req->delete();
                }
            }
            foreach ($collection->folders as $folder) {
                foreach ($folder->requests as $req) {
                    if (!isset($incomingIndex[$this->makeKey($req->name, $req->method, $req->url)])) {
                        $req->delete();
                    }
                }
            }
        }

        // Index existing requests
        $existingIndex = [];
        foreach ($collection->requests as $req) {
            if (!$req->trashed()) {
                $existingIndex[$this->makeKey($req->name, $req->method, $req->url)] = $req;
            }
        }
        foreach ($collection->folders as $folder) {
            if (!$folder->trashed()) {
                foreach ($folder->requests as $req) {
                    if (!$req->trashed()) {
                        $existingIndex[$this->makeKey($req->name, $req->method, $req->url)] = $req;
                    }
                }
            }
        }

        // Index existing folders
        $existingFolders = [];
        foreach ($collection->folders as $folder) {
            if (!$folder->trashed()) {
                $key = strtolower(trim($folder->name)) . '::' . ($folder->parent_id ?? 'root');
                $existingFolders[$key] = $folder;
            }
        }

        // Merge root requests
        foreach ($parsed->requests as $parsedReq) {
            $this->mergeRequest($parsedReq, $collection->id, $targetFolderId, $existingIndex, $strategy);
        }

        // Merge folders and their requests
        $this->mergeParsedFolders($parsed->folders, $collection->id, $targetFolderId, $existingIndex, $existingFolders, $strategy);

        if ($strategy === MergeStrategy::Mirror) {
            $this->pruneEmptyFolders($collection->id);
        }
    }

    private function mergeParsedFolders(array $folders, string $collectionId, ?string $parentId, array &$existingIndex, array &$existingFolders, MergeStrategy $strategy): void
    {
        foreach ($folders as $folder) {
            $folderKey = strtolower(trim($folder->name)) . '::' . ($parentId ?? 'root');
            $existingFolder = $existingFolders[$folderKey] ?? null;

            if ($existingFolder) {
                $folderId = $existingFolder->id;
                if ($strategy === MergeStrategy::MergeReplace || $strategy === MergeStrategy::Mirror) {
                    $existingFolder->update([
                        'description' => $folder->description,
                    ]);
                }
            } else {
                $newFolder = CollectionFolder::create([
                    'collection_id' => $collectionId,
                    'parent_id' => $parentId,
                    'name' => $folder->name,
                    'description' => $folder->description,
                ]);
                $folderId = $newFolder->id;
                // Add to index in case of duplicates in import
                $existingFolders[$folderKey] = $newFolder;
            }

            foreach ($folder->requests as $req) {
                $this->mergeRequest($req, $collectionId, $folderId, $existingIndex, $strategy);
            }

            $this->mergeParsedFolders($folder->folders, $collectionId, $folderId, $existingIndex, $existingFolders, $strategy);
        }
    }

    private function mergeRequest(
        ParsedRequest $parsed,
        string $collectionId,
        ?string $folderId,
        array $existingIndex,
        MergeStrategy $strategy,
    ): void {
        $key = $this->makeKey($parsed->name, $parsed->method, $parsed->url);

        if (isset($existingIndex[$key])) {
            if ($strategy === MergeStrategy::MergeReplace || $strategy === MergeStrategy::Mirror) {
                $updateData = [
                    'name' => $parsed->name,
                    'url' => $parsed->url,
                    'description' => $parsed->description,
                    'headers' => $parsed->headers,
                    'query_params' => $parsed->queryParams,
                    'body' => $parsed->body,
                    'auth' => $parsed->auth,
                ];
                if ($strategy === MergeStrategy::Mirror) {
                    $updateData['folder_id'] = $folderId;
                }
                $existingIndex[$key]->update($updateData);
            }
            // MergeSkip: do nothing
            return;
        }

        $this->createRequest($parsed, $collectionId, $folderId);
    }

    private function createRequest(ParsedRequest $parsed, string $collectionId, ?string $folderId = null): void
    {
        ApiRequest::create([
            'collection_id' => $collectionId,
            'folder_id' => $folderId,
            'name' => $parsed->name,
            'description' => $parsed->description,
            'method' => $parsed->method,
            'url' => $parsed->url,
            'headers' => $parsed->headers,
            'query_params' => $parsed->queryParams,
            'body' => $parsed->body ?: ['text' => ''],
            'auth' => $parsed->auth,
        ]);
    }

    private function createFolderWithRequests(ParsedFolder $folder, string $collectionId, ?string $parentId = null): void
    {
        $dbFolder = CollectionFolder::create([
            'collection_id' => $collectionId,
            'parent_id' => $parentId,
            'name' => $folder->name,
            'description' => $folder->description,
        ]);

        foreach ($folder->requests as $req) {
            $this->createRequest($req, $collectionId, $dbFolder->id);
        }

        foreach ($folder->folders as $childFolder) {
            $this->createFolderWithRequests($childFolder, $collectionId, $dbFolder->id);
        }
    }

    private function collectIncomingKeysFromFolders(array $folders, array &$incomingIndex): void
    {
        foreach ($folders as $folder) {
            foreach ($folder->requests as $req) {
                $incomingIndex[$this->makeKey($req->name, $req->method, $req->url)] = true;
            }
            $this->collectIncomingKeysFromFolders($folder->folders, $incomingIndex);
        }
    }

    private function pruneEmptyFolders(string $collectionId): void
    {
        do {
            $deletedCount = 0;
            $folders = CollectionFolder::where('collection_id', $collectionId)->get();
            foreach ($folders as $folder) {
                $hasRequests = $folder->requests()->exists();
                $hasChildren = $folder->children()->exists();
                if (!$hasRequests && !$hasChildren) {
                    $folder->delete();
                    $deletedCount++;
                }
            }
        } while ($deletedCount > 0);
    }

    private function makeKey(string $name, string $method, ?string $url = null): string
    {
        return RequestMatcher::makeKey($name, $method, $url);
    }
}
