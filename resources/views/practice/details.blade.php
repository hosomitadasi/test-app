@extends('layouts.practice')

@section('title', '画面操作の練習を選ぶ')

@section('content')
<section class="screen screen--center" data-screen="details">
    <div class="button-stack">
        <a class="btn btn--quiet" href="{{ route('practice.contents') }}">練習を選び直す</a>
        {{-- どの練習も、まず難易度選択画面へ進む --}}
        @foreach ($practices as $type => $practice)
            <a class="btn btn--primary" href="{{ route('practice.difficulty', ['type' => $type]) }}">{{ $practice['menu_label'] }}</a>
        @endforeach
    </div>
</section>
@endsection
