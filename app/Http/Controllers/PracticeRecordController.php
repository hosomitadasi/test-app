<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePracticeRecordRequest;
use App\Services\GrowthService;
use Illuminate\Http\JsonResponse;

/**
 * 練習をやり終えたときに、結果を記録する。
 * 練習画面は画面を切り替えずに結果を出すため、JSON で返す。
 */
class PracticeRecordController extends Controller
{
    public function store(StorePracticeRecordRequest $request, GrowthService $growth): JsonResponse
    {
        return response()->json($growth->record($request->user(), $request->validated()), 201);
    }
}
