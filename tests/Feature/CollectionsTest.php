<?php

use App\Domains\Collections\Models\Collection;
use App\Domains\Collections\Models\CollectionFolder;
use App\Domains\Documentation\Models\CollectionDocumentation;
use App\Domains\Documentation\Models\RequestResponseExample;
use App\Domains\Environments\Models\Environment;
use App\Domains\Environments\Models\EnvironmentVariable;
use App\Domains\Requests\Models\Request as ApiRequest;
use App\Domains\Teams\Models\Team;
use App\Enums\TeamRole;
use App\Models\User;
use Illuminate\Support\Facades\Http;

test('authenticated team member can delete a request in their collection', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $owner->switchTeam($team);

    $collection = Collection::create([
        'team_id' => $team->id,
        'name' => 'Team Collection',
    ]);

    $request = ApiRequest::create([
        'collection_id' => $collection->id,
        'name' => 'Test Request',
        'method' => 'GET',
        'url' => 'https://example.com',
        'headers' => [],
        'query_params' => [],
        'body' => [],
        'auth' => [],
    ]);

    $response = $this
        ->actingAs($owner)
        ->delete(route('requests.destroy', $request->id));

    $response->assertRedirect();
    $this->assertSoftDeleted('requests', ['id' => $request->id]);
});

test('user cannot delete a request belonging to another team collection', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $owner->switchTeam($team);

    $otherTeam = Team::factory()->create();
    $otherTeam->members()->attach($otherUser, ['role' => TeamRole::Owner->value]);
    $otherUser->switchTeam($otherTeam);

    $collection = Collection::create([
        'team_id' => $team->id,
        'name' => 'Team A Collection',
    ]);

    $request = ApiRequest::create([
        'collection_id' => $collection->id,
        'name' => 'Secret Request',
        'method' => 'GET',
        'url' => 'https://example.com',
        'headers' => [],
        'query_params' => [],
        'body' => [],
        'auth' => [],
    ]);

    $response = $this
        ->actingAs($otherUser)
        ->delete(route('requests.destroy', $request->id));

    $response->assertForbidden();
    $this->assertDatabaseHas('requests', ['id' => $request->id, 'deleted_at' => null]);
});

test('authenticated team member can delete an empty folder', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $owner->switchTeam($team);

    $collection = Collection::create([
        'team_id' => $team->id,
        'name' => 'Team Collection',
    ]);

    $folder = CollectionFolder::create([
        'collection_id' => $collection->id,
        'name' => 'Test Folder',
    ]);

    $response = $this
        ->actingAs($owner)
        ->delete(route('folders.destroy', $folder->id));

    $response->assertRedirect();
    $this->assertSoftDeleted('collection_folders', ['id' => $folder->id]);
});

test('authenticated team member can delete a folder that contains requests and deletes contained requests', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $owner->switchTeam($team);

    $collection = Collection::create([
        'team_id' => $team->id,
        'name' => 'Team Collection',
    ]);

    $folder = CollectionFolder::create([
        'collection_id' => $collection->id,
        'name' => 'Test Folder',
    ]);

    $request = ApiRequest::create([
        'collection_id' => $collection->id,
        'folder_id' => $folder->id,
        'name' => 'Nested Request',
        'method' => 'GET',
        'url' => 'https://example.com',
        'headers' => [],
        'query_params' => [],
        'body' => [],
        'auth' => [],
    ]);

    $response = $this
        ->actingAs($owner)
        ->delete(route('folders.destroy', $folder->id));

    $response->assertRedirect();
    $this->assertSoftDeleted('collection_folders', ['id' => $folder->id]);
    $this->assertSoftDeleted('requests', ['id' => $request->id]);
});

test('user cannot delete a folder belonging to another team collection', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $owner->switchTeam($team);

    $otherTeam = Team::factory()->create();
    $otherTeam->members()->attach($otherUser, ['role' => TeamRole::Owner->value]);
    $otherUser->switchTeam($otherTeam);

    $collection = Collection::create([
        'team_id' => $team->id,
        'name' => 'Team A Collection',
    ]);

    $folder = CollectionFolder::create([
        'collection_id' => $collection->id,
        'name' => 'Secret Folder',
    ]);

    $response = $this
        ->actingAs($otherUser)
        ->delete(route('folders.destroy', $folder->id));

    $response->assertForbidden();
    $this->assertDatabaseHas('collection_folders', ['id' => $folder->id, 'deleted_at' => null]);
});

test('authenticated team member can delete an empty collection', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $owner->switchTeam($team);

    $collection = Collection::create([
        'team_id' => $team->id,
        'name' => 'Team Collection',
    ]);

    $response = $this
        ->actingAs($owner)
        ->delete(route('collections.destroy', $collection->id));

    $response->assertRedirect();
    $this->assertSoftDeleted('collections', ['id' => $collection->id]);
});

test('authenticated team member can delete a collection that contains folders and requests', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $owner->switchTeam($team);

    $collection = Collection::create([
        'team_id' => $team->id,
        'name' => 'Team Collection',
    ]);

    $folder = CollectionFolder::create([
        'collection_id' => $collection->id,
        'name' => 'Test Folder',
    ]);

    $request = ApiRequest::create([
        'collection_id' => $collection->id,
        'folder_id' => $folder->id,
        'name' => 'Nested Request',
        'method' => 'GET',
        'url' => 'https://example.com',
        'headers' => [],
        'query_params' => [],
        'body' => [],
        'auth' => [],
    ]);

    $response = $this
        ->actingAs($owner)
        ->delete(route('collections.destroy', $collection->id));

    $response->assertRedirect();
    $this->assertSoftDeleted('collections', ['id' => $collection->id]);
    $this->assertSoftDeleted('collection_folders', ['id' => $folder->id]);
    $this->assertSoftDeleted('requests', ['id' => $request->id]);
});

test('user cannot delete a collection belonging to another team', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $owner->switchTeam($team);

    $otherTeam = Team::factory()->create();
    $otherTeam->members()->attach($otherUser, ['role' => TeamRole::Owner->value]);
    $otherUser->switchTeam($otherTeam);

    $collection = Collection::create([
        'team_id' => $team->id,
        'name' => 'Team A Collection',
    ]);

    $response = $this
        ->actingAs($otherUser)
        ->delete(route('collections.destroy', $collection->id));

    $response->assertForbidden();
    $this->assertDatabaseHas('collections', ['id' => $collection->id, 'deleted_at' => null]);
});

test('authenticated team member can update request headers, query params and auth configs', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $owner->switchTeam($team);

    $collection = Collection::create([
        'team_id' => $team->id,
        'name' => 'Team Collection',
    ]);

    $request = ApiRequest::create([
        'collection_id' => $collection->id,
        'name' => 'Test Request',
        'method' => 'GET',
        'url' => 'https://httpbin.org/get',
    ]);

    $response = $this
        ->actingAs($owner)
        ->patch(route('requests.update', $request->id), [
            'name' => 'Updated Name',
            'url' => 'https://httpbin.org/get?foo=bar',
            'headers' => [
                ['key' => 'X-Test-Header', 'value' => 'HelloHeader', 'enabled' => true],
            ],
            'query_params' => [
                ['key' => 'foo', 'value' => 'bar', 'enabled' => true],
            ],
            'auth' => [
                'type' => 'bearer',
                'bearerToken' => 'my-secure-token',
            ],
        ]);

    $response->assertRedirect();

    $request->refresh();
    expect($request->name)->toBe('Updated Name');
    expect($request->url)->toBe('https://httpbin.org/get?foo=bar');
    expect($request->headers)->toBe([
        ['key' => 'X-Test-Header', 'value' => 'HelloHeader', 'enabled' => true],
    ]);
    expect($request->query_params)->toBe([
        ['key' => 'foo', 'value' => 'bar', 'enabled' => true],
    ]);
    expect($request->auth)->toBe([
        'type' => 'bearer',
        'bearerToken' => 'my-secure-token',
    ]);
});

test('member can delete their own collection, folder, and request but not another member\'s', function () {
    $owner = User::factory()->create();
    $member1 = User::factory()->create();
    $member2 = User::factory()->create();

    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member1, ['role' => TeamRole::Member->value]);
    $team->members()->attach($member2, ['role' => TeamRole::Member->value]);

    $member1->switchTeam($team);
    $member2->switchTeam($team);

    $collection1 = Collection::create([
        'team_id' => $team->id,
        'user_id' => $member1->id,
        'name' => 'Member 1 Collection',
    ]);

    $folder1 = CollectionFolder::create([
        'collection_id' => $collection1->id,
        'user_id' => $member1->id,
        'name' => 'Member 1 Folder',
    ]);

    $request1 = ApiRequest::create([
        'collection_id' => $collection1->id,
        'folder_id' => $folder1->id,
        'user_id' => $member1->id,
        'name' => 'Member 1 Request',
        'method' => 'GET',
        'url' => 'https://example.com',
    ]);

    // Member 2 cannot delete member 1's request
    $this->actingAs($member2)
        ->delete(route('requests.destroy', $request1->id))
        ->assertStatus(403);

    // Member 2 cannot delete member 1's folder
    $this->actingAs($member2)
        ->delete(route('folders.destroy', $folder1->id))
        ->assertStatus(403);

    // Member 2 cannot delete member 1's collection
    $this->actingAs($member2)
        ->delete(route('collections.destroy', $collection1->id))
        ->assertStatus(403);

    // Member 1 CAN delete their own request
    $this->actingAs($member1)
        ->delete(route('requests.destroy', $request1->id))
        ->assertRedirect();
    expect(ApiRequest::find($request1->id))->toBeNull();

    // Member 1 CAN delete their own folder
    $this->actingAs($member1)
        ->delete(route('folders.destroy', $folder1->id))
        ->assertRedirect();
    expect(CollectionFolder::find($folder1->id))->toBeNull();

    // Member 1 CAN delete their own collection
    $this->actingAs($member1)
        ->delete(route('collections.destroy', $collection1->id))
        ->assertRedirect();
    expect(Collection::find($collection1->id))->toBeNull();
});

test('public documentation serves openapi json via /openapi.json endpoint', function () {
    $team = Team::factory()->create();
    $collection = Collection::create([
        'team_id' => $team->id,
        'name' => 'Public API',
        'description' => 'A public API collection',
    ]);

    $doc = CollectionDocumentation::create([
        'collection_id' => $collection->id,
        'team_id' => $team->id,
        'is_public' => true,
        'public_slug' => 'public-api-docs',
        'version' => '2.1.0',
    ]);

    $request = ApiRequest::create([
        'collection_id' => $collection->id,
        'name' => 'Get Users',
        'description' => 'Fetch all registered users',
        'method' => 'GET',
        'url' => 'https://api.example.com/users',
        'headers' => [['key' => 'X-Custom-Header', 'enabled' => true]],
        'query_params' => [['key' => 'limit', 'value' => '25', 'enabled' => true]],
        'body' => [],
    ]);

    RequestResponseExample::create([
        'request_id' => $request->id,
        'name' => 'Success 200',
        'status_code' => 200,
        'headers' => [],
        'body' => json_encode(['users' => []]),
    ]);

    $response = $this->get("/docs/{$collection->id}/public-api-docs/openapi.json");

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'application/json');
    $response->assertHeader('Access-Control-Allow-Origin', '*');

    $json = $response->json();
    expect($json)->toHaveKey('openapi');
    expect($json['openapi'])->toStartWith('3.');
    expect($json['info']['title'])->toBe('Public API');
    expect($json['info']['version'])->toBe('2.1.0');
    expect($json['paths'])->toHaveKey('/users');
    expect($json['paths']['/users'])->toHaveKey('get');
    expect($json['paths']['/users']['get']['summary'])->toBe('Get Users');
    expect($json['paths']['/users']['get']['description'])->toBe('Fetch all registered users');
    expect($json['paths']['/users']['get']['responses'])->toHaveKey('200');
});

test('public documentation serves openapi json via .json and /openapi endpoints', function () {
    $team = Team::factory()->create();
    $collection = Collection::create(['team_id' => $team->id, 'name' => 'Endpoints API']);
    CollectionDocumentation::create([
        'collection_id' => $collection->id,
        'team_id' => $team->id,
        'is_public' => true,
        'public_slug' => 'endpoints-docs',
        'version' => '1.0.0',
    ]);
    ApiRequest::create([
        'collection_id' => $collection->id,
        'name' => 'Ping',
        'method' => 'GET',
        'url' => '/ping',
    ]);

    $dotJsonResponse = $this->get("/docs/{$collection->id}/endpoints-docs.json");
    $dotJsonResponse->assertStatus(200);
    $dotJsonResponse->assertHeader('Content-Type', 'application/json');
    expect($dotJsonResponse->json('info.title'))->toBe('Endpoints API');

    $openapiResponse = $this->get("/docs/{$collection->id}/endpoints-docs/openapi");
    $openapiResponse->assertStatus(200);
    $openapiResponse->assertHeader('Content-Type', 'application/json');
    expect($openapiResponse->json('info.title'))->toBe('Endpoints API');
});

test('public documentation serves openapi json when Accept header or format query is specified', function () {
    $team = Team::factory()->create();
    $collection = Collection::create(['team_id' => $team->id, 'name' => 'Negotiated API']);
    CollectionDocumentation::create([
        'collection_id' => $collection->id,
        'team_id' => $team->id,
        'is_public' => true,
        'public_slug' => 'negotiated-docs',
        'version' => '1.0.0',
    ]);
    ApiRequest::create([
        'collection_id' => $collection->id,
        'name' => 'Status',
        'method' => 'GET',
        'url' => '/status',
    ]);

    // Accept: application/json
    $acceptResponse = $this->getJson("/docs/{$collection->id}/negotiated-docs");
    $acceptResponse->assertStatus(200);
    $acceptResponse->assertHeader('Content-Type', 'application/json');
    expect($acceptResponse->json('info.title'))->toBe('Negotiated API');

    // Query parameter: format=openapi
    $formatOpenApiResponse = $this->get("/docs/{$collection->id}/negotiated-docs?format=openapi");
    $formatOpenApiResponse->assertStatus(200);
    $formatOpenApiResponse->assertHeader('Content-Type', 'application/json');
    expect($formatOpenApiResponse->json('info.title'))->toBe('Negotiated API');

    // Query parameter: format=json
    $formatJsonResponse = $this->get("/docs/{$collection->id}/negotiated-docs?format=json");
    $formatJsonResponse->assertStatus(200);
    $formatJsonResponse->assertHeader('Content-Type', 'application/json');
    expect($formatJsonResponse->json('info.title'))->toBe('Negotiated API');
});

test('public documentation openapi endpoint supports CORS preflight options request', function () {
    $team = Team::factory()->create();
    $collection = Collection::create(['team_id' => $team->id, 'name' => 'CORS API']);
    CollectionDocumentation::create([
        'collection_id' => $collection->id,
        'team_id' => $team->id,
        'is_public' => true,
        'public_slug' => 'cors-docs',
    ]);

    $response = $this->call('OPTIONS', "/docs/{$collection->id}/cors-docs/openapi.json");
    $response->assertStatus(204);
    $response->assertHeader('Access-Control-Allow-Origin', '*');
    $response->assertHeader('Access-Control-Allow-Methods', 'GET, OPTIONS');
});

test('public documentation openapi endpoints return 404 when documentation is private or not found', function () {
    $team = Team::factory()->create();
    $collection = Collection::create(['team_id' => $team->id, 'name' => 'Private API']);
    CollectionDocumentation::create([
        'collection_id' => $collection->id,
        'team_id' => $team->id,
        'is_public' => false,
        'public_slug' => 'private-docs',
    ]);

    $this->get("/docs/{$collection->id}/private-docs/openapi.json")->assertStatus(404);
    $this->get("/docs/{$collection->id}/private-docs.json")->assertStatus(404);
    $this->get("/docs/{$collection->id}/non-existent/openapi.json")->assertStatus(404);
});

test('public documentation generates openapi spec with servers from environment variables', function () {
    $team = Team::factory()->create();
    $collection = Collection::create(['team_id' => $team->id, 'name' => 'Env API']);
    $environment = Environment::create(['team_id' => $team->id, 'name' => 'Production']);
    EnvironmentVariable::create([
        'environment_id' => $environment->id,
        'key' => 'baseUrl',
        'value' => 'https://api.production.com',
        'enabled' => true,
    ]);

    CollectionDocumentation::create([
        'collection_id' => $collection->id,
        'team_id' => $team->id,
        'environment_id' => $environment->id,
        'is_public' => true,
        'public_slug' => 'env-docs',
        'version' => '3.0.0',
    ]);

    ApiRequest::create([
        'collection_id' => $collection->id,
        'name' => 'Get Profile',
        'method' => 'GET',
        'url' => '{{baseUrl}}/profile/:userId',
        'path_variables' => [['key' => 'userId', 'value' => '123', 'enabled' => true]],
    ]);

    $response = $this->get("/docs/{$collection->id}/env-docs/openapi.json");
    $response->assertStatus(200);

    $json = $response->json();
    expect($json['servers'])->toHaveCount(1);
    expect($json['servers'][0]['url'])->toBe('https://api.production.com');
    expect($json['paths'])->toHaveKey('/profile/{userId}');
    expect($json['paths']['/profile/{userId}']['get']['parameters'][0]['name'])->toBe('userId');
    expect($json['paths']['/profile/{userId}']['get']['parameters'][0]['in'])->toBe('path');
});

test('importing from a public documentation url fetches openapi json and creates import record', function () {
    $team = Team::factory()->create();
    $owner = User::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $owner->switchTeam($team);

    $sourceCollection = Collection::create(['team_id' => $team->id, 'name' => 'Source API']);
    CollectionDocumentation::create([
        'collection_id' => $sourceCollection->id,
        'team_id' => $team->id,
        'is_public' => true,
        'public_slug' => 'source-api-docs',
    ]);
    ApiRequest::create([
        'collection_id' => $sourceCollection->id,
        'name' => 'List Orders',
        'method' => 'GET',
        'url' => 'https://api.orders.com/orders',
    ]);

    // Fetch the actual openapi json from our public endpoint
    $openApiResponse = $this->get("/docs/{$sourceCollection->id}/source-api-docs/openapi.json");
    $specJson = $openApiResponse->getContent();

    Http::fake([
        'https://example.com/docs/*' => Http::response($specJson, 200, ['Content-Type' => 'application/json']),
    ]);

    $response = $this->actingAs($owner)->post(route('import.upload'), [
        'url' => "https://example.com/docs/{$sourceCollection->id}/source-api-docs",
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();
});
