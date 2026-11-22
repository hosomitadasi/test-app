<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * 文字入力の練習（文字打ち練習・チャット型練習）で出す問題。
 */
class Question extends Model
{
    /** 文字打ち練習の問題 */
    public const TYPE_TYPING = 'typing';

    /** チャット型練習の問題 */
    public const TYPE_CHAT = 'chat';

    protected $fillable = [
        'practice_type',
        'is_conversion',
        'text',
        'answer',
        'answer_format',
    ];

    protected $casts = [
        'is_conversion' => 'boolean',
    ];

    /**
     * 練習の種類と、変換を求めるかどうかで絞り込む。
     */
    public function scopeForPractice(Builder $query, string $practiceType, bool $isConversion): Builder
    {
        return $query
            ->where('practice_type', $practiceType)
            ->where('is_conversion', $isConversion);
    }
}
