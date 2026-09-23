@extends('layouts.practice')

@section('title', '難易度を選ぶ')

@section('content')
<section class="screen screen--center" data-screen="typing-difficulty">
    <h1 class="title title--small">{{ $label }}</h1>
    <div class="button-stack">
        {{-- 文字打ち練習は練習詳細画面を経由しないため、練習内容選択画面へ戻る --}}
        <a class="btn btn--quiet" href="{{ route('practice.contents') }}">練習を選び直す</a>
        {{-- やさしい／ふつう／むずかしい は、いずれも同じ練習画面へ難易度付きで進む --}}
        @foreach ($levels as $level => $setting)
            <a class="btn btn--primary" href="{{ route('practice.typing.play', ['level' => $level]) }}">{{ $setting['label'] }}</a>
        @endforeach
    </div>
</section>
@endsection
