<?php

namespace App\Http\Requests\Auth;

/**
 * 高齢の利用者が入力しやすいよう、全角で入った英数字や空白をそろえる。
 */
trait NormalizesInput
{
    /** 全角の英数字・記号を半角にし、空白を取り除く（メールアドレス・確認番号向け） */
    protected function toHalfWidth(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        return preg_replace('/[\s　]+/u', '', mb_convert_kana($value, 'as'));
    }
}
