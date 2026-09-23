<?php

namespace App\Http\Controllers;

use App\Services\GrowthService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 成長の記録画面と、これまでの記録画面。
 */
class GrowthController extends Controller
{
    public function __construct(private readonly GrowthService $growth)
    {
    }

    public function index(Request $request): Response
    {
        return Inertia::render('Growth/Index', $this->growth->summary($request->user()));
    }

    public function history(Request $request): Response
    {
        return Inertia::render('Growth/History', $this->growth->history($request->user()));
    }
}
