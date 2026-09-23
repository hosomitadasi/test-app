<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * トップ画面。ログインしているかどうかで、出すボタンを切り替える（画面側で判定する）。
 */
class TopController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Top');
    }
}
