<?php

use App\Models\User;
use App\Enums\TeamRole;
use App\Domains\Teams\Models\Team;
use App\Domains\Collections\Models\Collection;
use App\Domains\Collections\Models\CollectionFolder;
use App\Domains\Requests\Models\Request as ApiRequest;

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
                ['key' => 'X-Test-Header', 'value' => 'HelloHeader', 'enabled' => true]
            ],
            'query_params' => [
                ['key' => 'foo', 'value' => 'bar', 'enabled' => true]
            ],
            'auth' => [
                'type' => 'bearer',
                'bearerToken' => 'my-secure-token'
            ]
        ]);

    $response->assertRedirect();

    $request->refresh();
    expect($request->name)->toBe('Updated Name');
    expect($request->url)->toBe('https://httpbin.org/get?foo=bar');
    expect($request->headers)->toBe([
        ['key' => 'X-Test-Header', 'value' => 'HelloHeader', 'enabled' => true]
    ]);
    expect($request->query_params)->toBe([
        ['key' => 'foo', 'value' => 'bar', 'enabled' => true]
    ]);
    expect($request->auth)->toBe([
        'type' => 'bearer',
        'bearerToken' => 'my-secure-token'
    ]);
});
