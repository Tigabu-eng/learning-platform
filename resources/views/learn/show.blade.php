<!DOCTYPE html>
<html lang="en">
<head>@include('partials.head', ['title' => $lesson->title])</head>
<body class="min-h-screen bg-white">
@php
    $total = $course->chapters->sum(fn ($c) => $c->lessons->count());
    $percent = $enrollment?->progress_percent ?? 0;
    $isDone = in_array($lesson->id, $done, true);
    $lock = $locks[$lesson->id];
@endphp
<header class="sticky top-0 z-20 flex h-14 items-center gap-4 border-b border-line bg-white px-4">
    <a href="{{ $manager ? route('studio.curriculum', $course) : route('dashboard') }}" class="text-sm font-semibold text-muted hover:text-ink">← {{ $manager ? 'Back to studio' : 'My learning' }}</a>
    <div class="min-w-0 flex-1 truncate text-center text-sm font-bold">{{ $course->title }}</div>
    @if ($enrollment)
        <div class="flex items-center gap-2 text-sm">
            <div class="hidden h-1.5 w-28 rounded-full bg-mist sm:block"><div class="h-1.5 rounded-full bg-saffron" style="width: {{ $percent }}%"></div></div>
            <span class="font-semibold">{{ $percent }}%</span>
        </div>
    @elseif ($manager)
        <span class="badge-grey">Instructor preview</span>
    @endif
</header>

<div class="lg:grid lg:grid-cols-[20rem_1fr]">
    <aside class="border-b border-line bg-mist/60 lg:sticky lg:top-14 lg:h-[calc(100vh-3.5rem)] lg:overflow-y-auto lg:border-b-0 lg:border-r">
        <details class="lg:hidden"><summary class="cursor-pointer px-5 py-3 text-sm font-semibold">Course contents</summary><div>@include('learn.path')</div></details>
        <div class="hidden lg:block">@include('learn.path')</div>
    </aside>

    <main class="min-w-0 px-5 py-10 sm:px-10">
        <div class="mx-auto max-w-3xl">
            @include('partials.flash')

            @if (session('celebrate') && $certificate)
                <div class="celebrate mb-8 rounded-xl border border-saffron/60 bg-saffron-soft p-6">
                    <div class="text-xl font-extrabold">You finished the course.</div>
                    <p class="mt-1 text-sm">Your certificate is ready{{ $certificate->final_score !== null ? ' — final exam score '.$certificate->final_score.'%' : '' }}.</p>
                    <a href="{{ route('certificates.show', $certificate) }}" class="btn-primary mt-4">View certificate</a>
                </div>
            @endif

            <div class="flex items-center gap-2 text-sm text-muted">
                @include('partials.lesson-icon', ['type' => $lesson->type])
                <span>{{ $lesson->typeLabel() }}</span>
                @if ($lesson->duration_minutes)<span>· {{ $lesson->duration_minutes }} min</span>@endif
                @if ($isDone)<span class="badge-gold ml-1">Completed</span>@endif
            </div>
            <h1 class="mt-2 text-3xl font-extrabold leading-tight">{{ $lesson->title }}</h1>

            @if ($lock)
                <div class="mt-8 rounded-xl border border-dashed border-line bg-mist p-8 text-center">
                    <svg viewBox="0 0 24 24" class="mx-auto size-8 text-muted" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 1 1 8 0v3"/></svg>
                    <p class="mt-3 font-semibold">This lesson is locked</p>
                    <p class="mt-1 text-sm text-muted">{{ $lock }}</p>
                    @unless ($enrollment)<a href="{{ route('courses.show', $course) }}" class="btn-primary mt-4">See enrollment options</a>@endunless
                </div>
            @else
                @if ($lesson->type === 'video')
                    <div class="mt-8 aspect-video overflow-hidden rounded-xl bg-ink">
                        @if ($lesson->embedUrl())
                            <iframe src="{{ $lesson->embedUrl() }}" class="size-full" allow="accelerometer; autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen title="{{ $lesson->title }}"></iframe>
                        @elseif ($lesson->videoFileUrl())
                            <video src="{{ $lesson->videoFileUrl() }}" controls class="size-full" preload="metadata"></video>
                        @else
                            <div class="grid size-full place-items-center text-sm text-white/70">No video has been added yet.</div>
                        @endif
                    </div>
                @endif

                @if ($lesson->renderedContent())
                    <div class="prose-lesson mt-8">{!! $lesson->renderedContent() !!}</div>
                @endif

                @if ($lesson->file_path)
                    <a href="{{ route('learn.download', [$course, $lesson]) }}" class="panel mt-8 flex items-center gap-4 p-4 hover:border-pine/50">
                        <span class="grid size-10 place-items-center rounded-lg bg-pine-soft text-pine">@include('partials.lesson-icon', ['type' => 'download'])</span>
                        <span class="min-w-0"><span class="block truncate font-semibold">{{ $lesson->file_name }}</span><span class="text-sm text-muted">Download file</span></span>
                    </a>
                @endif

                @if ($quizInfo)
                    @include('learn.quiz-intro', $quizInfo)
                @endif
            @endif

            <div class="mt-12 flex flex-wrap items-center justify-between gap-3 border-t border-line pt-6">
                <div>@if ($prev)<a href="{{ route('learn.lesson', [$course, $prev]) }}" class="btn-ghost">← Previous</a>@endif</div>
                <div class="flex gap-2">
                    @if ($enrollment && ! $lock && ! $lesson->isAssessment() && ! $isDone)
                        <form method="POST" action="{{ route('learn.complete', [$course, $lesson]) }}">@csrf
                            <button class="btn-primary">{{ $next ? 'Complete and continue' : 'Complete lesson' }}</button>
                        </form>
                    @elseif ($next)
                        <a href="{{ route('learn.lesson', [$course, $next]) }}" class="btn-primary">Next lesson →</a>
                    @endif
                </div>
            </div>
        </div>
    </main>
</div>
@include('partials.toast')
</body>
</html>
