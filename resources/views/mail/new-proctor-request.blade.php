<x-mail::message>
# New proctor request

**{{ $student->name }}** ({{ $student->email }}) asked to take the **{{ $exam->title }}** in {{ $course->title }}.

<x-mail::panel>
**Proctor:** {{ $r->proctor_name }} ({{ $r->proctor_email }}{{ $r->proctor_phone ? ', '.$r->proctor_phone : '' }})<br>
@if ($r->proctor_relationship)**Relationship:** {{ $r->proctor_relationship }}<br>@endif
@if ($r->preferredLabel())**Preferred time:** {{ $r->preferredLabel() }}<br>@endif
@if ($r->student_notes)**Notes:** {{ $r->student_notes }}@endif
</x-mail::panel>

<x-mail::button :url="$link">Review in the Studio</x-mail::button>
</x-mail::message>
