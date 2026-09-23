@extends('layouts.practice')

@section('title', '難易度を選ぶ')

@section('content')
<section class="screen screen--center" data-screen="difficulty">
    <h1 class="title title--small">{{ $practice['label'] }}</h1>
    <div class="button-stack">
        <a class="btn btn--quiet" href="{{ route('practice.details') }}">練習を選び直す</a>
        {{-- やさしい／ふつう／むずかしい は、いずれも同じ練習画面へ難易度付きで進む --}}
        @foreach ($levels as $level => $setting)
            <a class="btn btn--primary" href="{{ route('practice.play', ['type' => $type, 'level' => $level]) }}">{{ $setting['label'] }}</a>
        @endforeach
    </div>
</section>
@endsection
