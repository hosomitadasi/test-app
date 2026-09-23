@extends('layouts.practice')

@section('title', '今日の練習')

@section('content')
<section class="screen screen--menu" data-screen="menu">
    {{-- メニュー画面より前の画面は存在しないため、遷移先は設定していない --}}
    <div class="menu-top">
        <button type="button" class="btn btn--quiet">前の画面に戻る</button>
    </div>

    <div class="menu-body">
        <h1 class="title">今日の練習</h1>
        <p class="lead">文字の部分を押してください</p>

        {{-- ①〜③の遷移先は後日実装するため、現時点では href を設定しない --}}
        <nav class="menu-list">
            <a class="menu-list__item">①○○の練習</a>
            <a class="menu-list__item">②○○の練習</a>
            <a class="menu-list__item">③○○の練習</a>
        </nav>
    </div>

    <div class="menu-bottom">
        <a class="btn btn--primary" href="{{ route('practice.contents') }}">さらに他の練習をする方はこちら！</a>
    </div>
</section>
@endsection
