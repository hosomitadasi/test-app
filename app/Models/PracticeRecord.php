<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 1回ごとの練習結果。
 */
class PracticeRecord extends Model
{
    protected $fillable = [
        'practice_type',
        'level',
        'score',
        'played_at',
    ];

    protected $casts = [
        'score' => 'integer',
        'played_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
