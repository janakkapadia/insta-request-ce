<?php

namespace App\Domains\ImportExport\DTOs;

class ConflictItem
{
    public function __construct(
        public readonly string $requestName,
        public readonly string $method,
        public readonly string $url,
        public readonly ?string $existingRequestId,
        public readonly array $incomingData,
        public readonly array $existingData,
    ) {}

    public function toArray(): array
    {
        return [
            'request_name' => $this->requestName,
            'method' => $this->method,
            'url' => $this->url,
            'existing_request_id' => $this->existingRequestId,
            'incoming' => $this->incomingData,
            'existing' => $this->existingData,
        ];
    }
}
