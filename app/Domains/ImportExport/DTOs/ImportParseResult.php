<?php

namespace App\Domains\ImportExport\DTOs;

class ImportParseResult
{
    /**
     * @param  array<ParsedFolder>  $folders
     * @param  array<ParsedRequest>  $requests  Root-level requests (not in any folder)
     * @param  array<ValidationMessage>  $validationMessages
     */
    public function __construct(
        public readonly string $collectionName,
        public ?string $collectionDescription,
        public readonly array $folders,
        public readonly array $requests,
        public readonly array $validationMessages = [],
    ) {
        if ($this->collectionDescription !== null) {
            // Strip HTML tags and decode entities for clean plain-text descriptions
            $clean = strip_tags($this->collectionDescription);
            $clean = html_entity_decode($clean, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $clean = preg_replace('/\s+/', ' ', $clean);
            $this->collectionDescription = trim($clean) ?: null;
        }
    }

    public function toArray(): array
    {
        return [
            'collection_name' => $this->collectionName,
            'collection_description' => $this->collectionDescription,
            'folders' => array_map(fn (ParsedFolder $f) => $f->toArray(), $this->folders),
            'requests' => array_map(fn (ParsedRequest $r) => $r->toArray(), $this->requests),
            'validation_messages' => array_map(fn (ValidationMessage $m) => $m->toArray(), $this->validationMessages),
        ];
    }

    public function summary(): array
    {
        $folderCount = count($this->folders);
        $requestCount = count($this->requests);
        foreach ($this->folders as $folder) {
            $requestCount += count($folder->requests);
        }

        return [
            'collections' => 1,
            'folders' => $folderCount,
            'requests' => $requestCount,
            'warnings' => count(array_filter($this->validationMessages, fn (ValidationMessage $m) => $m->level === 'warning')),
            'errors' => count(array_filter($this->validationMessages, fn (ValidationMessage $m) => $m->level === 'error')),
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            collectionName: $data['collection_name'] ?? 'Imported Collection',
            collectionDescription: $data['collection_description'] ?? null,
            folders: array_map(fn (array $f) => ParsedFolder::fromArray($f), $data['folders'] ?? []),
            requests: array_map(fn (array $r) => ParsedRequest::fromArray($r), $data['requests'] ?? []),
            validationMessages: array_map(fn (array $m) => ValidationMessage::fromArray($m), $data['validation_messages'] ?? []),
        );
    }
}
