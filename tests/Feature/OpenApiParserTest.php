<?php

namespace Tests\Feature;

use App\Domains\ImportExport\Parsers\OpenApiParser;
use PHPUnit\Framework\TestCase;

class OpenApiParserTest extends TestCase
{
    public function test_parses_openapi_with_array_default_parameters()
    {
        $parser = new OpenApiParser();
        $openapi = <<<JSON
{
  "openapi": "3.0.0",
  "info": {
    "title": "Test API",
    "version": "1.0.0"
  },
  "paths": {
    "/users": {
      "get": {
        "summary": "Get users",
        "parameters": [
          {
            "name": "roles",
            "in": "query",
            "schema": {
              "type": "array",
              "items": {
                "type": "string"
              },
              "default": ["admin", "user"]
            }
          },
          {
            "name": "X-Custom-Header",
            "in": "header",
            "schema": {
              "type": "object",
              "default": {"foo": "bar"}
            }
          }
        ],
        "responses": {
          "200": {
            "description": "Success"
          }
        }
      }
    }
  }
}
JSON;

        $result = $parser->parse($openapi, 'openapi.json');
        
        $this->assertCount(1, $result->requests);
        $request = $result->requests[0];
        
        // Assert query params
        $this->assertCount(1, $request->queryParams);
        $this->assertEquals('roles', $request->queryParams[0]['key']);
        $this->assertEquals('["admin","user"]', $request->queryParams[0]['value']);

        // Assert headers
        $this->assertCount(1, $request->headers);
        $this->assertEquals('X-Custom-Header', $request->headers[0]['key']);
        $this->assertEquals('{"foo":"bar"}', $request->headers[0]['value']);
    }
}
