<?php

namespace App\Domains\ImportExport\DTOs;

class ParsedFolder
{
    /**
     * @param  string  $name
     * @param  string|null  $description
     * @param  array<ParsedRequest>  $requests
     * @param  array<ParsedFolder>  $folders
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $description = null,
        public readonly array $requests = [],
        public readonly array $folders = [],
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'requests' => array_map(fn (ParsedRequest $r) => $r->toArray(), $this->requests),
            'folders' => array_map(fn (ParsedFolder $f) => $f->toArray(), $this->folders),
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? 'Untitled Folder',
            description: $data['description'] ?? null,
            requests: array_map(fn (array $r) => ParsedRequest::fromArray($r), $data['requests'] ?? []),
            folders: array_map(fn (array $f) => ParsedFolder::fromArray($f), $data['folders'] ?? []),
        );
    }
}
