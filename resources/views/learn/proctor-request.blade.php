@extends('layouts.app', ['title' => 'Proctor request – '.$lesson->title])
@section('content')
<div class="mx-auto max-w-2xl">
    <a href="{{ route('learn.lesson', [$course, $lesson]) }}" class="-my-2 inline-block py-2 text-sm font-semibold text-muted hover:text-ink">← Back to {{ $lesson->title }}</a>
    <h1 class="mt-3 text-3xl font-extrabold">Take the exam with a proctor</h1>
    <p class="mt-2 text-muted">The {{ $lesson->title }} is supervised. Choose someone to proctor it. We review the request, then send your proctor a one-time access code and instructions. On the day, your proctor types the code on your screen to start the exam.</p>

    @if ($latest)
        @php
            $tone = ['pending' => 'border-saffron/50 bg-saffron-soft', 'approved' => 'border-pine/40 bg-pine-soft', 'declined' => 'border-brick/30 bg-brick-soft', 'cancelled' => 'border-line bg-white'][$latest->status];
            $code = $latest->code;
        @endphp
        <section class="mt-6 rounded-xl border p-5 {{ $tone }}" aria-label="Your latest request">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-bold">
                    @switch($latest->status)
                        @case('pending') Your request is waiting for review @break
                        @case('approved') {{ $code?->isActive() ? 'Approved: your proctor has the code' : 'Your last proctored session' }} @break
                        @case('declined') Your last request was declined @break
                        @default You cancelled your last request
                    @endswitch
                </h2>
                <span class="text-xs text-muted">Sent {{ $latest->created_at->format('M j, Y') }}</span>
            </div>
            <dl class="mt-3 grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-1 text-sm">
                <dt class="text-muted">Proctor</dt><dd>{{ $latest->proctor_name }} ({{ $latest->proctor_email }})</dd>
                @if ($latest->preferredLabel())<dt class="text-muted">Time</dt><dd>{{ $latest->preferredLabel() }}</dd>@endif
                @if ($latest->status === 'approved' && $code?->isActive())<dt class="text-muted">Code valid until</dt><dd>{{ $latest->localTime($code->expires_at) }}</dd>@endif
            </dl>
            @if ($latest->decision_note)<p class="mt-3 rounded-lg bg-white/70 px-3 py-2 text-sm">{{ $latest->decision_note }}</p>@endif
            @if ($latest->isPending())
                <form method="POST" action="{{ route('proctor-requests.cancel', [$course, $lesson, $latest]) }}" class="mt-3" data-confirm="Cancel this request?">@csrf
                    <button class="btn-ghost btn-sm">Cancel request</button>
                </form>
            @elseif ($latest->status === 'approved' && $code?->isActive())
                <a href="{{ route('learn.lesson', [$course, $lesson]) }}" class="btn-primary btn-sm mt-3">Go to the exam</a>
            @endif
        </section>
    @endif

    @if ($canRequest)
        <form method="POST" action="{{ route('proctor-requests.store', [$course, $lesson]) }}" class="panel mt-6 space-y-5 p-6">@csrf
            <h2 class="text-lg font-bold">{{ $latest ? 'Send a new request' : 'Your proctor' }}</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div><label class="label" for="proctor_name">Proctor's full name</label>
                    <input id="proctor_name" name="proctor_name" class="input" maxlength="120" required value="{{ old('proctor_name') }}" autocomplete="off">
                    @error('proctor_name')<p class="mt-1 text-sm text-brick">{{ $message }}</p>@enderror</div>
                <div><label class="label" for="proctor_email">Proctor's email</label>
                    <input id="proctor_email" name="proctor_email" type="email" class="input" maxlength="190" required value="{{ old('proctor_email') }}" autocomplete="off">
                    @error('proctor_email')<p class="mt-1 text-sm text-brick">{{ $message }}</p>@enderror</div>
                <div><label class="label" for="proctor_phone">Proctor's phone <span class="font-normal text-muted">(optional)</span></label>
                    <input id="proctor_phone" name="proctor_phone" type="tel" class="input" maxlength="40" value="{{ old('proctor_phone') }}"></div>
                <div><label class="label" for="proctor_relationship">How do you know them? <span class="font-normal text-muted">(optional)</span></label>
                    <input id="proctor_relationship" name="proctor_relationship" class="input" maxlength="120" value="{{ old('proctor_relationship') }}" placeholder="e.g. Manager at my host agency"></div>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div><label class="label" for="preferred_date">Preferred date and time <span class="font-normal text-muted">(optional)</span></label>
                    <input id="preferred_date" name="preferred_date" type="datetime-local" class="input" value="{{ old('preferred_date') }}">
                    @error('preferred_date')<p class="mt-1 text-sm text-brick">{{ $message }}</p>@enderror</div>
                <div><label class="label" for="timezone">Your time zone</label>
                    <select id="timezone" name="timezone" class="input" data-guess-timezone>
                        @foreach ($timezones as $tz => $label)<option value="{{ $tz }}" @selected(old('timezone', 'America/New_York') === $tz)>{{ $label }}</option>@endforeach
                    </select>
                    @error('timezone')<p class="mt-1 text-sm text-brick">{{ $message }}</p>@enderror</div>
            </div>
            <div><label class="label" for="student_notes">Anything we should know? <span class="font-normal text-muted">(optional)</span></label>
                <textarea id="student_notes" name="student_notes" rows="3" maxlength="2000" class="input" placeholder="e.g. We'll meet on Zoom">{{ old('student_notes') }}</textarea></div>
            <label class="flex gap-3 text-sm">
                <input type="checkbox" name="agree" value="1" class="check mt-0.5" @checked(old('agree'))>
                <span>My proctor is not a relative, will check my photo ID, and will supervise me for the whole exam, in person or on video.</span>
            </label>
            @error('agree')<p class="-mt-3 text-sm text-brick">{{ $message }}</p>@enderror
            <button class="btn-primary">Send request</button>
        </form>
    @endif
</div>
@endsection
