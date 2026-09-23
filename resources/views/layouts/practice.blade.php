<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    {{-- 高齢者の連打によるダブルタップ拡大を防ぐため、拡大操作を無効にする --}}
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="#f7f4ec">
    <title>@yield('title')</title>
    <link rel="stylesheet" href="{{ asset('css/practice.css') }}">
    {{-- 練習ごとに追加のスタイルが必要な場合はここへ差し込む --}}
    @stack('styles')
</head>
<body>

{{-- Android 端末を想定した 700x840px の枠。画面はすべてこの枠の中に収める。 --}}
<div class="app-frame">
@yield('content')
</div>

@stack('scripts')
</body>
</html>
