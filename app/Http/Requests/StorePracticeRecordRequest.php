<?php

namespace App\Http\Requests;

use App\Http\Controllers\PracticeController;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 練習をやり終えたときに、結果を記録する。
 */
class StorePracticeRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'practice_type' => ['required', 'string', Rule::in(array_keys(config('growth.practices')))],
            'level' => ['required', 'string', Rule::in(PracticeController::LEVELS)],
            // 得点は3桁（999点）まで。画面の表示桁数と揃える。
            'score' => ['required', 'integer', 'min:0', 'max:999'],
        ];
    }
}
