<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChirpLike extends Model
{
    protected $fillable = [
        'user_id',
        'session_id',
    ];

    public function chirp(): BelongsTo
    {
        return $this->belongsTo(Chirp::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
