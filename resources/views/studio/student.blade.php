@extends('layouts.studio', ['title' => $enrollment->user->name])
@section('breadcrumb')<a href="{{ route('studio.students', $course) }}" class="hover:text-ink">Students</a> / {{ $enrollment->user->name }} @endsection
@section('content')
<div class="flex flex-wrap items-center justify-between gap-4">
    <div><h2 class="text-xl font-bold">{{ $enrollment->user->name }}</h2><p class="text-sm text-muted">{{ $enrollment->user->email }} · enrolled {{ $enrollment->enrolled_at->format('M j, Y') }}{{ $enrollment->last_activity_at ? ' · last active '.$enrollment->last_activity_at->diffForHumans() : '' }}</p></div>
    <div class="text-right"><div class="text-3xl font-extrabold">{{ $enrollment->progress_percent }}%</div><div class="text-xs text-muted">complete</div></div>
</div>
<div class="mt-6 grid gap-6 lg:grid-cols-2">
    <div class="panel p-5">
        <h3 class="font-bold">Lessons</h3>
        @foreach ($course->chapters as $ch)
            <div class="mt-4 text-xs font-semibold text-muted">{{ $ch->title }}</div>
            <ul class="mt-1 divide-y divide-line/70 text-sm">
                @foreach ($ch->lessons as $l)
                    <li class="flex items-center justify-between py-2">
                        <span class="flex items-center gap-2">@include('partials.lesson-icon', ['type' => $l->type]) {{ $l->title }}</span>
                        @if (isset($done[$l->id]))<span class="text-xs text-pine">✓ {{ \Illuminate\Support\Carbon::parse($done[$l->id])->format('M j') }}</span>@else<span class="text-xs text-muted">Not done</span>@endif
                    </li>
                @endforeach
            </ul>
        @endforeach
    </div>
    <div class="panel overflow-x-auto p-5">
        <h3 class="font-bold">Quiz and exam attempts</h3>
        @if ($attempts->isEmpty())<p class="mt-3 text-sm text-muted">No attempts yet.</p>@else
        <table class="table mt-2">
            <thead><tr><th>Assessment</th><th>Date</th><th>Score</th><th></th></tr></thead>
            <tbody>@foreach ($attempts as $a)
                <tr><td>{{ $a->quiz->lesson->title }}</td><td class="whitespace-nowrap text-xs">{{ $a->submitted_at->format('M j, g:i a') }}</td>
                    <td><span class="font-semibold">{{ $a->score }}%</span> @if ($a->passed)<span class="badge-green ml-1">Pass</span>@else<span class="badge-red ml-1">Fail</span>@endif</td>
                    <td><a href="{{ route('attempts.show', $a) }}" class="text-xs font-semibold text-pine">View</a></td></tr>
            @endforeach</tbody>
        </table>@endif
    </div>
</div>
@endsection
