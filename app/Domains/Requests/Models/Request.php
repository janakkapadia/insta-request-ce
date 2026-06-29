<?php

namespace App\Domains\Requests\Models;

use App\Domains\Collections\Models\Collection;
use App\Domains\Collections\Models\CollectionFolder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;
use App\Models\User;

class Request extends Model
{
    use HasFactory, HasUuids, SoftDeletes, Prunable;

    protected $fillable = [
        'collection_id',
        'folder_id',
        'user_id',
        'name',
        'description',
        'method',
        'url',
        'headers',
        'query_params',
        'path_variables',
        'body',
        'pre_request_script',
        'test_script',
        'auth',
    ];

    protected $casts = [
        'headers' => 'array',
        'query_params' => 'array',
        'path_variables' => 'array',
        'body' => 'array',
    ];

    /**
     * Get and set request auth credentials with secure AES-256 encryption.
     * Backwards-compatible with unencrypted records.
     */
    protected function auth(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if (empty($value)) {
                    return [];
                }

                try {
                    $decrypted = Crypt::decryptString($value);
                    return json_decode($decrypted, true) ?: [];
                } catch (DecryptException $e) {
                    // Fallback for existing unencrypted JSON records
                    $decoded = json_decode($value, true);
                    return is_array($decoded) ? $decoded : [];
                }
            },
            set: function ($value) {
                if (empty($value)) {
                    return null;
                }

                $jsonString = is_array($value) ? json_encode($value) : $value;
                return Crypt::encryptString($jsonString);
            }
        );
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(CollectionFolder::class, 'folder_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function prunable(): Builder
    {
        return static::onlyTrashed()->where('deleted_at', '<=', now()->subDays(30));
    }
}
