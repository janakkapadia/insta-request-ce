<?php

namespace App\Domains\Teams\Models;

use App\Concerns\GeneratesUniqueTeamSlugs;
use App\Enums\TeamRole;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Models\User;

#[Fillable(['name', 'slug', 'is_personal', 'role_permissions'])]
class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use GeneratesUniqueTeamSlugs, HasFactory, SoftDeletes, HasUuids;

    protected static function newFactory()
    {
        return TeamFactory::new();
    }

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Team $team) {
            if (empty($team->slug)) {
                $team->slug = static::generateUniqueTeamSlug($team->name);
            }
        });

        static::updating(function (Team $team) {
            if ($team->isDirty('name')) {
                $team->slug = static::generateUniqueTeamSlug($team->name, $team->id);
            }
        });
    }

    /**
     * Get the team owner.
     */
    public function owner(): ?Model
    {
        return $this->members()
            ->wherePivot('role', TeamRole::Owner->value)
            ->first();
    }

    /**
     * Get all members of this team.
     *
     * @return BelongsToMany<Model, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_members', 'team_id', 'user_id')
            ->using(TeamUser::class)
            ->withPivot(['role'])
            ->withTimestamps();
    }

    /**
     * Get all memberships for this team.
     *
     * @return HasMany<TeamUser, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(TeamUser::class, 'team_id');
    }

    /**
     * Get all invitations for this team.
     *
     * @return HasMany<Invitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class, 'team_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_personal' => 'boolean',
            'role_permissions' => 'array',
        ];
    }

    /**
     * Get the customized permissions for a given role, falling back to defaults.
     *
     * @return array<TeamPermission>
     */
    public function getRolePermissions(TeamRole $role): array
    {
        if ($role === TeamRole::Owner) {
            // Owner always has all permissions
            return \App\Enums\TeamPermission::cases();
        }

        $customPermissions = $this->role_permissions[$role->value] ?? null;

        if ($customPermissions !== null && is_array($customPermissions)) {
            // Map custom strings to Enum cases
            return collect($customPermissions)
                ->map(fn ($permStr) => \App\Enums\TeamPermission::tryFrom($permStr))
                ->filter()
                ->values()
                ->toArray();
        }

        // Fallback to defaults
        return $role->permissions();
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
