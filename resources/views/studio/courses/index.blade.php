@extends('layouts.studio', ['title' => 'Courses'])
@section('breadcrumb', 'Courses')
@section('content')
<div class="flex items-center justify-between">
    <h1 class="text-2xl font-bold">Courses</h1>
    <a href="{{ route('studio.courses.create') }}" class="btn-primary">New course</a>
</div>
@if ($courses->isEmpty())
    <div class="panel mt-6 p-10 text-center"><p class="text-muted">No courses yet.</p><a href="{{ route('studio.courses.create') }}" class="btn-primary mt-4">Create a course</a></div>
@else
<div class="mt-6 grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
    @foreach ($courses as $course)
        <div class="panel flex flex-col overflow-hidden">
            <a href="{{ route('studio.curriculum', $course) }}" class="aspect-[16/9] bg-pine">@if ($course->thumbnailUrl())<img src="{{ $course->thumbnailUrl() }}" alt="" class="size-full object-cover">@else @include('catalog.cover') @endif</a>
            <div class="flex flex-1 flex-col p-5">
                <div class="flex items-center gap-2">@if ($course->isPublished())<span class="badge-green">Published</span>@else<span class="badge-grey">Draft</span>@endif <span class="text-sm text-muted">{{ $course->priceLabel() }}</span></div>
                <a href="{{ route('studio.curriculum', $course) }}" class="mt-2 text-lg font-bold leading-snug hover:text-pine">{{ $course->title }}</a>
                <div class="mt-auto pt-4 text-sm text-muted">{{ $course->lessons_count }} lessons · {{ $course->enrollments_count }} students @if (auth()->user()->isAdmin() && $course->instructor_id !== auth()->id()) · {{ $course->instructor->name }}@endif</div>
            </div>
        </div>
    @endforeach
</div>
@endif
@endsection
