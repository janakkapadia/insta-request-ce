<?php

namespace Tests\Feature;

use App\Domains\ImportExport\Parsers\CurlParser;
use PHPUnit\Framework\TestCase;

class CurlParserTest extends TestCase
{
    public function test_parses_multiline_curl_with_quotes()
    {
        $parser = new CurlParser;
        $curl = <<<CURL
curl -X POST https://example.com \
  -d '{"Domain": "<string>", "Records": [{"Value": "<string>" }]}'
CURL;

        $result = $parser->parse($curl, 'test.curl');

        $expected = json_encode(json_decode('{"Domain": "<string>", "Records": [{"Value": "<string>" }]}'), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->assertCount(1, $result->requests);
        $this->assertEquals($expected, $result->requests[0]->body['text']);
    }

    public function test_parses_unescaped_multiline_curl()
    {
        $parser = new CurlParser;
        $curl = <<<'CURL'
curl -X POST https://example.com -d '{
  "Domain": "<string>",
  "Records": [
    {
      "Value": "<string>"
    }
  ]
}'
CURL;

        $result = $parser->parse($curl, 'test.curl');

        $expected = json_encode(json_decode('{"Domain": "<string>", "Records": [{"Value": "<string>" }]}'), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->assertCount(1, $result->requests);
        $this->assertEquals($expected, $result->requests[0]->body['text']);
    }
}
