@extends('layouts.studio', ['title' => $enrollment->user->name])
@section('breadcrumb')<a href="{{ route('studio.students', $course) }}" class="hover:text-ink">Students</a> / {{ $enrollment->user->name }} @endsection
@section('content')
<div class="flex flex-wrap items-center justify-between gap-4">
    <div><h2 class="text-xl font-bold">{{ $enrollment->user->name }}</h2><p class="text-sm text-muted">{{ $enrollment->user->email }} · enrolled {{ $enrollment->enrolled_at->format('M j, Y') }}{{ $enrollment->last_activity_at ? ' · last active '.$enrollment->last_activity_at->diffForHumans() : '' }}</p></div>
    <div class="text-right"><div class="text-3xl font-extrabold">{{ $enrollment->progress_percent }}%</div><div class="text-xs text-muted">complete</div></div>
</div>

@if ($codeQuizzes->isNotEmpty() || $codes->isNotEmpty())
<section id="exam-codes" class="panel mt-6 scroll-mt-20 p-5">
    <h3 class="font-bold">Exam access codes</h3>
    <p class="mt-1 max-w-3xl text-sm text-muted">Send the code to the student’s proctor only. The proctor enters it on the student’s screen to start the exam. Each code works once, for {{ $enrollment->user->name }} only, until it expires.</p>

    @php $new = $codes->firstWhere('id', session('new_code')); @endphp
    @if ($new)
        <div class="mt-4 rounded-lg border border-pine/40 bg-pine-soft p-4">
            <div class="text-xs font-semibold text-pine-dark">New code for {{ $new->quiz->lesson->title }}</div>
            <div class="mt-1 font-mono text-3xl font-extrabold tracking-widest">{{ $new->code }}</div>
            <label class="label mt-3" for="email-text">Text for the proctor email</label>
            <textarea id="email-text" rows="5" readonly class="input font-sans text-sm" onclick="this.select()">Exam: {{ $course->title }} – {{ $new->quiz->lesson->title }}
Student: {{ $enrollment->user->name }} ({{ $enrollment->user->email }})
Access code: {{ $new->code }}
Valid until: {{ $new->expires_at->format('F j, Y g:i a T') }}
The code works once. Please enter it on the student's screen at the start of the exam. Don't share it with the student beforehand.</textarea>
        </div>
    @endif

    @if ($codeQuizzes->isNotEmpty())
        <form method="POST" action="{{ route('studio.exam-codes.store', [$course, $enrollment]) }}" class="mt-4 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            @csrf
            <div><label class="label" for="quiz_id">Exam</label>
                <select id="quiz_id" name="quiz_id" class="input">@foreach ($codeQuizzes as $cq)<option value="{{ $cq->id }}">{{ $cq->lesson->title }}</option>@endforeach</select></div>
            <div><label class="label" for="valid_hours">Valid for</label>
                <select id="valid_hours" name="valid_hours" class="input">@foreach (\App\Http\Controllers\Studio\ExamAccessController::VALID_FOR_HOURS as $h => $label)<option value="{{ $h }}" @selected($h === 72)>{{ $label }}</option>@endforeach</select></div>
            <div><label class="label" for="proctor_name">Proctor name <span class="font-normal text-muted">(optional)</span></label>
                <input id="proctor_name" name="proctor_name" maxlength="120" class="input" value="{{ old('proctor_name') }}"></div>
            <div><label class="label" for="proctor_email">Proctor email <span class="font-normal text-muted">(optional)</span></label>
                <input id="proctor_email" name="proctor_email" type="email" maxlength="190" class="input" value="{{ old('proctor_email') }}">
                @error('proctor_email')<p class="mt-1 text-sm text-brick">{{ $message }}</p>@enderror</div>
            <div class="md:col-span-2 lg:col-span-3"><label class="label" for="notes">Notes <span class="font-normal text-muted">(optional, staff only)</span></label>
                <input id="notes" name="notes" maxlength="2000" class="input" value="{{ old('notes') }}" placeholder="e.g. Virtual proctor, Zoom session Tuesday 10am ET"></div>
            <div class="flex items-end"><button class="btn-primary w-full">Generate code</button></div>
            @error('quiz_id')<p class="text-sm text-brick md:col-span-2">{{ $message }}</p>@enderror
        </form>
    @else
        <p class="mt-3 text-sm text-muted">No exam in this course uses one-time codes now. Earlier codes are listed below.</p>
    @endif

    @if ($codes->isNotEmpty())
        <div class="mt-5 overflow-x-auto">
            <table class="table">
                <thead><tr><th>Code</th><th>Exam</th><th>Proctor</th><th>Status</th><th>Created</th><th></th></tr></thead>
                <tbody>@foreach ($codes as $c)
                    @php $status = $c->status(); @endphp
                    <tr>
                        <td class="whitespace-nowrap font-mono font-semibold">{{ $c->code }}</td>
                        <td>{{ $c->quiz->lesson->title }}</td>
                        <td class="text-xs">{{ $c->proctor_name ?: '—' }}@if ($c->proctor_email)<br><span class="text-muted">{{ $c->proctor_email }}</span>@endif @if ($c->notes)<br><span class="text-muted">{{ $c->notes }}</span>@endif</td>
                        <td class="whitespace-nowrap text-xs">
                            @switch($status)
                                @case('active')<span class="badge-green">Active</span><br><span class="text-muted">until {{ $c->expires_at->format('M j, g:i a') }}</span>@break
                                @case('used')<span class="badge bg-mist text-ink">Used</span><br><span class="text-muted">{{ $c->used_at->format('M j, g:i a') }}</span>@if ($c->attempt) · <a href="{{ route('attempts.show', $c->attempt) }}" class="font-semibold text-pine">attempt</a>@endif @break
                                @case('expired')<span class="badge bg-mist text-muted">Expired</span>@break
                                @default<span class="badge-red">Cancelled</span>
                            @endswitch
                        </td>
                        <td class="whitespace-nowrap text-xs">{{ $c->created_at->format('M j, Y') }}@if ($c->creator)<br><span class="text-muted">by {{ $c->creator->name }}</span>@endif</td>
                        <td>@if ($status === 'active')<form method="POST" action="{{ route('studio.exam-codes.revoke', $c) }}" data-confirm="Cancel this code? The proctor won't be able to use it.">@csrf<button class="btn-danger btn-sm">Cancel</button></form>@endif</td>
                    </tr>
                @endforeach</tbody>
            </table>
        </div>
    @endif
</section>
@endif
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
                    <td><a href="{{ route('attempts.show', $a) }}" class="-my-1.5 inline-block py-1.5 text-xs font-semibold text-pine">View</a></td></tr>
            @endforeach</tbody>
        </table>@endif
    </div>
</div>
@endsection
