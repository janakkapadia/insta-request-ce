<?php

namespace App\Domains\Environments\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnvironmentVariable extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'environment_id',
        'key',
        'value',
        'enabled',
        'type',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }
}
