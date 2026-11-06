@extends('layouts.studio', ['title' => 'Proctoring'])
@section('breadcrumb', 'Proctoring')
@section('content')
<div class="flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-2xl font-bold">Proctoring</h1>
        <p class="mt-1 max-w-2xl text-sm text-muted">Students choose a proctor for exams that use one-time codes. Approving creates the code and emails it to the proctor with instructions. The student is told, but never sees the code.</p>
    </div>
</div>

@error('mail')<p class="mt-4 rounded-lg bg-brick-soft px-3 py-2 text-sm font-semibold text-brick">{{ $message }}</p>@enderror

<nav class="mt-6 flex gap-1 overflow-x-auto border-b border-line text-sm font-semibold" aria-label="Request status">
    @foreach (\App\Models\ProctorRequest::STATUSES as $key => $label)
        <a href="{{ route('studio.proctoring', ['status' => $key]) }}" @class([
            '-mb-px whitespace-nowrap border-b-2 px-3 py-2.5',
            'border-pine text-pine-dark' => $status === $key,
            'border-transparent text-muted hover:text-ink' => $status !== $key,
        ]) @if ($status === $key) aria-current="page" @endif>{{ $key === 'pending' ? 'Waiting' : $label }} <span class="ml-0.5 text-xs font-normal">{{ $counts[$key] ?? 0 }}</span></a>
    @endforeach
</nav>

@if ($requests->isEmpty())
    <div class="panel mt-6 p-10 text-center text-sm text-muted">{{ $status === 'pending' ? 'No requests waiting. New ones also arrive by email.' : 'Nothing here yet.' }}</div>
@else
    <ul class="mt-6 space-y-4">
        @foreach ($requests as $r)
            @php $student = $r->enrollment->user; $rc = $r->quiz->lesson->course; $code = $r->code; @endphp
            <li class="panel p-5" id="request-{{ $r->id }}">
                <div class="grid grid-cols-1 gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_20rem]">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-muted">Student</div>
                        <a href="{{ route('studio.students.show', [$rc, $r->enrollment]) }}" class="mt-1 block font-bold hover:text-pine">{{ $student->name }}</a>
                        <div class="text-sm text-muted">{{ $student->email }}</div>
                        <div class="mt-2 text-sm">{{ $r->quiz->lesson->title }} <span class="text-muted">· {{ $rc->title }}</span></div>
                        <div class="mt-1 text-xs text-muted">Requested {{ $r->created_at->diffForHumans() }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-muted">Proctor</div>
                        <div class="mt-1 font-bold">{{ $r->proctor_name }}</div>
                        <div class="text-sm"><a href="mailto:{{ $r->proctor_email }}" class="text-pine hover:underline">{{ $r->proctor_email }}</a>@if ($r->proctor_phone) · {{ $r->proctor_phone }}@endif</div>
                        @if ($r->proctor_relationship)<div class="mt-1 text-sm"><span class="text-muted">Relationship:</span> {{ $r->proctor_relationship }}</div>@endif
                        @if ($r->preferredLabel())<div class="mt-1 text-sm"><span class="text-muted">Preferred time:</span> {{ $r->preferredLabel() }}</div>@endif
                        @if ($r->student_notes)<div class="mt-2 rounded-lg bg-mist px-3 py-2 text-sm">{{ $r->student_notes }}</div>@endif
                    </div>
                    <div>
                        @if ($r->isPending())
                            <form method="POST" action="{{ route('studio.proctoring.approve', $r) }}" class="space-y-2">@csrf
                                <label class="label" for="vh{{ $r->id }}">Code valid for</label>
                                <select id="vh{{ $r->id }}" name="valid_hours" class="input">@foreach (\App\Services\Proctoring::VALID_FOR_HOURS as $h => $label)<option value="{{ $h }}" @selected($h === 72)>{{ $label }}</option>@endforeach</select>
                                <label class="sr-only" for="note{{ $r->id }}">Note for the proctor and student</label>
                                <textarea id="note{{ $r->id }}" name="note" rows="2" maxlength="2000" class="input text-sm" placeholder="Optional note for the proctor and student, e.g. the Zoom link"></textarea>
                                <button class="btn-primary w-full">Approve and email the proctor</button>
                            </form>
                            <details class="mt-3">
                                <summary class="cursor-pointer py-1.5 text-sm font-semibold text-brick">Decline…</summary>
                                <form method="POST" action="{{ route('studio.proctoring.decline', $r) }}" class="mt-2 space-y-2">@csrf
                                    <label class="sr-only" for="reason{{ $r->id }}">Reason</label>
                                    <textarea id="reason{{ $r->id }}" name="reason" rows="3" maxlength="2000" required class="input text-sm" placeholder="Why, so the student can send a new request. e.g. The proctor can't be a relative."></textarea>
                                    <button class="btn-danger w-full border border-brick/30">Decline and tell the student</button>
                                </form>
                            </details>
                        @else
                            <div class="text-xs font-semibold uppercase tracking-wide text-muted">{{ \App\Models\ProctorRequest::STATUSES[$r->status] }}</div>
                            @if ($r->decided_at)<div class="mt-1 text-sm text-muted">{{ $r->decided_at->format('M j, Y g:i a') }}@if ($r->decider) by {{ $r->decider->name }}@endif</div>@endif
                            @if ($r->decision_note)<div class="mt-2 rounded-lg bg-mist px-3 py-2 text-sm">{{ $r->decision_note }}</div>@endif
                            @if ($r->status === 'approved' && $code)
                                <div class="mt-3 text-sm">Code <span class="font-mono font-semibold">{{ $code->code }}</span>
                                    · @switch($code->status())
                                        @case('active') <span class="badge-green">Active</span> until {{ $r->localTime($code->expires_at) }} @break
                                        @case('used') <span class="badge bg-mist text-ink">Used</span> {{ $code->used_at->format('M j, g:i a') }}@if ($code->attempt) · <a href="{{ route('attempts.show', $code->attempt) }}" class="font-semibold text-pine">attempt{{ $code->attempt->submitted_at ? ' ('.$code->attempt->score.'%)' : '' }}</a>@endif @break
                                        @case('expired') <span class="badge bg-mist text-muted">Expired</span> @break
                                        @default <span class="badge-red">Cancelled</span>
                                    @endswitch
                                </div>
                                <div class="mt-2 text-sm">
                                    @if ($r->proctor_confirmed_at)
                                        @if ($r->proctor_flagged)<span class="badge-red">Proctor reported a problem</span>@else<span class="badge-green">Proctor confirmed</span>@endif
                                        <span class="text-muted">{{ $r->proctor_confirmed_at->format('M j, g:i a') }}</span>
                                        @if ($r->proctor_report)<div class="mt-2 rounded-lg border border-brick/30 bg-brick-soft/40 px-3 py-2">{{ $r->proctor_report }}</div>@endif
                                    @else
                                        <span class="text-muted">Proctor hasn't confirmed the session yet.</span>
                                    @endif
                                </div>
                                @if ($code->isActive())
                                    <form method="POST" action="{{ route('studio.proctoring.resend', $r) }}" class="mt-3" data-confirm="Send the proctor a new link? The old link will stop working.">@csrf
                                        <button class="btn-ghost btn-sm">Resend to proctor</button>
                                    </form>
                                @endif
                            @endif
                        @endif
                    </div>
                </div>
            </li>
        @endforeach
    </ul>
    <div class="mt-4">{{ $requests->links() }}</div>
@endif
@endsection
