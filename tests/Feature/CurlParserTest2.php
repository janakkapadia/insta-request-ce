<?php

namespace Tests\Feature;

use App\Domains\ImportExport\Parsers\CurlParser;
use PHPUnit\Framework\TestCase;

class CurlParserTest2 extends TestCase
{
    public function test_parses_bunny_net_curl()
    {
        $parser = new CurlParser();
        $curl = <<<CURL
curl --request POST \
  --url https://api.bunny.net/dnszone \
  --header 'AccessKey: <api-key>' \
  --header 'Content-Type: application/json' \
  --data '
{
  "Domain": "<string>",
  "Records": [
    {
      "Ttl": 123,
      "Value": "<string>",
      "Name": "<string>",
      "Weight": 123,
      "Priority": 123,
      "Flags": 123,
      "Tag": "<string>",
      "Port": 123,
      "PullZoneId": 123,
      "ScriptId": 123,
      "Accelerated": true,
      "GeolocationLatitude": 123,
      "GeolocationLongitude": 123,
      "LatencyZone": "<string>",
      "Disabled": true,
      "EnviromentalVariables": [
        {
          "Name": "<string>",
          "Value": "<string>"
        }
      ],
      "Comment": "<string>",
      "AutoSslIssuance": true
    }
  ]
}
'
CURL;
        
        $result = $parser->parse($curl, 'test.curl');
        
        $this->assertCount(1, $result->requests);
        $this->assertEquals("https://api.bunny.net/dnszone", $result->requests[0]->url);
        echo "BODY IS:\n" . $result->requests[0]->body['text'] . "\n";
    }
}
