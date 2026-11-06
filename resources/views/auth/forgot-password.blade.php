@extends('layouts.app', ['title' => 'Forgot password'])
@section('content')
<div class="mx-auto max-w-sm">
    <h1 class="text-3xl font-extrabold">Forgot your password?</h1>
    <p class="mt-2 text-muted">Enter the email you signed up with. We’ll send you a link to choose a new password.</p>
    <form method="POST" action="{{ route('password.email') }}" class="panel mt-8 space-y-5 p-6">@csrf
        <div><label class="label" for="email">Email</label><input class="input" id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email"></div>
        <button class="btn-primary w-full">Send reset link</button>
    </form>
    <p class="mt-6 text-center text-sm text-muted">Remembered it? <a href="{{ route('login') }}" class="font-semibold text-pine">Log in</a></p>
</div>
@endsection
