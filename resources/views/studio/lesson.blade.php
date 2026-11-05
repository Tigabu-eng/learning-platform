@extends('layouts.studio', ['title' => $lesson->title])
@section('breadcrumb')<a href="{{ route('studio.courses.index') }}" class="hover:text-ink">Courses</a> / <a href="{{ route('studio.curriculum', $course) }}" class="hover:text-ink">{{ $course->title }}</a> / {{ $lesson->title }} @endsection
@section('content')
@php
    $quiz = $lesson->quiz; $noun = $lesson->type === 'exam' ? 'exam' : 'quiz';
    $topicCounts = $quiz ? $quiz->topicCounts() : [];
    $bankSize = array_sum($topicCounts);
@endphp
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
                <textarea id="content" name="content" rows="{{ $lesson->type === 'text' ? 18 : 6 }}" class="input leading-relaxed" data-rich>{{ \App\Support\RichText::forEditor(old('content', $lesson->content)) }}</textarea>
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
                <div class="max-w-md">
                    <label class="label" for="questions_per_attempt">Questions per attempt</label>
                    <input id="questions_per_attempt" name="questions_per_attempt" type="number" min="1" max="1000" class="input" value="{{ old('questions_per_attempt', $quiz->questions_per_attempt) }}" placeholder="All {{ $bankSize }} questions">
                    <p class="hint">Leave blank to use every question. Set a number to give each student a different random set from the question bank, balanced across topics. Retakes get questions the student hasn't seen yet first.</p>
                    @if ($quiz->questions_per_attempt && $quiz->questions_per_attempt > $bankSize)
                        <p class="mt-1 text-sm font-semibold text-brick">The bank has only {{ $bankSize }} questions, so attempts use all of them.</p>
                    @endif
                </div>
                <fieldset class="rounded-lg border border-line p-4">
                    <legend class="px-1 text-sm font-semibold">Who can start the {{ $noun }}</legend>
                    @php $mode = old('access_mode', $quiz->access_mode ?? 'open'); @endphp
                    <div class="space-y-2.5">
                        <label class="flex gap-3"><input type="radio" name="access_mode" value="open" class="check mt-0.5" @checked($mode === 'open')>
                            <span><span class="block text-sm font-semibold">Any enrolled student</span><span class="text-xs text-muted">No password needed.</span></span></label>
                        <label class="flex gap-3"><input type="radio" name="access_mode" value="password" class="check mt-0.5" @checked($mode === 'password')>
                            <span><span class="block text-sm font-semibold">One shared password</span><span class="text-xs text-muted">The proctor gives students the password. Anyone who has it can use it, so change it after each exam session.</span></span></label>
                        <label class="flex gap-3"><input type="radio" name="access_mode" value="code" class="check mt-0.5" @checked($mode === 'code')>
                            <span><span class="block text-sm font-semibold">One-time code per student</span><span class="text-xs text-muted">Generate a code for each student on their page under <a href="{{ route('studio.students', $course) }}" class="font-semibold text-pine">Students</a> and send it to their proctor. Each code works once, for that student only, until it expires.</span></span></label>
                    </div>
                    <div class="mt-3 max-w-sm">
                        <label class="label" for="access_password">Shared password</label>
                        <input id="access_password" name="access_password" class="input font-mono" maxlength="100" autocomplete="off" value="{{ old('access_password', $quiz->access_password) }}" placeholder="Only used with a shared password">
                        @error('access_password')<p class="mt-1 text-sm font-semibold text-brick">{{ $message }}</p>@enderror
                        <p class="hint">Case-sensitive. Instructors and admins can start the {{ $noun }} without it, to preview.</p>
                    </div>
                </fieldset>
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
                <h2 class="text-xl font-bold">{{ $quiz->questions_per_attempt ? 'Question bank' : 'Questions' }}</h2>
                <p class="text-sm text-muted">
                    {{ $bankSize }} questions · {{ $quiz->questions->sum('points') }} points.
                    @if ($quiz->questions_per_attempt) Each attempt draws {{ $quiz->attemptSize() }} at random. @endif
                    Drag to reorder.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if (count($topicCounts) > 1)
                    <label class="sr-only" for="topic-filter">Show topic</label>
                    <select id="topic-filter" data-topic-filter class="input w-auto py-1.5 text-sm">
                        <option value="*">All topics</option>
                        @foreach ($topicCounts as $t => $n)<option value="{{ $t }}">{{ $t === '' ? 'No topic' : $t }} ({{ $n }})</option>@endforeach
                    </select>
                @endif
                <a href="#import" class="btn-ghost btn-sm">Import questions</a>
            </div>
        </div>
        @if ($topicCounts && array_keys($topicCounts) !== [''])
            <div class="mt-3 flex flex-wrap gap-1.5 text-xs">
                @foreach ($topicCounts as $t => $n)
                    <span class="rounded-full border border-line bg-white px-2.5 py-1"><span class="font-semibold">{{ $t === '' ? 'No topic' : $t }}</span> · {{ $n }}@if ($quiz->questions_per_attempt && $bankSize) · ~{{ round($quiz->attemptSize() * $n / $bankSize, 1) }} per attempt @endif</span>
                @endforeach
            </div>
        @endif
        <datalist id="question-topics">@foreach (array_filter(array_keys($topicCounts), 'strlen') as $t)<option value="{{ $t }}"></option>@endforeach</datalist>
        <ol data-questions data-reorder-url="{{ route('studio.questions.reorder', $lesson) }}" class="mt-4 space-y-3">
            @foreach ($quiz->questions as $i => $question)
                <li data-question="{{ $question->id }}" data-topic="{{ (string) $question->topic }}" id="q{{ $question->id }}" class="panel scroll-mt-20">
                    <details @if (old('_qid') == $question->id) open @endif>
                        <summary class="flex cursor-pointer list-none items-start gap-3 px-4 py-3.5">
                            <span class="drag-handle mt-0.5 text-muted">@include('studio.partials.grip')</span>
                            <span class="w-6 shrink-0 text-sm font-bold text-muted">{{ $i + 1 }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="line-clamp-2 font-semibold">{{ \App\Support\RichText::plain($question->prompt) }}</span>
                                <span class="mt-0.5 block text-xs text-muted">@if ($question->topic)<span class="font-semibold text-ink">{{ $question->topic }}</span> · @endif{{ \App\Models\Question::TYPES[$question->type] }} · {{ $question->points }} pt · @if ($question->type === 'short'){{ count($question->acceptedAnswers()) }} accepted answers @else correct: {{ $question->options->where('is_correct', true)->pluck('text')->implode(', ') }} @endif</span>
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

        <div id="import" class="panel mt-5 scroll-mt-20 p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="font-bold">Import questions</h3>
                    <p class="mt-1 max-w-2xl text-sm text-muted">Add many questions at once from Excel, a CSV file, a Word document, or text pasted from Google Docs. You'll see a preview before anything is added, and questions already in this {{ $noun }} are skipped.</p>
                </div>
                <a href="{{ route('studio.questions.template', $lesson) }}" class="btn-ghost btn-sm">Download Excel template</a>
            </div>
            @error('file')<p class="mt-3 rounded-lg bg-brick-soft px-3 py-2 text-sm font-semibold text-brick">{{ $message }}</p>@enderror
            @error('text')<p class="mt-3 rounded-lg bg-brick-soft px-3 py-2 text-sm font-semibold text-brick">{{ $message }}</p>@enderror
            <form method="POST" action="{{ route('studio.questions.import.preview', $lesson) }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                @csrf
                <div class="grid gap-4 md:grid-cols-2">
                    <div><label class="label" for="import-file">File</label>
                        <input id="import-file" name="file" type="file" accept=".xlsx,.csv,.docx,.txt" class="text-sm">
                        <p class="hint">.xlsx, .csv, .docx or .txt. Old .xls and .doc files: use Save As in Excel or Word first.</p></div>
                    <div><label class="label" for="default_topic">Topic for questions without one <span class="font-normal text-muted">(optional)</span></label>
                        <input id="default_topic" name="default_topic" list="question-topics" maxlength="120" class="input" placeholder="e.g. Module 4"></div>
                </div>
                <div><label class="label" for="import-text">…or paste questions</label>
                    <textarea id="import-text" name="text" rows="6" class="input font-mono text-sm" placeholder="Topic: Module 1&#10;1. Which body accredits travel agencies to sell airline tickets?&#10;A. IATA&#10;B. CLIA&#10;C. ASTA&#10;Answer: A">{{ old('text') }}</textarea></div>
                <button class="btn-primary btn-sm">Preview import</button>
            </form>
            <details class="mt-4 text-sm">
                <summary class="cursor-pointer font-semibold text-pine">Format guide</summary>
                <div class="mt-3 grid gap-5 md:grid-cols-2">
                    <div>
                        <p class="font-semibold">Excel or CSV: one row per question</p>
                        <p class="mt-1 text-muted">Columns: <strong>Topic</strong>, <strong>Type</strong>, <strong>Question</strong>, <strong>Option A</strong>–<strong>Option H</strong>, <strong>Correct</strong>, <strong>Points</strong>, <strong>Explanation</strong>. Only Question and Correct are required. The template has one example of each type.</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5 text-muted">
                            <li>Correct: the letter (<code>B</code>), several letters (<code>A, C</code>), <code>True</code>/<code>False</code>, or for short answers the accepted answers separated by <code>|</code>.</li>
                            <li>Type can be left blank: it's worked out from the options and the answer. Types: single, multiple, true_false, short.</li>
                        </ul>
                    </div>
                    <div>
                        <p class="font-semibold">Word, Google Docs or pasted text</p>
<pre class="mt-2 overflow-x-auto rounded-lg bg-mist p-3 text-xs leading-relaxed">Topic: Module 4
1. Which belong in a discovery call?
A. Budget
B. Travel dates
C. Your commission rate
Answer: A, B
Explanation: Keep the call about the client.

2. IATA stands for International Air
Transport Association.
Answer: True</pre>
                        <p class="mt-2 text-muted">Word's automatic numbering and lettering work too. A choice ending with <code>*</code> also marks it correct. "Module 4: ..." on its own line sets the topic for the questions below it.</p>
                    </div>
                </div>
            </details>
        </div>
    </section>
@endif
@endsection
