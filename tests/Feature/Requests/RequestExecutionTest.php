<?php

use App\Domains\Collections\Models\Collection;
use App\Domains\Environments\Models\Environment;
use App\Domains\Requests\Models\Request as ApiRequest;
use App\Domains\Teams\Models\Team;
use App\Enums\TeamRole;
use App\Models\User;
use Illuminate\Support\Facades\Http;

test('authenticated team member can execute an HTTP request and log history', function () {
    Http::fake([
        'https://jsonplaceholder.typicode.com/posts' => Http::response(['id' => 1, 'title' => 'Foo'], 200, ['Content-Type' => 'application/json']),
    ]);

    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $owner->switchTeam($team);

    $collection = Collection::create([
        'team_id' => $team->id,
        'name' => 'Test Collection',
    ]);

    $request = ApiRequest::create([
        'collection_id' => $collection->id,
        'name' => 'Get Posts',
        'method' => 'GET',
        'url' => 'https://jsonplaceholder.typicode.com/posts',
        'headers' => [],
        'query_params' => [],
        'body' => [],
        'auth' => [],
    ]);

    $response = $this
        ->actingAs($owner)
        ->post(route('requests.execute'), [
            'request_id' => $request->id,
            'method' => 'GET',
            'url' => 'https://jsonplaceholder.typicode.com/posts',
            'headers' => [],
            'query_params' => [],
            'body' => '',
        ]);

    $response->assertOk();
    $response->assertJsonPath('status', 200);

    // Verify history log was created
    $this->assertDatabaseHas('request_histories', [
        'team_id' => $team->id,
        'user_id' => $owner->id,
        'request_id' => $request->id,
        'method' => 'GET',
        'url' => 'https://jsonplaceholder.typicode.com/posts',
        'status' => 200,
    ]);
});

test('unauthorized user cannot execute a request belonging to another team', function () {
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
        'url' => 'https://example.com/secret',
        'headers' => [],
        'query_params' => [],
        'body' => [],
        'auth' => [],
    ]);

    // acting as otherUser who belongs to otherTeam, trying to execute request belonging to team
    $response = $this
        ->actingAs($otherUser)
        ->post(route('requests.execute'), [
            'request_id' => $request->id,
            'method' => 'GET',
            'url' => 'https://example.com/secret',
            'headers' => [],
            'query_params' => [],
            'body' => '',
        ]);

    $response->assertForbidden();
});

test('path variables are properly substituted in the URL', function () {
    Http::fake([
        'https://api.example.com/users/99/posts/100' => Http::response(['success' => true], 200, []),
    ]);

    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $owner->switchTeam($team);

    $collection = Collection::create([
        'team_id' => $team->id,
        'name' => 'Team A Collection',
    ]);

    $request = ApiRequest::create([
        'collection_id' => $collection->id,
        'name' => 'Path Var Request',
        'method' => 'GET',
        'url' => 'https://api.example.com/users/:userId/posts/:postId',
        'headers' => [],
        'query_params' => [],
        'path_variables' => [
            ['key' => 'userId', 'value' => '99', 'enabled' => true],
            ['key' => 'postId', 'value' => '100', 'enabled' => true],
            ['key' => 'ignored', 'value' => '101', 'enabled' => false],
        ],
        'body' => [],
        'auth' => [],
    ]);

    $response = $this
        ->actingAs($owner)
        ->post(route('requests.execute'), [
            'request_id' => $request->id,
            'method' => 'GET',
            'url' => 'https://api.example.com/users/:userId/posts/:postId',
            'headers' => [],
            'query_params' => [],
            'path_variables' => [
                ['key' => 'userId', 'value' => '99', 'enabled' => true],
                ['key' => 'postId', 'value' => '100', 'enabled' => true],
                ['key' => 'ignored', 'value' => '101', 'enabled' => false],
            ],
            'body' => '',
        ]);

    $response->assertOk();
    $response->assertJsonPath('resolved_url', 'https://api.example.com/users/99/posts/100');
});

test('environment variables work with both double curly and single curly syntax during request execution', function () {
    Http::fake([
        'https://api.example.com/v1/users/123?filter=active' => Http::response(['success' => true], 200, []),
    ]);

    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $owner->switchTeam($team);

    $environment = Environment::create([
        'team_id' => $team->id,
        'name' => 'Test Env',
    ]);
    $environment->variables()->create(['key' => 'baseUrl', 'value' => 'https://api.example.com', 'enabled' => true]);
    $environment->variables()->create(['key' => 'version', 'value' => 'v1', 'enabled' => true]);
    $environment->variables()->create(['key' => 'userId', 'value' => '123', 'enabled' => true]);
    $environment->variables()->create(['key' => 'filterVal', 'value' => 'active', 'enabled' => true]);
    $environment->variables()->create(['key' => 'apiKey', 'value' => 'secret-key', 'enabled' => true]);

    $collection = Collection::create([
        'team_id' => $team->id,
        'name' => 'Test Collection',
    ]);

    $request = ApiRequest::create([
        'collection_id' => $collection->id,
        'name' => 'Dual Syntax Request',
        'method' => 'GET',
        'url' => '{{baseUrl}}/{version}/users/{{userId}}',
        'headers' => [],
        'query_params' => [],
        'body' => [],
        'auth' => [],
    ]);

    $response = $this
        ->actingAs($owner)
        ->post(route('requests.execute'), [
            'request_id' => $request->id,
            'method' => 'GET',
            'url' => '{{baseUrl}}/{version}/users/{{userId}}',
            'headers' => [
                ['key' => 'Authorization', 'value' => 'Bearer {{apiKey}}', 'enabled' => true],
                ['key' => 'X-Custom', 'value' => '{apiKey}', 'enabled' => true],
            ],
            'query_params' => [
                ['key' => 'filter', 'value' => '{{filterVal}}', 'enabled' => true],
            ],
            'body' => '',
            'environment_id' => $environment->id,
        ]);

    $response->assertOk();
    $response->assertJsonPath('resolved_url', 'https://api.example.com/v1/users/123');
    expect($response->json('request_options.query.filter'))->toBe('active');
    expect($response->json('request_options.headers.Authorization'))->toBe('Bearer secret-key');
    expect($response->json('request_options.headers.X-Custom'))->toBe('secret-key');
});
