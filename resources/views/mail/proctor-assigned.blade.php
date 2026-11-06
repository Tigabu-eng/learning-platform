<x-mail::message>
# Thank you for proctoring

**{{ $student->name }}** has named you as the proctor for their **{{ $exam->title }}** in the {{ $course->title }} at {{ config('app.name') }}.

<x-mail::panel>
**Student:** {{ $student->name }} ({{ $student->email }})<br>
**Exam:** {{ $exam->title }}@if ($exam->quiz?->time_limit_minutes) ({{ $exam->quiz->time_limit_minutes }} minutes)@endif<br>
@if ($r->preferredLabel())**Requested time:** {{ $r->preferredLabel() }}<br>@endif
**Access code:** <span style="font-family: monospace; font-size: 18px; letter-spacing: 2px;"><strong>{{ $code?->code }}</strong></span><br>
**Code valid until:** {{ $r->localTime($code?->expires_at) }}
</x-mail::panel>

## On the day
1. Confirm the student's identity with a photo ID.
2. When they're ready, they open the exam and you type the access code on their screen. **Please don't share the code with the student beforehand.** It works once, for this student only.
3. Stay with the student, in person or on video, until they submit the exam.
4. Afterwards, confirm the session on your proctor page and let us know if anything unusual happened.

@if ($r->decision_note)
**Note from {{ config('school.brand.short_name') }}:** {{ $r->decision_note }}
@endif

<x-mail::button :url="$link">Open your proctor page</x-mail::button>

Your proctor page always shows the current code and lets you confirm the session. Keep this email private.

Questions? Reply to this email or write to {{ config('school.brand.support_email') }}.

{{ config('app.name') }}
</x-mail::message>
