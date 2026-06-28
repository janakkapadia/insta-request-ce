<?php

namespace App\Actions\Teams;

use App\Domains\Collections\Models\Collection;
use App\Domains\Teams\Models\Team;
use App\Enums\TeamRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateTeam
{
    /**
     * Create a new team and add the user as owner.
     */
    public function handle(User $user, string $name, bool $isPersonal = false): Team
    {
        return DB::transaction(function () use ($user, $name, $isPersonal) {
            $team = Team::create([
                'name' => $name,
                'is_personal' => $isPersonal,
            ]);

            $membership = $team->memberships()->create([
                'user_id' => $user->id,
                'role' => TeamRole::Owner,
            ]);

            Collection::create([
                'team_id' => $team->id,
                'name' => $user->name,
            ]);

            $user->switchTeam($team);

            return $team;
        });
    }
}
