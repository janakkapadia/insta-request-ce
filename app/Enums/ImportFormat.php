<?php

namespace App\Enums;

enum ImportFormat: string
{
    case PostmanV2 = 'postman_v2';
    case OpenApi3 = 'openapi_3';
    case Swagger2 = 'swagger_2';
    case Curl = 'curl';
    case Har = 'har';
    case Insomnia = 'insomnia';

    public function label(): string
    {
        return match ($this) {
            self::PostmanV2 => 'Postman Collection v2',
            self::OpenApi3 => 'OpenAPI 3.x',
            self::Swagger2 => 'Swagger 2.0',
            self::Curl => 'cURL',
            self::Har => 'HAR',
            self::Insomnia => 'Insomnia',
        };
    }

    public function extensions(): array
    {
        return match ($this) {
            self::PostmanV2 => ['json'],
            self::OpenApi3 => ['json', 'yaml', 'yml'],
            self::Swagger2 => ['json', 'yaml', 'yml'],
            self::Curl => ['txt', 'sh', 'bash', 'curl'],
            self::Har => ['har', 'json'],
            self::Insomnia => ['json', 'yaml', 'yml'],
        };
    }
}
