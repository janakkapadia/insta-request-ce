<?php

namespace App\Domains\Collections\Models;

use App\Domains\Requests\Models\Request;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CollectionFolder extends Model
{
    use HasFactory, HasUuids, Prunable, SoftDeletes;

    protected $fillable = [
        'collection_id',
        'user_id',
        'parent_id',
        'name',
        'description',
    ];

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(CollectionFolder::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(CollectionFolder::class, 'parent_id');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(Request::class, 'folder_id');
    }

    public function prunable(): Builder
    {
        return static::onlyTrashed()->where('deleted_at', '<=', now()->subDays(30));
    }
}
