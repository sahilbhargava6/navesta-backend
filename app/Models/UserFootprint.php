<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserFootprint extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'event_type',
        'ip_address',
        'user_agent',
        'device_type',
        'browser',
        'os',
        'fingerprint_hash',
        'session_id',
        'country_code',
        'city',
        'cookie_consent',
        'headers_snapshot',
    ];

    protected $casts = [
        'cookie_consent' => 'array',
        'headers_snapshot' => 'array',
        'created_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
