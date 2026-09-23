@extends('layouts.practice')

@section('title', $label)

@push('styles')
<link rel="stylesheet" href="{{ asset('css/typing.css') }}">
@endpush

@section('content')
{{-- ================= 開始画面 ================= --}}
<section class="screen screen--center" id="screen-start" data-screen="start">
    <h1 class="title">{{ $label }}</h1>
    {{-- 練習前のかんたんな説明。操作のしかたと、難易度ごとの進め方を伝える。 --}}
    <p class="lead">画面に出てくる ことばを<br>下の わくに 入力してください</p>
    <p class="lead lead--note">{!! $setting['note'] !!}</p>
    <div class="button-stack">
        <button type="button" class="btn btn--primary" id="btn-start">始める</button>
        {{-- 練習を選び直す場合は難易度選択画面へ戻る --}}
        <a class="btn btn--quiet" id="btn-exit-start" href="{{ route('practice.typing.difficulty') }}">練習を選び直す</a>
    </div>
</section>

{{-- ================= 練習画面 ================= --}}
<section class="screen screen--play is-hidden" id="screen-play" data-screen="play">
    <header class="play-header">
        <button type="button" class="btn btn--pause" id="btn-pause">中断する</button>
        <div class="status">
            @if ($requiredCount > 0)
                {{-- 難易度「やさしい」は制限時間ではなく残り問題数で終わる --}}
                <div class="status__item">
                    <span class="status__label">のこり</span>
                    <span class="status__value" id="count-value">{{ $requiredCount }}もん</span>
                </div>
            @else
                <div class="status__item">
                    <span class="status__label">制限時間</span>
                    <span class="status__value" id="time-value">03:00</span>
                </div>
            @endif
            <div class="status__item">
                <span class="status__label">得点</span>
                <span class="status__value" id="score-value">000点</span>
            </div>
        </div>
    </header>

    {{-- お題・入力欄・回答ボタンを縦に並べる。キーボードは端末のものを使う。 --}}
    <div class="typing-field" id="typing-field">
        <p class="typing-prompt" id="typing-prompt" aria-live="polite">　</p>
        @if ($isConversion)
            {{-- 「むずかしい」だけ、入力してほしい表記を読みがなの下に添える --}}
            <p class="typing-target" id="typing-target"></p>
        @endif

        {{-- 端末に設定されているソフトウェアキーボード（Gboard など）をそのまま使う --}}
        <label class="typing-input-label" for="typing-input">
            <span class="is-visually-hidden">こたえを入力してください</span>
            <input
                class="typing-input"
                id="typing-input"
                type="text"
                inputmode="text"
                lang="ja"
                autocomplete="off"
                autocorrect="off"
                autocapitalize="off"
                spellcheck="false"
                enterkeyhint="done">
        </label>

        <button type="button" class="btn btn--primary typing-answer" id="btn-answer">回答です</button>

        {{-- まちがい・ヒントはこの1か所にまとめて出し、画面が動かないようにする --}}
        <p class="typing-message" id="typing-message" aria-live="polite"></p>
        @if ($hasHint)
            <div class="typing-hint is-hidden" id="typing-hint" aria-live="polite"></div>
        @endif
    </div>
</section>

{{-- ================= 中断画面 ================= --}}
<section class="screen screen--center screen--overlay is-hidden" id="screen-pause" data-screen="pause">
    <h2 class="title">中断中</h2>
    <div class="button-stack">
        <button type="button" class="btn btn--primary" id="btn-resume">再開する</button>
        {{-- 練習を選び直す場合は難易度選択画面へ戻る --}}
        <a class="btn btn--quiet" id="btn-quit" href="{{ route('practice.typing.difficulty') }}">練習を選び直す</a>
    </div>
</section>

{{-- ================= 結果画面 ================= --}}
<section class="screen screen--center is-hidden" id="screen-result" data-screen="result">
    <h2 class="title">結果</h2>
    <p class="result-score" id="result-score">000点</p>
    <div class="button-row">
        <button type="button" class="btn btn--primary" id="btn-again">もう一度練習する</button>
    </div>
    {{-- 練習を選び直す場合はメニュー画面へ戻る --}}
    <div class="button-stack button-stack--tight">
        <a class="btn btn--quiet" href="{{ route('practice.menu') }}">練習を選び直す</a>
    </div>
</section>
@endsection

@push('scripts')
<script>
    // Laravel 側で持っている設定値と出題内容を JavaScript に渡す
    window.TYPING_CONFIG = {
        level: @json($level),
        timeLimitSeconds: {{ (int) $timeLimitSeconds }},
        requiredCount: {{ (int) $requiredCount }},
        hasHint: {{ $hasHint ? 'true' : 'false' }},
        isConversion: {{ $isConversion ? 'true' : 'false' }},
        hintIntervalMs: {{ (int) $hintIntervalMs }},
        scoreDigits: {{ (int) $scoreDigits }},
        questions: @json($questions),
    };
</script>
<script src="{{ asset('js/typing-play.js') }}"></script>
@endpush
