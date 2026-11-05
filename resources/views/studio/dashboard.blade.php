@extends('layouts.studio', ['title' => 'Studio'])
@section('breadcrumb', 'Studio overview')
@section('content')
<div class="flex flex-wrap items-center justify-between gap-4">
    <h1 class="text-2xl font-bold">Good to see you, {{ explode(' ', auth()->user()->name)[0] }}</h1>
    <a href="{{ route('studio.courses.create') }}" class="btn-primary">New course</a>
</div>
<div class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
    @include('partials.stat', ['label' => 'Revenue', 'value' => money($stats['revenue']), 'sub' => money($stats['revenue30']).' in the last 30 days'])
    @include('partials.stat', ['label' => 'Students', 'value' => number_format($stats['students']), 'sub' => $stats['enrollments30'].' new enrollments in 30 days'])
    @include('partials.stat', ['label' => 'Completions', 'value' => number_format($stats['completions'])])
    @include('partials.stat', ['label' => 'Courses', 'value' => $stats['courses']])
</div>

<div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
    <div class="panel p-5">
        <h2 class="font-bold">Enrollments, last 14 days</h2>
        <div class="mt-5">@include('partials.bars', ['data' => $trend, 'aria' => 'Daily enrollments for the last 14 days'])</div>
    </div>
    <div class="panel p-5">
        <h2 class="font-bold">Newest students</h2>
        <ul class="mt-3 divide-y divide-line/70 text-sm">
            @forelse ($recent as $e)
                <li class="py-2.5"><div class="font-semibold">{{ $e->user->name }}</div><div class="truncate text-xs text-muted">{{ $e->course->title }} · {{ $e->enrolled_at->diffForHumans() }}</div></li>
            @empty
                <li class="py-4 text-muted">No students yet. Publish a course and share its link.</li>
            @endforelse
        </ul>
    </div>
</div>

<div class="panel mt-6 overflow-x-auto">
    <div class="px-5 pt-5 font-bold">Your courses</div>
    @if ($courses->isEmpty())
        <div class="p-8 text-center"><p class="text-muted">You haven’t created a course yet.</p><a href="{{ route('studio.courses.create') }}" class="btn-primary mt-4">Create your first course</a></div>
    @else
    <table class="table mt-2">
        <thead><tr><th>Course</th><th>Status</th><th>Lessons</th><th>Students</th><th>Avg. progress</th><th></th></tr></thead>
        <tbody>
        @foreach ($courses as $c)
            <tr>
                <td class="font-semibold">{{ $c->title }}</td>
                <td>@if ($c->isPublished())<span class="badge-green">Published</span>@else<span class="badge-grey">Draft</span>@endif</td>
                <td>{{ $c->lessons_count }}</td>
                <td>{{ $c->enrollments_count }}</td>
                <td>{{ $c->enrollments_count ? round($c->enrollments_avg_progress_percent).'%' : '—' }}</td>
                <td class="text-right"><a href="{{ route('studio.curriculum', $c) }}" class="font-semibold text-pine">Edit</a></td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div>
@endsection
