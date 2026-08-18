<?php

namespace Tests\Feature;

use App\Domains\ImportExport\Parsers\OpenApiParser;
use PHPUnit\Framework\TestCase;

class OpenApiParserTest extends TestCase
{
    public function test_parses_openapi_with_array_default_parameters()
    {
        $parser = new OpenApiParser;
        $openapi = <<<'JSON'
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

    public function test_parses_operation_description()
    {
        $parser = new OpenApiParser;
        $openapi = <<<'JSON'
{
  "openapi": "3.0.0",
  "info": { "title": "Test", "version": "1.0" },
  "paths": {
    "/users": {
      "get": {
        "summary": "List users",
        "description": "Returns a paginated list of all active users.",
        "responses": { "200": { "description": "OK" } }
      }
    }
  }
}
JSON;

        $result = $parser->parse($openapi, 'test.json');

        $this->assertCount(1, $result->requests);
        $this->assertEquals('Returns a paginated list of all active users.', $result->requests[0]->description);
    }

    public function test_falls_back_to_summary_when_no_description()
    {
        $parser = new OpenApiParser;
        $openapi = <<<'JSON'
{
  "openapi": "3.0.0",
  "info": { "title": "Test", "version": "1.0" },
  "paths": {
    "/users": {
      "get": {
        "summary": "List users",
        "responses": { "200": { "description": "OK" } }
      }
    }
  }
}
JSON;

        $result = $parser->parse($openapi, 'test.json');

        $this->assertCount(1, $result->requests);
        $this->assertEquals('List users', $result->requests[0]->description);
    }

    public function test_parses_tag_descriptions()
    {
        $parser = new OpenApiParser;
        $openapi = <<<'JSON'
{
  "openapi": "3.0.0",
  "info": { "title": "Test", "version": "1.0" },
  "tags": [
    { "name": "Users", "description": "User management endpoints" }
  ],
  "paths": {
    "/users": {
      "get": {
        "tags": ["Users"],
        "summary": "List users",
        "responses": { "200": { "description": "OK" } }
      }
    }
  }
}
JSON;

        $result = $parser->parse($openapi, 'test.json');

        $this->assertCount(1, $result->folders);
        $this->assertEquals('Users', $result->folders[0]->name);
        $this->assertEquals('User management endpoints', $result->folders[0]->description);
    }

    public function test_parses_response_examples()
    {
        $parser = new OpenApiParser;
        $openapi = <<<'JSON'
{
  "openapi": "3.0.0",
  "info": { "title": "Test", "version": "1.0" },
  "paths": {
    "/users": {
      "get": {
        "summary": "List users",
        "responses": {
          "200": {
            "description": "Success",
            "content": {
              "application/json": {
                "example": { "id": 1, "name": "Alice" }
              }
            }
          }
        }
      }
    }
  }
}
JSON;

        $result = $parser->parse($openapi, 'test.json');

        $this->assertCount(1, $result->requests);
        $this->assertCount(1, $result->requests[0]->examples);

        $example = $result->requests[0]->examples[0];
        $this->assertEquals('Success', $example['name']);
        $this->assertEquals(200, $example['status_code']);
        $this->assertEquals(['Content-Type' => 'application/json'], $example['headers']);
        $this->assertStringContainsString('"id": 1', $example['body']);
        $this->assertStringContainsString('"name": "Alice"', $example['body']);
    }
}
