<?php

namespace Tests\Feature;

use App\Domains\Collections\Models\Collection;
use App\Domains\Collections\Models\CollectionFolder;
use App\Domains\Documentation\Models\RequestResponseExample;
use App\Domains\ImportExport\DTOs\ImportParseResult;
use App\Domains\ImportExport\DTOs\ParsedFolder;
use App\Domains\ImportExport\DTOs\ParsedRequest;
use App\Domains\ImportExport\Models\Import;
use App\Domains\ImportExport\Services\ConflictResolver;
use App\Domains\ImportExport\Services\ImportService;
use App\Domains\Requests\Models\Request as ApiRequest;
use App\Domains\Teams\Models\Team;
use App\Enums\MergeStrategy;
use App\Enums\TeamRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportMirrorTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Team $team;

    private ImportService $importService;

    private ConflictResolver $conflictResolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->team = Team::factory()->create();
        $this->team->members()->attach($this->user, ['role' => TeamRole::Owner->value]);
        $this->user->switchTeam($this->team);

        $this->importService = app(ImportService::class);
        $this->conflictResolver = app(ConflictResolver::class);
    }

    public function test_find_deletions_reports_missing_requests(): void
    {
        $collection = Collection::create([
            'team_id' => $this->team->id,
            'name' => 'Original Collection',
        ]);

        $reqToKeep = ApiRequest::create([
            'collection_id' => $collection->id,
            'name' => 'Get Users',
            'method' => 'GET',
            'url' => 'https://api.example.com/users',
            'body' => ['text' => ''],
        ]);

        $reqToDelete = ApiRequest::create([
            'collection_id' => $collection->id,
            'name' => 'Old Endpoint',
            'method' => 'DELETE',
            'url' => 'https://api.example.com/deprecated',
            'body' => ['text' => ''],
        ]);

        $parsed = new ImportParseResult(
            collectionName: 'Mirror Spec',
            collectionDescription: 'Synced description',
            folders: [],
            requests: [
                new ParsedRequest(
                    name: 'Get Users',
                    method: 'GET',
                    url: 'https://api.example.com/users',
                    headers: [],
                    queryParams: [],
                    body: [],
                    auth: [],
                    description: null,
                ),
            ],
            validationMessages: []
        );

        $deletions = $this->conflictResolver->findDeletions($parsed, $collection);

        $this->assertCount(1, $deletions);
        $this->assertEquals($reqToDelete->id, $deletions[0]->existingRequestId);
        $this->assertEquals('Old Endpoint', $deletions[0]->requestName);
    }

    public function test_find_deletions_deduplicates_folder_requests(): void
    {
        $collection = Collection::create([
            'team_id' => $this->team->id,
            'name' => 'Folder Collection',
        ]);

        $folder = CollectionFolder::create([
            'collection_id' => $collection->id,
            'name' => 'Legacy Folder',
        ]);

        $reqInFolder = ApiRequest::create([
            'collection_id' => $collection->id,
            'folder_id' => $folder->id,
            'name' => 'Folder Request To Delete',
            'method' => 'POST',
            'url' => 'https://api.example.com/charge',
            'body' => ['text' => ''],
        ]);

        $parsed = new ImportParseResult(
            collectionName: 'Mirror Spec',
            collectionDescription: null,
            folders: [],
            requests: [],
            validationMessages: []
        );

        $deletions = $this->conflictResolver->findDeletions($parsed, $collection);

        // Even though $collection->requests and $collection->folders->requests both reference $reqInFolder,
        // findDeletions should return exactly 1 unique deletion item.
        $this->assertCount(1, $deletions);
        $this->assertEquals($reqInFolder->id, $deletions[0]->existingRequestId);
    }

    public function test_mirror_strategy_prunes_relocates_and_syncs_metadata(): void
    {
        $collection = Collection::create([
            'team_id' => $this->team->id,
            'name' => 'Old Collection Name',
            'description' => 'Old Description',
        ]);

        $oldFolder = CollectionFolder::create([
            'collection_id' => $collection->id,
            'name' => 'Legacy V1',
        ]);

        // Request that will be deleted because it is missing in the incoming spec
        $reqToDelete = ApiRequest::create([
            'collection_id' => $collection->id,
            'folder_id' => $oldFolder->id,
            'name' => 'Deprecated User Get',
            'method' => 'GET',
            'url' => 'https://api.example.com/v1/users',
            'body' => ['text' => ''],
        ]);

        // Request that will move to a new folder
        $reqToMove = ApiRequest::create([
            'collection_id' => $collection->id,
            'folder_id' => $oldFolder->id,
            'name' => 'Get Posts',
            'method' => 'GET',
            'url' => 'https://api.example.com/posts',
            'body' => ['text' => ''],
        ]);

        $parsed = new ImportParseResult(
            collectionName: 'Synced OpenAPI Collection',
            collectionDescription: 'Updated Collection Description',
            folders: [
                new ParsedFolder(
                    name: 'Posts V2',
                    description: 'Posts endpoints',
                    requests: [
                        new ParsedRequest(
                            name: 'Get Posts V2',
                            method: 'GET',
                            url: 'https://api.example.com/posts',
                            headers: [],
                            queryParams: [],
                            body: [],
                            auth: [],
                            description: 'Updated description for posts',
                        ),
                    ],
                    folders: []
                ),
            ],
            requests: [],
            validationMessages: []
        );

        $import = Import::create([
            'team_id' => $this->team->id,
            'user_id' => $this->user->id,
            'original_filename' => 'spec_v2.json',
            'source_format' => 'openapi_3',
            'file_hash' => 'dummy',
            'parsed_data' => $parsed->toArray(),
            'status' => 'previewing',
        ]);

        $this->importService->confirmImport($import, MergeStrategy::Mirror, $collection->id);

        $collection->refresh();

        // 1. Check Collection metadata updated
        $this->assertEquals('Synced OpenAPI Collection', $collection->name);
        $this->assertEquals('Updated Collection Description', $collection->description);

        // 2. Check reqToDelete is soft-deleted
        $this->assertTrue($reqToDelete->fresh()->trashed());

        // 3. Check oldFolder is pruned (soft-deleted) because all its requests were either moved or deleted
        $this->assertTrue($oldFolder->fresh()->trashed());

        // 4. Check new folder was created and reqToMove moved into it and updated
        $newFolder = CollectionFolder::where('collection_id', $collection->id)->where('name', 'Posts V2')->first();
        $this->assertNotNull($newFolder);

        $reqToMove->refresh();
        $this->assertFalse($reqToMove->trashed());
        $this->assertEquals($newFolder->id, $reqToMove->folder_id);
        $this->assertEquals('Get Posts V2', $reqToMove->name);
        $this->assertEquals('Updated description for posts', $reqToMove->description);
    }

    public function test_mirror_preserves_handwritten_description_when_parsed_is_null(): void
    {
        $collection = Collection::create([
            'team_id' => $this->team->id,
            'name' => 'API Collection',
        ]);

        $request = ApiRequest::create([
            'collection_id' => $collection->id,
            'name' => 'Refresh domain status',
            'method' => 'POST',
            'url' => 'https://api.example.com/domains/refresh',
            'description' => 'Triggers a fresh DNS lookup for the given domain.',
            'body' => ['text' => ''],
        ]);

        $parsed = new ImportParseResult(
            collectionName: 'API Collection',
            collectionDescription: null,
            folders: [],
            requests: [
                new ParsedRequest(
                    name: 'Refresh domain status',
                    method: 'POST',
                    url: 'https://api.example.com/domains/refresh',
                    headers: [],
                    queryParams: [],
                    body: [],
                    auth: [],
                    description: null,
                ),
            ],
            validationMessages: []
        );

        $import = Import::create([
            'team_id' => $this->team->id,
            'user_id' => $this->user->id,
            'original_filename' => 'spec.json',
            'source_format' => 'openapi_3',
            'file_hash' => 'dummy2',
            'parsed_data' => $parsed->toArray(),
            'status' => 'previewing',
        ]);

        $this->importService->confirmImport($import, MergeStrategy::Mirror, $collection->id);

        $request->refresh();
        $this->assertEquals('Triggers a fresh DNS lookup for the given domain.', $request->description);
    }

    public function test_mirror_syncs_response_examples(): void
    {
        $collection = Collection::create([
            'team_id' => $this->team->id,
            'name' => 'API Collection',
        ]);

        $request = ApiRequest::create([
            'collection_id' => $collection->id,
            'name' => 'Get Users',
            'method' => 'GET',
            'url' => 'https://api.example.com/users',
            'body' => ['text' => ''],
        ]);

        $parsed = new ImportParseResult(
            collectionName: 'API Collection',
            collectionDescription: null,
            folders: [],
            requests: [
                new ParsedRequest(
                    name: 'Get Users',
                    method: 'GET',
                    url: 'https://api.example.com/users',
                    headers: [],
                    queryParams: [],
                    body: [],
                    auth: [],
                    description: 'Returns all users',
                    examples: [
                        [
                            'name' => 'Success (200)',
                            'status_code' => 200,
                            'headers' => ['Content-Type' => 'application/json'],
                            'body' => '{"users": [{"id": 1}]}',
                        ],
                    ],
                ),
            ],
            validationMessages: []
        );

        $import = Import::create([
            'team_id' => $this->team->id,
            'user_id' => $this->user->id,
            'original_filename' => 'spec.json',
            'source_format' => 'openapi_3',
            'file_hash' => 'dummy3',
            'parsed_data' => $parsed->toArray(),
            'status' => 'previewing',
        ]);

        $this->importService->confirmImport($import, MergeStrategy::Mirror, $collection->id);

        $request->refresh();
        $this->assertEquals('Returns all users', $request->description);

        $examples = RequestResponseExample::where('request_id', $request->id)->get();
        $this->assertCount(1, $examples);
        $this->assertEquals('Success (200)', $examples[0]->name);
        $this->assertEquals(200, $examples[0]->status_code);
        $this->assertStringContainsString('users', $examples[0]->body);
    }
}
