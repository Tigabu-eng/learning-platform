@extends('layouts.app', ['title' => 'Results: '.$lesson->title])
@section('content')
@php
    $answers = $attempt->answers ?? [];
    $results = $attempt->results ?? [];
    $reveal = $quiz->show_answers;
    $enrollment = $attempt->enrollment;
    $certificate = $enrollment->certificate;
@endphp
<div class="mx-auto max-w-3xl">
    <a href="{{ route('learn.lesson', [$course, $lesson]) }}" class="-my-2 inline-block py-2 text-sm font-semibold text-muted hover:text-ink">← Back to {{ $lesson->title }}</a>

    <div @class(['celebrate mt-4 rounded-xl border p-8', 'border-pine/30 bg-pine-soft' => $attempt->passed, 'border-brick/25 bg-brick-soft' => ! $attempt->passed])>
        <div class="flex flex-wrap items-end justify-between gap-6">
            <div>
                <div class="text-sm font-semibold {{ $attempt->passed ? 'text-pine-dark' : 'text-brick' }}">{{ $attempt->passed ? 'Passed' : 'Not passed yet' }}</div>
                <div class="mt-1 text-6xl font-extrabold tabular-nums">{{ $attempt->score }}%</div>
                <div class="mt-2 text-sm">{{ $attempt->points_earned }} of {{ $attempt->points_total }} points · pass mark {{ $quiz->passing_score }}%</div>
            </div>
            <div class="flex flex-wrap gap-2">
                @if ($certificate && $quiz->is_final && $attempt->passed)
                    <a href="{{ route('certificates.show', $certificate) }}" class="btn-accent">View your certificate</a>
                @endif
                <a href="{{ route('learn.lesson', [$course, $lesson]) }}" class="btn-ghost">{{ $attempt->passed ? 'Continue course' : 'See attempts left' }}</a>
            </div>
        </div>
        @if ($quiz->is_final && $attempt->passed && ! $certificate && $course->certificate_enabled)
            <p class="mt-4 text-sm">Complete the remaining lessons and your certificate will be issued automatically.</p>
        @endif
    </div>

    <h2 class="mt-10 text-xl font-bold">{{ $reveal ? 'Review your answers' : 'Your answers' }}</h2>
    @unless ($reveal)<p class="mt-1 text-sm text-muted">Your instructor has chosen not to show correct answers for this {{ $quiz->is_final ? 'exam' : 'quiz' }}.</p>@endunless
    <ol class="mt-5 space-y-4">
        @foreach ($questions as $i => $q)
            @php $ok = $results[$q->id] ?? false; $a = $answers[$q->id] ?? null; $given = array_map('strval', (array) $a); @endphp
            <li class="panel p-6">
                <div class="flex items-center justify-between text-xs">
                    <span class="text-muted">Question {{ $i + 1 }}</span>
                    @if ($ok)<span class="badge-green">Correct</span>@else<span class="badge-red">{{ $a === null || $a === '' || $a === [] ? 'Not answered' : 'Incorrect' }}</span>@endif
                </div>
                <div class="prose-question mt-2 font-semibold">{{ \App\Support\RichText::html($q->prompt, false) }}</div>
                @if ($q->type === 'short')
                    <p class="mt-3 text-sm">Your answer: <span class="font-semibold">{{ is_string($a) && $a !== '' ? $a : '—' }}</span></p>
                    @if ($reveal && ! $ok)<p class="mt-1 text-sm text-pine-dark">Accepted: {{ implode(', ', array_filter(preg_split('/\r?\n/', $q->accepted_answers))) }}</p>@endif
                @else
                    <ul class="mt-3 space-y-1.5 text-sm">
                        @foreach ($q->options as $o)
                            @php $picked = in_array((string) $o->id, $given, true); @endphp
                            <li @class([
                                'flex items-center gap-2 rounded-md px-3 py-2',
                                'bg-pine-soft text-pine-dark font-semibold' => $reveal && $o->is_correct,
                                'bg-brick-soft text-brick' => $picked && ! $o->is_correct && $reveal,
                                'bg-mist' => $picked && ! $reveal,
                            ])>
                                <span class="w-4">{{ $picked ? '●' : '○' }}</span>
                                <span class="flex-1">{{ $o->text }}</span>
                                @if ($reveal && $o->is_correct)<span class="text-xs">Correct answer</span>@endif
                            </li>
                        @endforeach
                    </ul>
                @endif
                @if ($reveal && $q->explanation)
                    <div class="prose-question mt-4 border-l-4 border-saffron bg-saffron-soft/50 px-3 py-2 text-sm">{{ \App\Support\RichText::html($q->explanation, false) }}</div>
                @endif
            </li>
        @endforeach
    </ol>
</div>
@endsection
