@extends('layouts.practice')

@section('title', $practice['label'])

@section('content')
{{-- ================= 開始画面 ================= --}}
<section class="screen screen--center" id="screen-start" data-screen="start">
    <h1 class="title">{{ $practice['label'] }}</h1>
    {{-- 練習前のかんたんな説明。操作のしかたと、難易度ごとの進め方を伝える。 --}}
    <p class="lead">{!! $practice['lead'] !!}</p>
    <p class="lead lead--note">{!! $setting['note'] !!}</p>
    <div class="button-stack">
        <button type="button" class="btn btn--primary" id="btn-start">始める</button>
        {{-- 練習を選び直す場合は難易度選択画面へ戻る --}}
        <a class="btn btn--quiet" id="btn-exit-start" href="{{ route('practice.difficulty', ['type' => $type]) }}">練習を選び直す</a>
    </div>
</section>

{{-- ================= 練習画面 ================= --}}
<section class="screen screen--play is-hidden" id="screen-play" data-screen="play">
    <header class="play-header">
        <button type="button" class="btn btn--pause" id="btn-pause">中断する</button>
        <div class="status">
            @if ($requiredCount > 0)
                {{-- 難易度「やさしい」は制限時間ではなく残り回数で終わる --}}
                <div class="status__item">
                    <span class="status__label">のこり</span>
                    <span class="status__value" id="count-value">{{ $requiredCount }}かい</span>
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

    {{-- アイコンはこの領域（画面中央〜下部）の中だけに出現する --}}
    <div class="play-field" id="play-field" aria-live="off">
        <button type="button" class="target target--{{ $type }} is-hidden" id="target" aria-label="{{ $practice['target_label'] }}">
            @if (in_array($type, ['swipe', 'flick'], true))
                <span class="target__arrow" aria-hidden="true"></span>
            @endif
        </button>
        @if ($hasDecoy)
            {{-- 操作してはいけないアイコン。ただしいアイコンと必ず一緒に出す。 --}}
            <button type="button" class="target target--{{ $type }} target--decoy is-hidden" id="decoy" aria-label="さわってはいけません">
                @if (in_array($type, ['swipe', 'flick'], true))
                    <span class="target__arrow" aria-hidden="true"></span>
                @endif
            </button>
        @endif
    </div>
</section>

{{-- ================= 中断画面 ================= --}}
<section class="screen screen--center screen--overlay is-hidden" id="screen-pause" data-screen="pause">
    <h2 class="title">中断中</h2>
    <div class="button-stack">
        <button type="button" class="btn btn--primary" id="btn-resume">再開する</button>
        {{-- 練習を選び直す場合は難易度選択画面へ戻る --}}
        <a class="btn btn--quiet" id="btn-quit" href="{{ route('practice.difficulty', ['type' => $type]) }}">練習を選び直す</a>
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
    // Laravel 側で持っている設定値を JavaScript に渡す
    window.PRACTICE_CONFIG = {
        type: @json($type),
        level: @json($level),
        timeLimitSeconds: {{ (int) $timeLimitSeconds }},
        requiredCount: {{ (int) $requiredCount }},
        hasDecoy: {{ $hasDecoy ? 'true' : 'false' }},
        holdMs: {{ (int) $holdMs }},
        scoreDigits: {{ (int) $scoreDigits }},
    };
</script>
<script src="{{ asset('js/practice-play.js') }}"></script>
@endpush
