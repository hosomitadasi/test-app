<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * メールで送った確認番号。
 */
class VerificationCode extends Model
{
    public const PURPOSE_REGISTER = 'register';
    public const PURPOSE_PASSWORD_RESET = 'password_reset';

    protected $fillable = [
        'email',
        'purpose',
        'name',
        'code_hash',
        'link_token_hash',
        'attempts',
        'expires_at',
        'verified_at',
        'link_used_at',
    ];

    protected $hidden = [
        'code_hash',
        'link_token_hash',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'link_used_at' => 'datetime',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
