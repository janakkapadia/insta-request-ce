<?php

use App\Domains\Collections\Models\Collection;
use App\Domains\Environments\Models\Environment;
use App\Domains\History\Models\RequestHistory;
use App\Domains\Teams\Models\Team;
use App\Enums\TeamRole;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->team = Team::factory()->create();
    $this->team->members()->attach($this->user, ['role' => TeamRole::Admin->value]);
    $this->user->switchTeam($this->team);
});

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can view dashboard and it contains stats, collections and history', function () {
    Collection::create(['team_id' => $this->team->id, 'name' => 'Test Collection']);
    Environment::create(['team_id' => $this->team->id, 'name' => 'Prod', 'color' => 'red']);

    // We need to fetch the user again or act as the user
    $this->actingAs($this->user);

    RequestHistory::create([
        'team_id' => $this->team->id,
        'user_id' => $this->user->id,
        'method' => 'GET',
        'url' => 'https://api.test.com',
        'status' => 200,
        'time_ms' => 120,
        'size_bytes' => 1024,
    ]);

    $response = $this->get(route('dashboard'));
    $response->assertStatus(200);

    $response->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard')
        ->has('recentCollections', 1)
        ->has('recentHistory', 1)
        ->has('stats', fn (Assert $page) => $page
            ->where('total_collections', 1)
            ->where('total_requests_made', 1)
            ->where('total_environments', 1)
        )
    );
});
