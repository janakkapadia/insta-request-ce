<?php

namespace App\Domains\ImportExport\DTOs;

class ValidationMessage
{
    public function __construct(
        public readonly string $level,
        public readonly string $message,
        public readonly ?string $path = null,
    ) {}

    public function toArray(): array
    {
        return [
            'level' => $this->level,
            'message' => $this->message,
            'path' => $this->path,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            level: $data['level'] ?? 'info',
            message: $data['message'] ?? '',
            path: $data['path'] ?? null,
        );
    }

    public static function error(string $message, ?string $path = null): self
    {
        return new self('error', $message, $path);
    }

    public static function warning(string $message, ?string $path = null): self
    {
        return new self('warning', $message, $path);
    }

    public static function info(string $message, ?string $path = null): self
    {
        return new self('info', $message, $path);
    }
}
