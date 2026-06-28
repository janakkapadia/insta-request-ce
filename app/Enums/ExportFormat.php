<?php

namespace App\Enums;

enum ExportFormat: string
{
    case PostmanV2 = 'postman_v2';
    case OpenApi3 = 'openapi_3';
    case Curl = 'curl';
    case Har = 'har';

    public function label(): string
    {
        return match ($this) {
            self::PostmanV2 => 'Postman Collection v2',
            self::OpenApi3 => 'OpenAPI 3.0',
            self::Curl => 'cURL',
            self::Har => 'HAR',
        };
    }

    public function mimeType(): string
    {
        return match ($this) {
            self::PostmanV2 => 'application/json',
            self::OpenApi3 => 'application/json',
            self::Curl => 'text/plain',
            self::Har => 'application/json',
        };
    }

    public function fileExtension(): string
    {
        return match ($this) {
            self::PostmanV2 => 'json',
            self::OpenApi3 => 'json',
            self::Curl => 'txt',
            self::Har => 'har',
        };
    }
}
