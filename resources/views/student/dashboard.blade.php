@extends('layouts.app', ['title' => 'My learning'])
@section('content')
<h1 class="text-3xl font-extrabold">My learning</h1>

@if ($enrollments->isEmpty())
    <div class="panel mt-8 p-10 text-center">
        <p class="font-semibold">You haven’t enrolled in a course yet.</p>
        <a href="{{ route('home') }}" class="btn-primary mt-4">Browse courses</a>
    </div>
@else
    <div class="mt-8 space-y-4">
        @foreach ($enrollments as $e)
            <div class="panel flex flex-col gap-5 p-5 sm:flex-row sm:items-center">
                <div class="aspect-[16/9] w-full shrink-0 overflow-hidden rounded-lg bg-pine sm:w-44">
                    @if ($e->course->thumbnailUrl())<img src="{{ $e->course->thumbnailUrl() }}" alt="" class="size-full object-cover">@else @include('catalog.cover', ['course' => $e->course]) @endif
                </div>
                <div class="min-w-0 flex-1">
                    <h2 class="text-lg font-bold">{{ $e->course->title }}</h2>
                    <p class="text-sm text-muted">{{ $e->course->instructor->name }}@if ($e->lastLesson) · Last opened: {{ $e->lastLesson->title }}@endif</p>
                    <div class="mt-3 flex items-center gap-3">
                        <div class="h-2 flex-1 rounded-full bg-mist"><div class="h-2 rounded-full bg-saffron" style="width: {{ $e->progress_percent }}%"></div></div>
                        <span class="w-10 text-right text-sm font-semibold">{{ $e->progress_percent }}%</span>
                    </div>
                    @if ($e->isExpired())<p class="mt-2 text-xs text-brick">Access ended {{ $e->expires_at->format('M j, Y') }}</p>@elseif ($e->expires_at)<p class="mt-2 text-xs text-muted">Access until {{ $e->expires_at->format('M j, Y') }}</p>@endif
                </div>
                <div class="flex shrink-0 gap-2">
                    @if ($e->certificate)<a href="{{ route('certificates.show', $e->certificate) }}" class="btn-accent btn-sm">View certificate</a>@endif
                    <a href="{{ route('learn.resume', $e->course) }}" class="btn-primary btn-sm">{{ $e->completed_at ? 'Review' : ($e->progress_percent ? 'Continue' : 'Start') }}</a>
                </div>
            </div>
        @endforeach
    </div>
@endif

@if ($certificates->isNotEmpty())
    <h2 class="mt-14 text-2xl font-bold">Certificates</h2>
    <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($certificates as $c)
            <a href="{{ route('certificates.show', $c) }}" class="panel block border-saffron/40 p-5 hover:border-saffron">
                <div class="text-xs font-semibold text-[#7a5408]">Certificate of completion</div>
                <div class="mt-1 font-bold">{{ $c->course->title }}</div>
                <div class="mt-3 text-xs text-muted">Issued {{ $c->issued_at->format('M j, Y') }}@if ($c->final_score !== null) · Final exam {{ $c->final_score }}%@endif</div>
                <div class="mt-1 text-xs text-muted">Code {{ $c->code }}</div>
            </a>
        @endforeach
    </div>
@endif
@endsection
