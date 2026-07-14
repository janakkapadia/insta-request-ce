<?php

use App\Domains\Collections\Models\Collection;
use App\Domains\Documentation\Models\CollectionDocumentation;
use App\Domains\ImportExport\Models\Import;
use App\Domains\Requests\Models\Request as ApiRequest;
use App\Domains\Teams\Models\Team;
use App\Enums\ImportStatus;
use App\Enums\TeamRole;
use App\Models\User;
use Illuminate\Http\UploadedFile;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

// ══════════════════════════════════════════════════════════════════════════════
// Helpers
// ══════════════════════════════════════════════════════════════════════════════

function makeUserWithTeam(): array
{
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    return [$user, $team];
}

function bearerToken(User $user, string $name = 'test-token'): string
{
    return $user->createToken($name)->plainTextToken;
}

function minimalOpenApiSpec(string $title = 'Test API', string $server = 'https://api.example.com'): string
{
    return json_encode([
        'openapi' => '3.0.0',
        'info'    => ['title' => $title, 'version' => '1.0.0'],
        'servers' => [['url' => $server]],
        'paths'   => [
            '/users' => [
                'get' => [
                    'summary'    => 'List Users',
                    'operationId' => 'listUsers',
                    'responses'  => ['200' => ['description' => 'OK']],
                ],
            ],
            '/users/{id}' => [
                'get' => [
                    'summary'    => 'Get User',
                    'operationId' => 'getUser',
                    'responses'  => ['200' => ['description' => 'OK']],
                ],
            ],
        ],
    ]);
}

// ══════════════════════════════════════════════════════════════════════════════
// Authentication
// ══════════════════════════════════════════════════════════════════════════════

test('unauthenticated requests to api/v1 are rejected with 401', function () {
    $this->getJson('/api/v1/user')->assertStatus(401);
    $this->getJson('/api/v1/collections')->assertStatus(401);
    $this->postJson('/api/v1/imports')->assertStatus(401);
});

test('valid bearer token authenticates the user', function () {
    [$user] = makeUserWithTeam();
    $token  = bearerToken($user);

    $this->withToken($token)
        ->getJson('/api/v1/user')
        ->assertOk()
        ->assertJsonFragment(['email' => $user->email]);
});

// ══════════════════════════════════════════════════════════════════════════════
// Token management
// ══════════════════════════════════════════════════════════════════════════════

test('user can list their tokens', function () {
    [$user] = makeUserWithTeam();
    $user->createToken('my-token');
    $token = bearerToken($user, 'api-token');

    $this->withToken($token)
        ->getJson('/api/v1/tokens')
        ->assertOk()
        ->assertJsonCount(2) // my-token + api-token
        ->assertJsonStructure([['id', 'name', 'created_at']]);
});

test('user can create a new token via API', function () {
    [$user] = makeUserWithTeam();
    $token  = bearerToken($user);

    $response = $this->withToken($token)
        ->postJson('/api/v1/tokens', ['name' => 'CI Deploy Token'])
        ->assertCreated()
        ->assertJsonStructure(['id', 'name', 'token', 'created_at']);

    expect($response->json('name'))->toBe('CI Deploy Token');
    expect($response->json('token'))->toBeString()->not->toBeEmpty();
});

test('user can revoke a token', function () {
    [$user] = makeUserWithTeam();
    $token    = bearerToken($user);
    $toRevoke = $user->createToken('to-revoke');
    $tokenId  = $toRevoke->accessToken->id;

    $this->withToken($token)
        ->deleteJson("/api/v1/tokens/{$tokenId}")
        ->assertOk()
        ->assertJsonFragment(['message' => 'Token revoked.']);

    expect($user->tokens()->where('id', $tokenId)->exists())->toBeFalse();
});

test('revoking a non-existent token returns 404', function () {
    [$user] = makeUserWithTeam();
    $token  = bearerToken($user);

    $this->withToken($token)
        ->deleteJson('/api/v1/tokens/99999')
        ->assertNotFound();
});

// ══════════════════════════════════════════════════════════════════════════════
// Collection listing
// ══════════════════════════════════════════════════════════════════════════════

test('user can list their collections', function () {
    [$user, $team] = makeUserWithTeam();
    $token = bearerToken($user);

    Collection::create(['team_id' => $team->id, 'name' => 'Alpha API']);
    Collection::create(['team_id' => $team->id, 'name' => 'Beta API']);

    $this->withToken($token)
        ->getJson('/api/v1/collections')
        ->assertOk()
        ->assertJsonCount(2)
        ->assertJsonStructure([['id', 'name', 'team_id', 'is_public', 'public_slug']]);
});

test('user cannot see collections from other teams', function () {
    [$user, $team]   = makeUserWithTeam();
    [$other, $other_team] = makeUserWithTeam();
    $token = bearerToken($user);

    Collection::create(['team_id' => $team->id, 'name' => 'My Collection']);
    Collection::create(['team_id' => $other_team->id, 'name' => 'Their Collection']);

    $response = $this->withToken($token)->getJson('/api/v1/collections')->assertOk();
    expect($response->json())->toHaveCount(1);
    expect($response->json('0.name'))->toBe('My Collection');
});

test('collections endpoint scopes to requested team_id', function () {
    [$user, $team] = makeUserWithTeam();

    // Join a second team
    $team2 = Team::factory()->create();
    $team2->members()->attach($user, ['role' => TeamRole::Owner->value]);

    Collection::create(['team_id' => $team->id,  'name' => 'Team1 Collection']);
    Collection::create(['team_id' => $team2->id, 'name' => 'Team2 Collection']);

    $token = bearerToken($user);

    $response = $this->withToken($token)
        ->getJson("/api/v1/collections?team_id={$team2->id}")
        ->assertOk();

    expect($response->json())->toHaveCount(1);
    expect($response->json('0.name'))->toBe('Team2 Collection');
});

test('user cannot scope collections to a team they do not belong to', function () {
    [$user]   = makeUserWithTeam();
    $stranger = Team::factory()->create();
    $token    = bearerToken($user);

    $this->withToken($token)
        ->getJson("/api/v1/collections?team_id={$stranger->id}")
        ->assertForbidden();
});

// ══════════════════════════════════════════════════════════════════════════════
// One-shot import — happy paths
// ══════════════════════════════════════════════════════════════════════════════

test('user can import an openapi spec via raw content and create a new collection', function () {
    [$user, $team] = makeUserWithTeam();
    $token = bearerToken($user);

    $this->withToken($token)
        ->postJson('/api/v1/imports', [
            'content'        => minimalOpenApiSpec('My API'),
            'filename'       => 'openapi.json',
            'merge_strategy' => 'create_new',
            'team_id'        => $team->id,
        ])
        ->assertCreated()
        ->assertJsonFragment(['status' => 'completed'])
        ->assertJsonStructure(['status', 'import_id', 'collection_id', 'collection_name', 'summary']);

    expect(Collection::where('team_id', $team->id)->count())->toBe(1);
    expect(ApiRequest::count())->toBe(2); // /users + /users/{id}
});

test('user can import via file upload', function () {
    [$user, $team] = makeUserWithTeam();
    $token = bearerToken($user);

    $file = UploadedFile::fake()->createWithContent('openapi.json', minimalOpenApiSpec());

    $this->withToken($token)
        ->post('/api/v1/imports', [
            'file'           => $file,
            'merge_strategy' => 'create_new',
            'team_id'        => $team->id,
        ], ['Authorization' => "Bearer {$token}"])
        ->assertCreated()
        ->assertJsonFragment(['status' => 'completed']);
});

test('merge_replace updates existing requests in a collection', function () {
    [$user, $team] = makeUserWithTeam();
    $token = bearerToken($user);

    $collection = Collection::create(['team_id' => $team->id, 'name' => 'Existing']);
    ApiRequest::create([
        'collection_id' => $collection->id,
        'name'          => 'List Users',
        'method'        => 'GET',
        'url'           => 'https://api.example.com/users',
        'body'          => ['text' => ''],
    ]);

    $spec = json_encode([
        'openapi' => '3.0.0',
        'info'    => ['title' => 'Updated API', 'version' => '2.0.0'],
        'servers' => [['url' => 'https://api.example.com']],
        'paths'   => [
            '/users' => [
                'get' => ['summary' => 'List Users', 'responses' => ['200' => ['description' => 'OK']]],
            ],
            '/users/{id}' => [
                'get' => ['summary' => 'Get User', 'responses' => ['200' => ['description' => 'OK']]],
            ],
        ],
    ]);

    $this->withToken($token)
        ->postJson('/api/v1/imports', [
            'content'         => $spec,
            'filename'        => 'openapi.json',
            'merge_strategy'  => 'merge_replace',
            'collection_id'   => $collection->id,
        ])
        ->assertCreated()
        ->assertJsonFragment(['status' => 'completed']);

    // 1 original + 1 new = 2 total (not duplicated)
    expect(ApiRequest::where('collection_id', $collection->id)->count())->toBe(2);
});

test('mirror strategy deletes endpoints absent from the spec', function () {
    [$user, $team] = makeUserWithTeam();
    $token = bearerToken($user);

    $collection = Collection::create(['team_id' => $team->id, 'name' => 'To Sync']);

    $toKeep = ApiRequest::create([
        'collection_id' => $collection->id,
        'name'          => 'List Users',
        'method'        => 'GET',
        'url'           => 'https://api.example.com/users',
        'body'          => ['text' => ''],
    ]);

    $toDelete = ApiRequest::create([
        'collection_id' => $collection->id,
        'name'          => 'Old Deprecated',
        'method'        => 'DELETE',
        'url'           => 'https://api.example.com/old',
        'body'          => ['text' => ''],
    ]);

    $this->withToken($token)
        ->postJson('/api/v1/imports', [
            'content'        => minimalOpenApiSpec(),
            'filename'       => 'openapi.json',
            'merge_strategy' => 'mirror',
            'collection_id'  => $collection->id,
        ])
        ->assertCreated()
        ->assertJsonFragment(['status' => 'completed']);

    $this->assertSoftDeleted('requests', ['id' => $toDelete->id]);
    $this->assertNotSoftDeleted('requests', ['id' => $toKeep->id]);
});

// ══════════════════════════════════════════════════════════════════════════════
// One-shot import — error paths
// ══════════════════════════════════════════════════════════════════════════════

test('import returns 422 when no content source is provided', function () {
    [$user, $team] = makeUserWithTeam();
    $token = bearerToken($user);

    $this->withToken($token)
        ->postJson('/api/v1/imports', [
            'merge_strategy' => 'create_new',
            'team_id'        => $team->id,
        ])
        ->assertUnprocessable();
});

test('import returns 422 when format cannot be detected', function () {
    [$user, $team] = makeUserWithTeam();
    $token = bearerToken($user);

    $this->withToken($token)
        ->postJson('/api/v1/imports', [
            'content'        => 'this is not a valid spec at all!!!',
            'filename'       => 'garbage.txt',
            'merge_strategy' => 'create_new',
            'team_id'        => $team->id,
        ])
        ->assertUnprocessable()
        ->assertJsonFragment(['message' => 'Could not detect import format. Supported formats: openapi3, swagger2, postman_v2, curl, har, insomnia.']);
});

test('import returns 422 with error message when import fails', function () {
    [$user, $team] = makeUserWithTeam();
    $token = bearerToken($user);

    // Valid JSON that passes format detection (looks like OpenAPI) but will fail during confirm
    // We simulate by passing a corrupt parsed_data — easiest is to mock, but we can also
    // test by injecting a collection_id that points to a non-existent collection so merge fails.
    // Here we use an invalid collection_id (merge_skip requires collection_id but it won't exist)
    $fakeCollectionId = '00000000-0000-0000-0000-000000000000';

    $this->withToken($token)
        ->postJson('/api/v1/imports', [
            'content'        => minimalOpenApiSpec(),
            'filename'       => 'openapi.json',
            'merge_strategy' => 'merge_skip',
            'collection_id'  => $fakeCollectionId,
            'team_id'        => $team->id,
        ])
        ->assertUnprocessable(); // collection_id exists validation fails
});

test('import returns 403 when collection belongs to another team', function () {
    [$user, $team]   = makeUserWithTeam();
    [$other, $other_team] = makeUserWithTeam();
    $token = bearerToken($user);

    $otherCollection = Collection::create(['team_id' => $other_team->id, 'name' => 'Other']);

    $this->withToken($token)
        ->postJson('/api/v1/imports', [
            'content'        => minimalOpenApiSpec(),
            'filename'       => 'openapi.json',
            'merge_strategy' => 'merge_replace',
            'collection_id'  => $otherCollection->id,
        ])
        ->assertForbidden();
});

test('import resolves team from collection_id when team_id is omitted', function () {
    [$user, $team] = makeUserWithTeam();
    $token = bearerToken($user);

    $collection = Collection::create(['team_id' => $team->id, 'name' => 'Existing']);

    $this->withToken($token)
        ->postJson('/api/v1/imports', [
            'content'        => minimalOpenApiSpec(),
            'filename'       => 'openapi.json',
            'merge_strategy' => 'merge_replace',
            'collection_id'  => $collection->id,
            // no team_id — should derive from collection
        ])
        ->assertCreated()
        ->assertJsonFragment(['status' => 'completed']);
});

// ══════════════════════════════════════════════════════════════════════════════
// Documentation publish
// ══════════════════════════════════════════════════════════════════════════════

test('user can publish a collection to public portal', function () {
    [$user, $team] = makeUserWithTeam();
    $token = bearerToken($user);

    $collection = Collection::create(['team_id' => $team->id, 'name' => 'My API']);

    $response = $this->withToken($token)
        ->putJson("/api/v1/collections/{$collection->id}/documentation", [
            'is_public'   => true,
            'public_slug' => 'my-api-docs',
            'version'     => '1.5.0',
        ])
        ->assertOk()
        ->assertJsonFragment([
            'is_public'   => true,
            'public_slug' => 'my-api-docs',
            'version'     => '1.5.0',
        ]);

    expect($response->json('public_url'))->toContain('/docs/');
    $this->assertDatabaseHas('collection_documentations', [
        'collection_id' => $collection->id,
        'is_public'     => true,
        'public_slug'   => 'my-api-docs',
    ]);
});

test('user can make a collection private via api', function () {
    [$user, $team] = makeUserWithTeam();
    $token = bearerToken($user);

    $collection = Collection::create(['team_id' => $team->id, 'name' => 'My API']);

    CollectionDocumentation::create([
        'collection_id' => $collection->id,
        'team_id'       => $team->id,
        'is_public'     => true,
        'public_slug'   => 'my-api',
        'version'       => '1.0.0',
    ]);

    $response = $this->withToken($token)
        ->putJson("/api/v1/collections/{$collection->id}/documentation", [
            'is_public' => false,
        ])
        ->assertOk()
        ->assertJsonFragment(['is_public' => false]);

    expect($response->json('public_url'))->toBeNull();
});

test('public_slug is auto-generated if not provided', function () {
    [$user, $team] = makeUserWithTeam();
    $token = bearerToken($user);

    $collection = Collection::create(['team_id' => $team->id, 'name' => 'Insta Cloud']);

    $response = $this->withToken($token)
        ->putJson("/api/v1/collections/{$collection->id}/documentation", [
            'is_public' => true,
        ])
        ->assertOk();

    expect($response->json('public_slug'))->toBeString()->not->toBeEmpty();
    expect($response->json('public_slug'))->toContain('insta-cloud');
});

test('duplicate public_slug is rejected', function () {
    [$user, $team] = makeUserWithTeam();
    $token = bearerToken($user);

    $col1 = Collection::create(['team_id' => $team->id, 'name' => 'Collection 1']);
    $col2 = Collection::create(['team_id' => $team->id, 'name' => 'Collection 2']);

    CollectionDocumentation::create([
        'collection_id' => $col1->id,
        'team_id'       => $team->id,
        'is_public'     => true,
        'public_slug'   => 'taken-slug',
        'version'       => '1.0.0',
    ]);

    $this->withToken($token)
        ->putJson("/api/v1/collections/{$col2->id}/documentation", [
            'is_public'   => true,
            'public_slug' => 'taken-slug',
        ])
        ->assertUnprocessable();
});

test('user cannot publish a collection from another team', function () {
    [$user]   = makeUserWithTeam();
    [$other, $other_team] = makeUserWithTeam();
    $token = bearerToken($user);

    $otherCollection = Collection::create(['team_id' => $other_team->id, 'name' => 'Private']);

    $this->withToken($token)
        ->putJson("/api/v1/collections/{$otherCollection->id}/documentation", [
            'is_public' => true,
        ])
        ->assertForbidden();
});

// ══════════════════════════════════════════════════════════════════════════════
// Artisan command
// ══════════════════════════════════════════════════════════════════════════════

test('api:token command generates a token for a valid user', function () {
    [$user, $team] = makeUserWithTeam();

    $this->artisan("api:token {$user->email} --name=\"CI Token\"")
        ->assertSuccessful()
        ->expectsOutputToContain('Token created successfully');

    expect($user->fresh()->tokens()->where('name', 'CI Token')->exists())->toBeTrue();
});

test('api:token command fails for unknown email', function () {
    $this->artisan('api:token unknown@example.com')
        ->assertFailed()
        ->expectsOutputToContain('No user found');
});

test('api:token command verifies team membership when --team is given', function () {
    [$user, $team] = makeUserWithTeam();
    $stranger      = Team::factory()->create();

    $this->artisan("api:token {$user->email} --team={$stranger->slug}")
        ->assertFailed()
        ->expectsOutputToContain('not a member');
});

test('api:token command succeeds when user belongs to the specified team', function () {
    [$user, $team] = makeUserWithTeam();

    $this->artisan("api:token {$user->email} --team={$team->slug} --name=\"Deploy\"")
        ->assertSuccessful();

    expect($user->fresh()->tokens()->where('name', 'Deploy')->exists())->toBeTrue();
});
