<?php

namespace Tests\Feature;

use App\Domains\Collections\Models\Collection;
use App\Domains\Requests\Models\Request as ApiRequest;
use App\Domains\ImportExport\DTOs\ImportParseResult;
use App\Domains\ImportExport\DTOs\ParsedRequest;
use App\Domains\ImportExport\Models\Import;
use App\Domains\ImportExport\Services\ConflictResolver;
use App\Domains\ImportExport\Services\ImportService;
use App\Domains\Teams\Models\Team;
use App\Enums\TeamRole;
use App\Models\User;
use App\Enums\MergeStrategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportMergeTest extends TestCase
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

    public function test_no_collision_for_same_summary_but_different_endpoints(): void
    {
        $collection = Collection::create([
            'team_id' => $this->team->id,
            'name' => 'Existing Collection',
        ]);

        // Create an existing request with summary "Get backups" on /sites/{id}/backups
        ApiRequest::create([
            'collection_id' => $collection->id,
            'name' => 'Get backups',
            'method' => 'GET',
            'url' => 'https://api.example.com/v1/sites/{id}/backups',
            'body' => ['text' => ''],
        ]);

        // Now we parse a new import that has another request with the same summary "Get backups" but for /teams/{id}/backups
        $parsed = new ImportParseResult(
            collectionName: 'Imported Spec',
            collectionDescription: null,
            folders: [],
            requests: [
                new ParsedRequest(
                    name: 'Get backups',
                    method: 'GET',
                    url: 'https://api.example.com/v1/teams/{id}/backups',
                    headers: [],
                    queryParams: [],
                    body: [],
                    auth: [],
                    description: null,
                ),
            ],
            validationMessages: []
        );

        // 1. Verify findConflicts does NOT report a conflict
        $conflicts = $this->conflictResolver->findConflicts($parsed, $collection);
        $this->assertEmpty($conflicts, 'Should not report a conflict for requests with same name but different URLs');

        // 2. Perform merge into existing collection with MergeReplace strategy
        $import = Import::create([
            'team_id' => $this->team->id,
            'user_id' => $this->user->id,
            'original_filename' => 'spec.json',
            'source_format' => 'openapi_3',
            'file_hash' => 'dummy',
            'parsed_data' => $parsed->toArray(),
            'status' => 'previewing',
        ]);

        $this->importService->confirmImport($import, MergeStrategy::MergeReplace, $collection->id);

        // Verify both requests exist inside the collection and no duplicate/collision overwrote the first one
        $this->assertEquals(2, ApiRequest::where('collection_id', $collection->id)->count());
        $this->assertDatabaseHas('requests', [
            'collection_id' => $collection->id,
            'name' => 'Get backups',
            'url' => 'https://api.example.com/v1/sites/{id}/backups',
        ]);
        $this->assertDatabaseHas('requests', [
            'collection_id' => $collection->id,
            'name' => 'Get backups',
            'url' => 'https://api.example.com/v1/teams/{id}/backups',
        ]);
    }

    public function test_merge_replace_updates_request_name_when_url_matches(): void
    {
        $collection = Collection::create([
            'team_id' => $this->team->id,
            'name' => 'Existing Collection',
        ]);

        // Create an existing request
        $existingReq = ApiRequest::create([
            'collection_id' => $collection->id,
            'name' => 'Get backups',
            'method' => 'GET',
            'url' => 'https://api.example.com/v1/sites/{id}/backups',
            'body' => ['text' => ''],
        ]);

        // Now we import an updated spec where the summary/name changed from "Get backups" to "Get all site backups"
        $parsed = new ImportParseResult(
            collectionName: 'Updated Spec',
            collectionDescription: null,
            folders: [],
            requests: [
                new ParsedRequest(
                    name: 'Get all site backups',
                    method: 'GET',
                    url: 'https://api.example.com/v1/sites/{id}/backups',
                    headers: [],
                    queryParams: [],
                    body: [],
                    auth: [],
                    description: 'Updated description',
                ),
            ],
            validationMessages: []
        );

        // 1. Verify findConflicts correctly detects the conflict by matching method + normalized URL
        $conflicts = $this->conflictResolver->findConflicts($parsed, $collection);
        $this->assertCount(1, $conflicts);
        $this->assertEquals($existingReq->id, $conflicts[0]->existingRequestId);
        $this->assertEquals('Get all site backups', $conflicts[0]->requestName);

        // 2. Perform confirmImport with MergeReplace
        $import = Import::create([
            'team_id' => $this->team->id,
            'user_id' => $this->user->id,
            'original_filename' => 'spec_v2.json',
            'source_format' => 'openapi_3',
            'file_hash' => 'dummy',
            'parsed_data' => $parsed->toArray(),
            'status' => 'previewing',
        ]);

        $this->importService->confirmImport($import, MergeStrategy::MergeReplace, $collection->id);

        // Verify the existing request was updated (not duplicated) and the name/description changed
        $this->assertEquals(1, ApiRequest::where('collection_id', $collection->id)->count());
        $this->assertDatabaseHas('requests', [
            'id' => $existingReq->id,
            'collection_id' => $collection->id,
            'name' => 'Get all site backups',
            'description' => 'Updated description',
            'url' => 'https://api.example.com/v1/sites/{id}/backups',
        ]);
    }
}
