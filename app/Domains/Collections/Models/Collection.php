<?php

namespace App\Domains\Collections\Models;

use App\Domains\Requests\Models\Request;
use App\Domains\Teams\Models\Team;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

use App\Models\User;

class Collection extends Model
{
    use HasFactory, HasUuids, SoftDeletes, Prunable;

    protected $fillable = [
        'team_id',
        'user_id',
        'name',
        'description',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function folders(): HasMany
    {
        return $this->hasMany(CollectionFolder::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(Request::class);
    }

    public function prunable(): Builder
    {
        return static::onlyTrashed()->where('deleted_at', '<=', now()->subDays(30));
    }
}
