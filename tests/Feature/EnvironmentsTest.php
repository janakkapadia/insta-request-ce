<?php

use App\Domains\Environments\Models\Environment;
use App\Domains\Environments\Models\EnvironmentVariable;
use App\Models\User;
use App\Domains\Teams\Models\Team;
use App\Enums\TeamRole;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->team = Team::factory()->create();
    $this->team->members()->attach($this->user, ['role' => TeamRole::Admin->value]);
    $this->user->switchTeam($this->team);
});

test('guest cannot access environments', function () {
    $response = $this->get(route('environments.index', ['team' => $this->team->slug]));
    $response->assertRedirect(route('login'));
});

test('user can view environment index page', function () {
    $this->actingAs($this->user);
    
    $response = $this->get(route('environments.index', ['team' => $this->team->slug]));
    $response->assertStatus(200);
});

test('user can fetch environments json list', function () {
    $this->actingAs($this->user);

    Environment::create([
        'team_id' => $this->team->id,
        'name' => 'Staging Env'
    ]);

    $response = $this->getJson(route('api.environments.list', ['team' => $this->team->slug]));
    
    $response->assertStatus(200)
        ->assertJsonCount(1)
        ->assertJsonFragment(['name' => 'Staging Env']);
});

test('user can create a new environment with variables', function () {
    $this->actingAs($this->user);

    $response = $this->postJson(route('api.environments.store', ['team' => $this->team->slug]), [
        'name' => 'Production Server',
        'variables' => [
            ['key' => 'base_url', 'value' => 'https://api.myapp.com', 'enabled' => true],
            ['key' => 'auth_token', 'value' => 'secret-1234', 'enabled' => false],
        ]
    ]);

    $response->assertStatus(200)
        ->assertJsonFragment(['name' => 'Production Server']);

    $env = Environment::where('name', 'Production Server')->first();
    expect($env)->not->toBeNull();
    expect($env->variables)->toHaveCount(2);
    expect($env->variables()->where('key', 'base_url')->first()->value)->toBe('https://api.myapp.com');
});

test('user can update environment and sync variables', function () {
    $this->actingAs($this->user);

    $env = Environment::create([
        'team_id' => $this->team->id,
        'name' => 'Dev Local'
    ]);

    $var = $env->variables()->create([
        'key' => 'port',
        'value' => '8000',
        'enabled' => true
    ]);

    $response = $this->putJson(route('api.environments.update', ['team' => $this->team->slug, 'environment' => $env->id]), [
        'name' => 'Dev Upgraded',
        'variables' => [
            // Update existing variable
            ['id' => $var->id, 'key' => 'port', 'value' => '9000', 'enabled' => true],
            // Create new variable
            ['key' => 'debug', 'value' => 'true', 'enabled' => true],
        ]
    ]);

    $response->assertStatus(200);

    $env->refresh();
    expect($env->name)->toBe('Dev Upgraded');
    expect($env->variables)->toHaveCount(2);
    expect($env->variables()->where('key', 'port')->first()->value)->toBe('9000');
    expect($env->variables()->where('key', 'debug')->first()->value)->toBe('true');
});

test('user can delete environment', function () {
    $this->actingAs($this->user);

    $env = Environment::create([
        'team_id' => $this->team->id,
        'name' => 'To Delete'
    ]);

    $response = $this->deleteJson(route('api.environments.destroy', ['team' => $this->team->slug, 'environment' => $env->id]));
    
    $response->assertStatus(200);
    expect(Environment::find($env->id))->toBeNull();
});
