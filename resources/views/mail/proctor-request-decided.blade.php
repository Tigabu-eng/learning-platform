<x-mail::message>
@if ($r->status === 'approved')
# Your proctored exam is approved

Hi {{ $student->name }},

Your request to take the **{{ $exam->title }}** with **{{ $r->proctor_name }}** as your proctor is approved. We've sent your proctor the access code and instructions.

@if ($r->preferredLabel())**Requested time:** {{ $r->preferredLabel() }}@endif

When you're ready, open the exam with your proctor present. Your proctor will type the access code on your screen to start it. The code works once and expires on {{ $r->localTime($r->code?->expires_at) }}.
@else
# About your proctor request

Hi {{ $student->name }},

We couldn't approve your request to take the **{{ $exam->title }}** with {{ $r->proctor_name }} as your proctor.
@endif

@if ($r->decision_note)
<x-mail::panel>
{{ $r->decision_note }}
</x-mail::panel>
@endif

<x-mail::button :url="$link">{{ $r->status === 'approved' ? 'Go to the exam' : 'Send a new request' }}</x-mail::button>

Questions? Reply to this email or write to {{ config('school.brand.support_email') }}.

{{ config('app.name') }}
</x-mail::message>
