@extends('layouts.studio', ['title' => 'Students – '.$course->title])
@section('breadcrumb')<a href="{{ route('studio.courses.index') }}" class="hover:text-ink">Courses</a> / {{ $course->title }} / Students @endsection
@section('content')
<div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_18rem]">
    <div class="panel overflow-x-auto">
        <div class="flex flex-wrap items-center justify-between gap-3 px-5 pt-5">
            <h2 class="font-bold">{{ $enrollments->count() }} {{ \Illuminate\Support\Str::plural('student', $enrollments->count()) }}</h2>
            <form><label class="sr-only" for="q">Search students</label><input id="q" name="q" value="{{ request('q') }}" class="input w-60" placeholder="Search by name or email"></form>
        </div>
        @if ($enrollments->isEmpty())
            <p class="p-8 text-center text-muted">{{ request('q') ? 'No students match that search.' : 'No students yet.' }}</p>
        @else
        <table class="table mt-3">
            <thead><tr><th>Student</th><th>Enrolled</th><th>Progress</th>@if ($final)<th>Final exam</th>@endif<th>Certificate</th><th></th></tr></thead>
            <tbody>
            @foreach ($enrollments as $e)
                @php $fs = $finalScores[$e->id] ?? null; @endphp
                <tr>
                    <td><div class="font-semibold">{{ $e->user->name }}</div><div class="text-xs text-muted">{{ $e->user->email }}</div></td>
                    <td class="whitespace-nowrap">{{ $e->enrolled_at->format('M j, Y') }}</td>
                    <td><div class="flex items-center gap-2"><div class="h-1.5 w-20 rounded-full bg-mist"><div class="h-1.5 rounded-full bg-saffron" style="width: {{ $e->progress_percent }}%"></div></div><span class="text-xs">{{ $e->progress_percent }}%</span></div></td>
                    @if ($final)<td>@if ($fs) <span class="font-semibold">{{ $fs->best }}%</span> @if ($fs->passed)<span class="badge-green ml-1">Passed</span>@endif @else <span class="text-muted">—</span> @endif</td>@endif
                    <td>@if ($e->certificate)<a href="{{ route('certificates.show', $e->certificate) }}" class="badge-gold" target="_blank" rel="noopener">Issued</a>@else<span class="text-muted">—</span>@endif</td>
                    <td class="text-right"><a href="{{ route('studio.students.show', [$course, $e]) }}" class="font-semibold text-pine">Details</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
        @endif
    </div>
    <form method="POST" action="{{ route('studio.students.enroll', $course) }}" class="panel space-y-4 self-start p-5">@csrf
        <h2 class="font-bold">Enroll a student</h2>
        <p class="text-sm text-muted">Give someone access without payment.</p>
        <div><label class="label" for="email">Email</label><input id="email" name="email" type="email" class="input" value="{{ old('email') }}" required></div>
        <div><label class="label" for="name">Name</label><input id="name" name="name" class="input" value="{{ old('name') }}"><p class="hint">Only needed if they don’t have an account yet.</p></div>
        <label class="flex gap-3 text-sm"><input type="checkbox" name="notify" value="1" class="check mt-0.5" @checked(old('notify', true))>
            <span>Email them that they’re enrolled<span class="block text-xs text-muted">New accounts get a link to choose their password.</span></span></label>
        <button class="btn-primary w-full">Enroll student</button>
    </form>
</div>
@endsection
