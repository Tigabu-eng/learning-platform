<x-mail::message>
# Welcome, {{ $user->name }}

Your {{ config('app.name') }} account is ready. You'll use it to take courses, track your progress and keep your certificates.

Make sure your name is spelled the way you want it on your certificates. It's printed exactly as you entered it: **{{ $user->name }}**. If it needs changing, reply to this email.

<x-mail::button :url="route('home').'#courses'">Browse courses</x-mail::button>

You can sign in any time at {{ route('login') }} with **{{ $user->email }}**.

Questions? Reply to this email or write to {{ config('school.brand.support_email') }}.

{{ config('app.name') }}
</x-mail::message>
