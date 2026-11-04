@extends('layouts.studio', ['title' => $lesson->title])
@section('breadcrumb')<a href="{{ route('studio.courses.index') }}" class="hover:text-ink">Courses</a> / <a href="{{ route('studio.curriculum', $course) }}" class="hover:text-ink">{{ $course->title }}</a> / {{ $lesson->title }} @endsection
@section('content')
@php $quiz = $lesson->quiz; $noun = $lesson->type === 'exam' ? 'exam' : 'quiz'; @endphp
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <div class="flex items-center gap-2 text-sm text-muted">@include('partials.lesson-icon', ['type' => $lesson->type]) {{ $lesson->typeLabel() }} lesson</div>
    <div class="flex gap-2">
        <a href="{{ route('learn.lesson', [$course, $lesson]) }}" class="btn-ghost btn-sm" target="_blank" rel="noopener">Preview</a>
        <form method="POST" action="{{ route('studio.lessons.destroy', $lesson) }}" data-confirm="Delete this lesson? Student progress on it will be removed.">@csrf @method('DELETE')<button class="btn-danger btn-sm">Delete lesson</button></form>
    </div>
</div>

<form method="POST" action="{{ route('studio.lessons.update', $lesson) }}" enctype="multipart/form-data" class="grid max-w-6xl gap-6 lg:grid-cols-[1fr_19rem]">
    @csrf @method('PUT')
    <div class="space-y-6">
        <section class="panel space-y-5 p-6">
            <div><label class="label" for="title">Lesson title</label><input id="title" name="title" class="input text-base font-semibold" value="{{ old('title', $lesson->title) }}" required></div>

            @if ($lesson->type === 'video')
                <div><label class="label" for="video_url">Video link</label><input id="video_url" name="video_url" type="url" class="input" value="{{ old('video_url', $lesson->video_url) }}" placeholder="https://www.youtube.com/watch?v=… or https://vimeo.com/…"><p class="hint">YouTube, Vimeo, or a direct link to an .mp4 file.</p></div>
                <div><label class="label" for="video">…or upload a video</label>
                    @if ($lesson->video_path)<p class="mb-2 text-sm">Uploaded video attached. <label class="ml-2 inline-flex items-center gap-1 text-brick"><input type="checkbox" name="remove_video" value="1" class="check"> Remove</label></p>@endif
                    <input id="video" name="video" type="file" accept="video/mp4,video/webm,video/quicktime" class="text-sm"><p class="hint">MP4 or WebM, up to 500 MB. An uploaded video is used when no link is set.</p></div>
            @endif

            <div>
                <label class="label" for="content">{{ $lesson->isAssessment() ? 'Instructions shown before the '.$noun : ($lesson->type === 'video' ? 'Notes below the video' : 'Lesson content') }}</label>
                <textarea id="content" name="content" rows="{{ $lesson->type === 'text' ? 18 : 6 }}" class="input leading-relaxed">{{ old('content', $lesson->content) }}</textarea>
                <p class="hint">Markdown: ## heading, **bold**, *italic*, - list, 1. list, > callout, [link](https://…), ![image](https://…)</p>
            </div>

            @if (in_array($lesson->type, ['download', 'text', 'video']))
                <div><label class="label" for="attachment">{{ $lesson->type === 'download' ? 'File to download' : 'Attachment (optional)' }}</label>
                    @if ($lesson->file_name)<p class="mb-2 text-sm">Current file: <span class="font-semibold">{{ $lesson->file_name }}</span></p>@endif
                    <input id="attachment" name="attachment" type="file" class="text-sm"><p class="hint">PDF, slides, worksheets — up to 100 MB. Only enrolled students can download it.</p></div>
            @endif
        </section>

        @if ($quiz)
            <section class="panel space-y-5 p-6">
                <h2 class="font-bold">{{ ucfirst($noun) }} settings</h2>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div><label class="label" for="passing_score">Pass mark (%)</label><input id="passing_score" name="passing_score" type="number" min="0" max="100" class="input" value="{{ old('passing_score', $quiz->passing_score) }}"></div>
                    <div><label class="label" for="max_attempts">Attempts allowed</label><input id="max_attempts" name="max_attempts" type="number" min="1" class="input" value="{{ old('max_attempts', $quiz->max_attempts) }}" placeholder="Unlimited"></div>
                    <div><label class="label" for="time_limit_minutes">Time limit (min)</label><input id="time_limit_minutes" name="time_limit_minutes" type="number" min="1" class="input" value="{{ old('time_limit_minutes', $quiz->time_limit_minutes) }}" placeholder="No limit"></div>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="shuffle_questions" value="1" class="check" @checked($quiz->shuffle_questions)> Shuffle question order</label>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="shuffle_options" value="1" class="check" @checked($quiz->shuffle_options)> Shuffle answer choices</label>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="show_answers" value="1" class="check" @checked($quiz->show_answers)> Show correct answers after submitting</label>
                    @if ($quiz->is_final)<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="require_all_lessons" value="1" class="check" @checked($quiz->require_all_lessons)> Unlock only after all lessons are done</label>@endif
                </div>
            </section>
        @endif
    </div>

    <aside class="space-y-6">
        <section class="panel space-y-5 p-6">
            <div><label class="label" for="chapter_id">Chapter</label>
                <select id="chapter_id" name="chapter_id" class="input">@foreach ($course->chapters as $ch)<option value="{{ $ch->id }}" @selected($ch->id === $lesson->chapter_id)>{{ $ch->title }}</option>@endforeach</select></div>
            <div><label class="label" for="duration_minutes">Length (minutes)</label><input id="duration_minutes" name="duration_minutes" type="number" min="0" class="input" value="{{ old('duration_minutes', $lesson->duration_minutes) }}"></div>
            @if ($course->drip_enabled)
                <div><label class="label" for="drip_days">Opens after (days)</label><input id="drip_days" name="drip_days" type="number" min="0" class="input" value="{{ old('drip_days', $lesson->drip_days) }}" placeholder="Right away"><p class="hint">Days after the student enrolls.</p></div>
            @endif
            <label class="flex gap-3"><input type="checkbox" name="is_preview" value="1" class="check mt-0.5" @checked($lesson->is_preview)><span><span class="block text-sm font-semibold">Free preview</span><span class="text-xs text-muted">Anyone with an account can open it.</span></span></label>
        </section>
        <button class="btn-primary w-full">Save lesson</button>
    </aside>
</form>

@if ($quiz)
    <section class="mt-10 max-w-6xl">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-xl font-bold">Questions</h2>
                <p class="text-sm text-muted">{{ $quiz->questions->count() }} questions · {{ $quiz->questions->sum('points') }} points. Drag to reorder.</p>
            </div>
        </div>
        <ol data-questions data-reorder-url="{{ route('studio.questions.reorder', $lesson) }}" class="mt-4 space-y-3">
            @foreach ($quiz->questions as $i => $question)
                <li data-question="{{ $question->id }}" id="q{{ $question->id }}" class="panel scroll-mt-20">
                    <details @if (old('_qid') == $question->id) open @endif>
                        <summary class="flex cursor-pointer list-none items-start gap-3 px-4 py-3.5">
                            <span class="drag-handle mt-0.5 text-muted">@include('studio.partials.grip')</span>
                            <span class="w-6 shrink-0 text-sm font-bold text-muted">{{ $i + 1 }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="line-clamp-2 font-semibold">{{ $question->prompt }}</span>
                                <span class="mt-0.5 block text-xs text-muted">{{ \App\Models\Question::TYPES[$question->type] }} · {{ $question->points }} pt · @if ($question->type === 'short'){{ count($question->acceptedAnswers()) }} accepted answers @else correct: {{ $question->options->where('is_correct', true)->pluck('text')->implode(', ') }} @endif</span>
                            </span>
                            <span class="text-xs font-semibold text-pine">Edit</span>
                        </summary>
                        <div class="border-t border-line p-5">
                            @include('studio.partials.question-form', ['question' => $question, 'action' => route('studio.questions.update', $question), 'method' => 'PUT'])
                            <form method="POST" action="{{ route('studio.questions.destroy', $question) }}" class="mt-3" data-confirm="Delete this question?">@csrf @method('DELETE')<button class="btn-danger btn-sm">Delete question</button></form>
                        </div>
                    </details>
                </li>
            @endforeach
        </ol>

        <div class="panel mt-5 border-dashed p-5">
            <h3 class="font-bold">Add a question</h3>
            <div class="mt-4">@include('studio.partials.question-form', ['question' => null, 'action' => route('studio.questions.store', $lesson), 'method' => 'POST'])</div>
        </div>
    </section>
@endif
@endsection
