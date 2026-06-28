<?php

namespace App\Domains\ImportExport\Models;

use App\Domains\Collections\Models\Collection;
use App\Domains\Teams\Models\Team;
use App\Enums\ExportFormat;
use App\Enums\ExportStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Export extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'team_id',
        'user_id',
        'target_format',
        'collection_id',
        'filename',
        'status',
        'file_path',
        'error_message',
    ];

    protected $casts = [
        'target_format' => ExportFormat::class,
        'status' => ExportStatus::class,
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }
}
