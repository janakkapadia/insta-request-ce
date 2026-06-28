<?php

namespace App\Domains\Documentation\Models;

use App\Domains\Collections\Models\Collection;
use App\Domains\Teams\Models\Team;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectionDocumentation extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'collection_documentations';

    protected $fillable = [
        'collection_id',
        'team_id',
        'is_public',
        'public_slug',
        'version',
        'markdown_intro',
        'auth_info',
        'settings',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'settings' => 'array',
    ];

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
