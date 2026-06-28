<?php

use App\Models\User;
use App\Enums\TeamRole;
use App\Domains\Teams\Models\Team;
use App\Domains\Collections\Models\Collection;
use App\Domains\Requests\Models\Request as ApiRequest;
use App\Domains\History\Models\RequestHistory;
use Illuminate\Support\Facades\Http;

test('authenticated team member can execute an HTTP request and log history', function () {
    Http::fake([
        'https://jsonplaceholder.typicode.com/posts' => Http::response(['id' => 1, 'title' => 'Foo'], 200, ['Content-Type' => 'application/json'])
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
        'https://api.example.com/users/99/posts/100' => Http::response(['success' => true], 200, [])
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

