<?php

namespace App\Domains\ImportExport\DTOs;

class ParsedRequest
{
    public function __construct(
        public readonly string $name,
        public readonly string $method = 'GET',
        public readonly string $url = '',
        public readonly array $headers = [],
        public readonly array $queryParams = [],
        public readonly array $body = [],
        public readonly array $auth = [],
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'method' => $this->method,
            'url' => $this->url,
            'headers' => $this->headers,
            'query_params' => $this->queryParams,
            'body' => $this->body,
            'auth' => $this->auth,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? 'Untitled Request',
            method: strtoupper($data['method'] ?? 'GET'),
            url: $data['url'] ?? '',
            headers: $data['headers'] ?? [],
            queryParams: $data['query_params'] ?? [],
            body: $data['body'] ?? [],
            auth: $data['auth'] ?? [],
        );
    }
}
