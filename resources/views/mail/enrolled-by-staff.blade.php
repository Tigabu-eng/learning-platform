<x-mail::message>
# You're enrolled in {{ $course->title }}

Hi {{ $user->name }},

@if ($setPasswordUrl)
{{ config('school.brand.short_name') }} has created a {{ config('app.name') }} account for you and enrolled you in **{{ $course->title }}**. Choose a password to get started.

<x-mail::button :url="$setPasswordUrl">Choose your password</x-mail::button>

This link works for {{ $days }} days. After that, use **Forgot password?** on the log-in page with **{{ $user->email }}**.
@else
{{ config('school.brand.short_name') }} has enrolled you in **{{ $course->title }}**. Log in with **{{ $user->email }}** to start.

<x-mail::button :url="route('learn.resume', $course)">Start learning</x-mail::button>
@endif

Questions? Reply to this email or write to {{ config('school.brand.support_email') }}.

{{ config('app.name') }}
</x-mail::message>
