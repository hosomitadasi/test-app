<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    {{-- 高齢者の連打によるダブルタップ拡大を防ぐため、拡大操作を無効にする --}}
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="#f7f4ec">
    {{-- 練習画面のスタイルは、Blade で作っていたころのものをそのまま使う --}}
    <link rel="stylesheet" href="{{ asset('css/practice.css') }}">
    <link rel="stylesheet" href="{{ asset('css/typing.css') }}">
    @vite('resources/js/app.js')
    @inertiaHead
</head>
<body>
@inertia
</body>
</html>
