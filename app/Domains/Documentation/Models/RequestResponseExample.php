<?php

namespace App\Domains\Documentation\Models;

use App\Domains\Requests\Models\Request;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestResponseExample extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'request_response_examples';

    protected $fillable = [
        'request_id',
        'name',
        'status_code',
        'headers',
        'body',
    ];

    protected $casts = [
        'headers' => 'array',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class);
    }
}
