<?php

namespace App\Domains\History\Models;

use App\Domains\Requests\Models\Request;
use App\Domains\Teams\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestHistory extends Model
{
    use HasFactory, HasUuids, MassPrunable;

    protected $fillable = [
        'team_id',
        'user_id',
        'request_id',
        'method',
        'url',
        'status',
        'time_ms',
        'size_bytes',
        'request_payload',
        'response_meta',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_meta' => 'array',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class);
    }

    /**
     * Get the prunable model query.
     */
    public function prunable(): Builder
    {
        return static::where('created_at', '<=', now()->subHours(24));
    }
}
