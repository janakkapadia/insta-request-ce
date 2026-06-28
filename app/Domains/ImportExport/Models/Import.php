<?php

namespace App\Domains\ImportExport\Models;

use App\Domains\Collections\Models\Collection;
use App\Domains\Teams\Models\Team;
use App\Enums\ImportFormat;
use App\Enums\ImportStatus;
use App\Enums\MergeStrategy;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Import extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'team_id',
        'user_id',
        'source_format',
        'original_filename',
        'file_hash',
        'status',
        'target_collection_id',
        'merge_strategy',
        'summary',
        'validation_report',
        'parsed_data',
        'error_message',
    ];

    protected $casts = [
        'source_format' => ImportFormat::class,
        'status' => ImportStatus::class,
        'merge_strategy' => MergeStrategy::class,
        'summary' => 'array',
        'validation_report' => 'array',
        'parsed_data' => 'array',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function targetCollection(): BelongsTo
    {
        return $this->belongsTo(Collection::class, 'target_collection_id');
    }
}
