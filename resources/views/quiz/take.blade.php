<!DOCTYPE html>
<html lang="en">
<head>@include('partials.head', ['title' => $lesson->title])</head>
<body class="min-h-screen bg-mist">
@php $answers = $attempt->answers ?? []; @endphp
<header class="sticky top-0 z-20 border-b border-line bg-white">
    <div class="mx-auto flex h-14 max-w-3xl items-center justify-between gap-4 px-4">
        <div class="min-w-0">
            <div class="truncate text-sm font-bold">{{ $lesson->title }}</div>
            <div class="text-xs text-muted"><span data-answered>0</span> of {{ $questions->count() }} answered</div>
        </div>
        @if ($attempt->expires_at)
            <div data-timer-box class="flex items-center gap-1.5 rounded-full border border-line px-3 py-1 text-sm font-bold tabular-nums" aria-live="off">
                <svg viewBox="0 0 20 20" class="size-4" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="10" cy="11" r="6.5"/><path d="M10 7.5V11l2 1.5M8 2.5h4"/></svg>
                <span data-timer>--:--</span>
            </div>
        @endif
    </div>
</header>

<main class="mx-auto max-w-3xl px-4 py-8">
    @if ($quiz->is_final)
        <div class="mb-6 rounded-lg border border-saffron/50 bg-saffron-soft px-4 py-3 text-sm text-[#7a5408]">Final exam · pass mark {{ $quiz->passing_score }}%. Your answers save as you go{{ $attempt->expires_at ? ', and the exam submits itself when time runs out' : '' }}.</div>
    @endif

    <nav class="mb-6 flex flex-wrap gap-1.5" aria-label="Jump to question">
        @foreach ($questions as $i => $q)
            <a href="#q{{ $q->id }}" data-qnav="{{ $q->id }}" class="grid size-8 place-items-center rounded-md border border-line bg-white text-xs font-semibold">{{ $i + 1 }}</a>
        @endforeach
    </nav>

    <form method="POST" action="{{ route('attempts.submit', $attempt) }}" data-quiz-form
          data-autosave-url="{{ route('attempts.autosave', $attempt) }}"
          data-total="{{ $questions->count() }}"
          @if ($attempt->expires_at) data-seconds-left="{{ $attempt->secondsLeft() }}" @endif>
        @csrf
        <ol class="space-y-5">
            @foreach ($questions as $i => $q)
                @php $a = $answers[$q->id] ?? null; @endphp
                <li id="q{{ $q->id }}" class="panel scroll-mt-20 p-6">
                    <fieldset>
                        <legend class="w-full">
                            <div class="flex items-center justify-between text-xs text-muted">
                                <span>Question {{ $i + 1 }}</span>
                                <span>{{ $q->points }} {{ \Illuminate\Support\Str::plural('point', $q->points) }}@if ($q->type === 'multiple') · select all that apply @endif</span>
                            </div>
                            <div class="prose-question mt-2 text-lg font-semibold leading-snug">{{ \App\Support\RichText::html($q->prompt, false) }}</div>
                        </legend>
                        <div class="mt-4 space-y-2">
                            @if ($q->type === 'short')
                                <input type="text" name="answers[{{ $q->id }}]" value="{{ is_string($a) ? $a : '' }}" class="input" placeholder="Type your answer" autocomplete="off">
                            @else
                                @foreach ($q->options as $o)
                                    @php $checked = $q->type === 'multiple' ? in_array((string) $o->id, array_map('strval', (array) $a), true) : (string) $a === (string) $o->id; @endphp
                                    <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-line px-4 py-3 has-[:checked]:border-pine has-[:checked]:bg-pine-soft">
                                        <input type="{{ $q->type === 'multiple' ? 'checkbox' : 'radio' }}" name="answers[{{ $q->id }}]{{ $q->type === 'multiple' ? '[]' : '' }}" value="{{ $o->id }}" @checked($checked) class="check">
                                        <span>{{ $o->text }}</span>
                                    </label>
                                @endforeach
                            @endif
                        </div>
                    </fieldset>
                </li>
            @endforeach
        </ol>
        <div class="mt-8 flex items-center justify-between">
            <a href="{{ route('learn.lesson', [$course, $lesson]) }}" data-save-leave class="text-sm font-semibold text-muted hover:text-ink">Save and leave</a>
            <button class="{{ $quiz->is_final ? 'btn-accent' : 'btn-primary' }} px-6">Submit {{ $quiz->is_final ? 'exam' : 'quiz' }}</button>
        </div>
    </form>
</main>
@include('partials.toast')
</body>
</html>
