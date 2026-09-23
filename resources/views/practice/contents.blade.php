@extends('layouts.practice')

@section('title', '練習を選ぶ')

@section('content')
<section class="screen screen--center" data-screen="contents">
    <div class="button-stack">
        <a class="btn btn--quiet" href="{{ route('practice.menu') }}">練習を選び直す</a>
        <a class="btn btn--primary" href="{{ route('practice.details') }}">画面操作の練習をする</a>
        <a class="btn btn--primary" href="{{ route('practice.typing.difficulty') }}">文字入力の練習をする</a>
        {{-- 電話の練習は後日実装するため、遷移は設定しない --}}
        <button type="button" class="btn btn--primary">電話の練習をする</button>
    </div>
</section>
@endsection
