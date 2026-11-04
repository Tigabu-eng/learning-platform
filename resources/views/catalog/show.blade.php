@extends('layouts.app', ['title' => $course->title])
@section('content')
@php
    $lessonCount = $course->chapters->sum(fn ($c) => $c->lessons->count());
    $minutes = $course->chapters->flatMap->lessons->sum('duration_minutes');
    $final = $course->chapters->flatMap->lessons->firstWhere('type', 'exam');
@endphp
<div class="grid gap-10 lg:grid-cols-[1fr_22rem]">
    <div class="min-w-0">
        @unless ($course->isPublished())<div class="mb-4"><span class="badge-grey">Draft — only you can see this page</span></div>@endunless
        <h1 class="text-4xl font-extrabold leading-tight">{{ $course->title }}</h1>
        @if ($course->subtitle)<p class="mt-3 text-xl text-muted">{{ $course->subtitle }}</p>@endif
        <p class="mt-5 text-sm text-muted">Taught by <span class="font-semibold text-ink">{{ $course->instructor->name }}</span> · {{ $course->level }} · {{ $lessonCount }} lessons{{ $minutes ? ' · about '.($minutes >= 60 ? round($minutes / 60, 1).' hours' : $minutes.' minutes') : '' }}</p>

        @if ($course->description)
            <div class="prose-lesson mt-8">{!! \Illuminate\Support\Str::markdown($course->description, ['html_input' => 'escape', 'allow_unsafe_links' => false]) !!}</div>
        @endif

        <h2 class="mt-12 text-2xl font-bold">What you’ll go through</h2>
        <div class="mt-5 space-y-4">
            @foreach ($course->chapters as $chapter)
                <div class="panel">
                    <div class="flex items-center justify-between border-b border-line px-5 py-3">
                        <h3 class="font-bold">{{ $chapter->title }}</h3>
                        <span class="text-xs text-muted">{{ $chapter->lessons->count() }} {{ \Illuminate\Support\Str::plural('lesson', $chapter->lessons->count()) }}</span>
                    </div>
                    <ul class="divide-y divide-line/70">
                        @foreach ($chapter->lessons as $lesson)
                            <li class="flex items-center justify-between gap-3 px-5 py-3 text-sm">
                                <span class="flex min-w-0 items-center gap-3">
                                    @include('partials.lesson-icon', ['type' => $lesson->type])
                                    <span class="truncate">{{ $lesson->title }}</span>
                                </span>
                                <span class="flex shrink-0 items-center gap-3 text-xs text-muted">
                                    @if ($lesson->is_preview && ! $enrollment)
                                        <a href="{{ auth()->check() ? route('learn.lesson', [$course, $lesson]) : route('login') }}" class="font-semibold text-pine">Preview</a>
                                    @endif
                                    @if ($lesson->duration_minutes){{ $lesson->duration_minutes }} min @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </div>

    <aside class="lg:sticky lg:top-6 lg:self-start">
        <div class="panel overflow-hidden">
            <div class="aspect-[16/9] bg-pine">
                @if ($course->thumbnailUrl())<img src="{{ $course->thumbnailUrl() }}" alt="" class="size-full object-cover">@else @include('catalog.cover') @endif
            </div>
            <div class="p-6">
                @if ($enrollment)
                    <p class="text-sm text-muted">You’re enrolled · {{ $enrollment->progress_percent }}% complete</p>
                    <div class="mt-2 h-2 rounded-full bg-mist"><div class="h-2 rounded-full bg-saffron" style="width: {{ $enrollment->progress_percent }}%"></div></div>
                    <a href="{{ route('learn.resume', $course) }}" class="btn-primary mt-5 w-full">{{ $enrollment->progress_percent ? 'Continue learning' : 'Start learning' }}</a>
                @else
                    <div class="text-3xl font-extrabold">{{ $course->priceLabel() }}</div>
                    @auth
                        <a href="{{ route('checkout.show', $course) }}" class="btn-primary mt-5 w-full">{{ $course->isFree() ? 'Enroll for free' : 'Buy this course' }}</a>
                    @else
                        <a href="{{ route('register') }}" class="btn-primary mt-5 w-full">Sign up to enroll</a>
                    @endauth
                @endif
                <ul class="mt-6 space-y-2.5 text-sm">
                    <li>{{ $lessonCount }} lessons in {{ $course->chapters->count() }} chapters</li>
                    @if ($final)<li>Final exam: pass with {{ $final->quiz?->passing_score ?? 70 }}% or higher</li>@endif
                    @if ($course->certificate_enabled)<li>Certificate with a public verification code</li>@endif
                    <li>{{ $course->access_days ? $course->access_days.' days of access' : 'Lifetime access' }}</li>
                </ul>
            </div>
        </div>
    </aside>
</div>
@endsection
