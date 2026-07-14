<?php

namespace App\Console\Commands;

use App\Domains\Teams\Models\Team;
use App\Models\User;
use Illuminate\Console\Command;

class GenerateApiToken extends Command
{
    protected $signature = 'api:token
                            {email : The email address of the user to generate a token for}
                            {--name=CI Token : Name for the personal access token}
                            {--team= : Team slug or UUID to verify membership (optional)}';

    protected $description = 'Generate a Sanctum personal access token for a user (for CI/automation use)';

    public function handle(): int
    {
        $email = $this->argument('email');
        $user  = User::where('email', $email)->first();

        if (! $user) {
            $this->error("No user found with email: {$email}");
            return self::FAILURE;
        }

        // Optional team membership check
        if ($this->option('team')) {
            $teamIdentifier = $this->option('team');
            $team = Team::where('slug', $teamIdentifier)
                ->orWhere('id', $teamIdentifier)
                ->first();

            if (! $team) {
                $this->error("Team not found: {$teamIdentifier}");
                return self::FAILURE;
            }

            if (! $user->belongsToTeam($team)) {
                $this->error("User {$email} is not a member of team: {$team->name}");
                return self::FAILURE;
            }

            $this->line("✓ Verified: {$user->name} is a member of team <comment>{$team->name}</comment>");
        }

        $tokenName = $this->option('name');
        $token     = $user->createToken($tokenName);

        $this->newLine();
        $this->info('✓ Token created successfully.');
        $this->newLine();
        $this->line('<comment>Token (shown once — store it safely):</comment>');
        $this->line($token->plainTextToken);
        $this->newLine();
        $this->table(
            ['User', 'Token Name', 'Token ID'],
            [[$user->email, $tokenName, $token->accessToken->id]]
        );
        $this->newLine();
        $this->line('Usage:');
        $this->line('  <comment>Authorization: Bearer ' . $token->plainTextToken . '</comment>');
        $this->newLine();

        return self::SUCCESS;
    }
}
