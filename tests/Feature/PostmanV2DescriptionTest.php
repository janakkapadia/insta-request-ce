<?php

use App\Models\User;
use App\Enums\TeamRole;
use App\Domains\Teams\Models\Team;
use App\Domains\Collections\Models\Collection;
use App\Domains\ImportExport\Exporters\PostmanV2Exporter;
use App\Domains\ImportExport\Parsers\PostmanV2Parser;
use App\Domains\Requests\Models\Request as ApiRequest;

test('parses postman v2 request descriptions and header metadata', function () {
    $parser = new PostmanV2Parser();
    $json = <<<JSON
{
  "info": {
    "name": "Test Collection",
    "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
  },
  "item": [
    {
      "name": "User Login",
      "request": {
        "method": "POST",
        "header": [
          {
            "key": "Authorization",
            "value": "Bearer token",
            "description": "Auth token",
            "disabled": false
          },
          {
            "key": "X-Optional",
            "value": "test",
            "description": "Disabled header",
            "disabled": true
          }
        ],
        "url": {
          "raw": "https://api.example.com/login?debug=1",
          "query": [
            {
              "key": "debug",
              "value": "1",
              "description": "Debug mode",
              "disabled": false
            }
          ]
        },
        "description": "### Login Request\\nThis endpoint authenticates a user using credentials."
      }
    }
  ]
}
JSON;

    $result = $parser->parse($json, 'test.json');

    expect($result->requests)->not->toBeEmpty();
    $request = $result->requests[0];

    expect($request->name)->toBe('User Login');
    expect($request->description)->toBe("### Login Request\nThis endpoint authenticates a user using credentials.");

    // Verify headers
    expect($request->headers)->toHaveCount(2);
    expect($request->headers[0]['key'])->toBe('Authorization');
    expect($request->headers[0]['description'])->toBe('Auth token');
    expect($request->headers[0]['enabled'])->toBeTrue();

    expect($request->headers[1]['key'])->toBe('X-Optional');
    expect($request->headers[1]['description'])->toBe('Disabled header');
    expect($request->headers[1]['enabled'])->toBeFalse();

    // Verify query params
    expect($request->queryParams)->toHaveCount(1);
    expect($request->queryParams[0]['key'])->toBe('debug');
    expect($request->queryParams[0]['description'])->toBe('Debug mode');
    expect($request->queryParams[0]['enabled'])->toBeTrue();
});

test('exports postman v2 request descriptions and header metadata', function () {
    $team = Team::factory()->create();

    $collection = Collection::create([
        'team_id' => $team->id,
        'name' => 'Export Collection',
        'description' => 'Test collection export',
    ]);
    
    $request = ApiRequest::create([
        'collection_id' => $collection->id,
        'name' => 'Get Profile',
        'method' => 'GET',
        'url' => 'https://api.example.com/profile?verbose=1',
        'description' => "### Profile API\nReturns user profile details.",
        'headers' => [
            [
                'key' => 'Accept',
                'value' => 'application/json',
                'enabled' => true,
                'description' => 'Accept json'
            ]
        ],
        'query_params' => [
            [
                'key' => 'verbose',
                'value' => '1',
                'enabled' => true,
                'description' => 'Verbose mode'
            ]
        ],
        'body' => [],
        'auth' => [],
    ]);

    $collection->load(['folders.requests', 'requests']);

    $exporter = new PostmanV2Exporter();
    $result = $exporter->generate($collection);

    $data = json_decode($result->content, true);

    expect($data)->toBeArray();
    expect($data['item'])->toHaveCount(1);
    
    $item = $data['item'][0];
    expect($item['name'])->toBe('Get Profile');
    expect($item['request']['description'])->toBe("### Profile API\nReturns user profile details.");
    expect($item['request']['header'][0]['description'])->toBe('Accept json');
    expect($item['request']['url']['query'][0]['description'])->toBe('Verbose mode');
});

test('controller stores and updates request description', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $collection = Collection::create([
        'team_id' => $team->id,
        'name' => 'API Collection',
    ]);

    // Store request with description
    $response = $this
        ->actingAs($user)
        ->post(route('collections.requests.store', $collection->id), [
            'name' => 'Create User',
            'method' => 'POST',
            'url' => 'https://api.example.com/users',
            'description' => '### Create User Endpoint',
        ]);

    $response->assertStatus(302);
    $request = ApiRequest::where('collection_id', $collection->id)->first();
    expect($request)->not->toBeNull();
    expect($request->description)->toBe('### Create User Endpoint');

    // Update request description
    $response = $this
        ->actingAs($user)
        ->patch(route('requests.update', $request->id), [
            'name' => 'Create User Updated',
            'method' => 'POST',
            'url' => 'https://api.example.com/users',
            'description' => '### Updated Description',
        ]);

    $response->assertStatus(302);
    $request->refresh();
    expect($request->description)->toBe('### Updated Description');
});

test('documentation supports attached environment', function () {
    $user = \App\Models\User::factory()->create();
    $team = \App\Domains\Teams\Models\Team::factory()->create();
    $team->members()->attach($user, ['role' => \App\Enums\TeamRole::Admin->value]);
    $user->switchTeam($team);

    $collection = Collection::create([
        'team_id' => $team->id,
        'name' => 'API Docs Collection',
    ]);

    $environment = \App\Domains\Environments\Models\Environment::create([
        'team_id' => $team->id,
        'name' => 'Staging',
        'color' => '#10b981',
    ]);

    $environment->variables()->create([
        'key' => 'baseUrl',
        'value' => 'https://staging.example.com',
        'enabled' => true,
    ]);

    $response = $this
        ->actingAs($user)
        ->post("/documentation/collection/{$collection->id}", [
            'public_slug' => 'test-staging-docs',
            'version' => '2.0.0',
            'is_public' => true,
            'environment_id' => $environment->id,
        ]);

    $response->assertStatus(302);

    $doc = \App\Domains\Documentation\Models\CollectionDocumentation::where('collection_id', $collection->id)->first();
    expect($doc)->not->toBeNull();
    expect($doc->environment_id)->toBe($environment->id);
    expect($doc->is_public)->toBeTrue();

    // Visit public documentation and assert environment is loaded
    $response = $this->get("/docs/{$collection->id}/test-staging-docs");
    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Documentation/PublicViewer')
        ->has('environment')
        ->where('environment.name', 'Staging')
        ->where('environment.variables.0.key', 'baseUrl')
        ->where('environment.variables.0.value', 'https://staging.example.com')
    );
});
