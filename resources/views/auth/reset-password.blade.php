@extends('layouts.app', ['title' => $invite ? 'Choose a password' : 'Reset password'])
@section('content')
<div class="mx-auto max-w-sm">
    <h1 class="text-3xl font-extrabold">{{ $invite ? 'Choose your password' : 'Choose a new password' }}</h1>
    <p class="mt-2 text-muted">{{ $invite ? 'Set a password to finish creating your account.' : 'You’ll be logged in once it’s saved.' }}</p>
    <form method="POST" action="{{ route('password.update') }}" class="panel mt-8 space-y-5 p-6">@csrf
        <input type="hidden" name="token" value="{{ $token }}">
        @if ($invite)<input type="hidden" name="invite" value="1">@endif
        <div><label class="label" for="email">Email</label><input class="input" id="email" name="email" type="email" value="{{ old('email', $email) }}" required autocomplete="email"></div>
        <div><label class="label" for="password">{{ $invite ? 'Password' : 'New password' }}</label><input class="input" id="password" name="password" type="password" required autofocus autocomplete="new-password"><p class="hint">At least 8 characters.</p></div>
        <div><label class="label" for="password_confirmation">Confirm password</label><input class="input" id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"></div>
        <button class="btn-primary w-full">Save password</button>
    </form>
    <p class="mt-6 text-center text-sm text-muted">Link expired? <a href="{{ route('password.request') }}" class="font-semibold text-pine">Get a new one</a></p>
</div>
@endsection
