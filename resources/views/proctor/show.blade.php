<!DOCTYPE html>
<html lang="en">
<head>
@include('partials.head', ['title' => 'Proctor page'])
<meta name="robots" content="noindex, nofollow">
</head>
<body class="flex min-h-screen min-h-svh flex-col">
@php
    $student = $r->enrollment->user; $exam = $r->quiz->lesson; $course = $exam->course; $code = $r->code; $attempt = $code?->attempt;
    $state = $code?->status();
@endphp
<header class="border-b border-line bg-white">
    <div class="mx-auto flex h-16 max-w-3xl items-center justify-between px-4">
        @include('partials.logo')
        <span class="text-sm font-semibold text-muted">Proctor page</span>
    </div>
</header>
<main class="mx-auto w-full max-w-3xl flex-1 px-4 py-8">
    @include('partials.flash')
    <h1 class="text-2xl font-extrabold sm:text-3xl">Proctoring {{ $student->name }}</h1>
    <p class="mt-1 text-muted">{{ $exam->title }} · {{ $course->title }}</p>

    <section class="panel mt-6 overflow-hidden">
        <dl class="grid grid-cols-1 gap-px bg-line sm:grid-cols-2">
            <div class="bg-white p-4"><dt class="text-xs text-muted">Student</dt><dd class="mt-0.5 font-semibold">{{ $student->name }}</dd><dd class="text-sm text-muted">{{ $student->email }}</dd></div>
            <div class="bg-white p-4"><dt class="text-xs text-muted">Requested time</dt><dd class="mt-0.5 font-semibold">{{ $r->preferredLabel() ?? 'Not set: agree a time with the student' }}</dd></div>
            <div class="bg-white p-4"><dt class="text-xs text-muted">Exam</dt><dd class="mt-0.5 font-semibold">{{ $exam->title }}</dd>
                <dd class="text-sm text-muted">@if ($r->quiz->time_limit_minutes){{ $r->quiz->time_limit_minutes }} minutes · @endif pass mark {{ $r->quiz->passing_score }}%</dd></div>
            <div class="bg-white p-4"><dt class="text-xs text-muted">Status</dt><dd class="mt-0.5 font-semibold">
                @if ($attempt?->submitted_at) Exam submitted {{ $r->localTime($attempt->submitted_at) }}
                @elseif ($attempt) Exam in progress (started {{ $r->localTime($attempt->started_at) }})
                @elseif ($state === 'active') Ready: waiting for the student to begin
                @elseif ($state === 'expired') The code expired. Ask TARC for a new one.
                @else The code was cancelled. Ask TARC for a new one.
                @endif
            </dd></div>
        </dl>
    </section>

    @if ($state === 'active')
        <section class="mt-6 rounded-xl border border-pine/40 bg-pine-soft p-5 text-center">
            <div class="text-sm font-semibold text-pine-dark">Access code: type it on the student's screen when they're ready</div>
            <div class="mt-2 font-mono text-4xl font-extrabold tracking-[0.2em] text-ink">{{ $code->code }}</div>
            <div class="mt-2 text-sm text-muted">Works once, for {{ $student->name }} only. Valid until {{ $r->localTime($code->expires_at) }}. Please don't share it with the student.</div>
        </section>
    @endif

    @if ($r->decision_note)
        <p class="mt-4 rounded-lg bg-saffron-soft px-4 py-3 text-sm"><span class="font-semibold">Note from {{ config('school.brand.short_name') }}:</span> {{ $r->decision_note }}</p>
    @endif

    <section class="panel mt-6 p-5">
        <h2 class="font-bold">How to proctor</h2>
        <ol class="mt-3 list-decimal space-y-1.5 pl-5 text-[15px]">
            <li>Check the student's photo ID matches {{ $student->name }}.</li>
            <li>The student signs in and opens the exam. When they're ready, type the access code on their screen and start it.</li>
            <li>Stay with them, in person or on video, until they submit. Notes, other people and other tabs aren't allowed.</li>
            <li>Afterwards, confirm the session below.</li>
        </ol>
    </section>

    <section class="panel mt-6 p-5" id="confirm">
        <h2 class="font-bold">Confirm the session</h2>
        @if ($r->proctor_confirmed_at)
            <p class="mt-2 text-sm">
                @if ($r->proctor_flagged)<span class="badge-red">You reported a problem</span>@else<span class="badge-green">Confirmed</span>@endif
                <span class="text-muted">on {{ $r->localTime($r->proctor_confirmed_at) }}. You can update it below.</span>
            </p>
        @endif
        @if ($code?->used_at)
            <form method="POST" action="{{ route('proctor.confirm', $token) }}" class="mt-4 space-y-4">@csrf
                <label class="flex gap-3 text-[15px]"><input type="checkbox" name="supervised" value="1" class="check mt-1" @checked(old('supervised', $r->proctor_confirmed_at ? 1 : 0))>
                    <span>I checked the student's ID and supervised the whole exam.</span></label>
                @error('supervised')<p class="-mt-2 text-sm text-brick">{{ $message }}</p>@enderror
                <label class="flex gap-3 text-[15px]"><input type="checkbox" name="flagged" value="1" class="check mt-1" @checked(old('flagged', $r->proctor_flagged))>
                    <span>Something unusual happened that TARC should know about.</span></label>
                <div><label class="label" for="report">Details <span class="font-normal text-muted">(required if something happened)</span></label>
                    <textarea id="report" name="report" rows="3" maxlength="5000" class="input">{{ old('report', $r->proctor_report) }}</textarea>
                    @error('report')<p class="mt-1 text-sm text-brick">{{ $message }}</p>@enderror</div>
                <button class="btn-primary">{{ $r->proctor_confirmed_at ? 'Update confirmation' : 'Confirm session' }}</button>
            </form>
        @else
            <p class="mt-2 text-sm text-muted">You can confirm once the student has started the exam with your code.</p>
        @endif
    </section>

    <p class="mt-6 text-sm text-muted">Questions? Email <a href="mailto:{{ config('school.brand.support_email') }}" class="font-semibold text-pine">{{ config('school.brand.support_email') }}</a>. Keep this page's link private: anyone with it can see the code.</p>
</main>
<footer class="border-t border-line bg-white py-5 text-center text-sm text-muted">© {{ date('Y') }} {{ config('app.name') }}</footer>
</body>
</html>
