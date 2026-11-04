@extends('layouts.app', ['title' => 'Sign up'])
@section('content')
<div class="mx-auto max-w-sm">
    <h1 class="text-3xl font-extrabold">Create your account</h1>
    <p class="mt-2 text-muted">You’ll use it to enroll in courses and keep your certificates.</p>
    <form method="POST" action="{{ route('register') }}" class="panel mt-8 space-y-5 p-6">@csrf
        <div><label class="label" for="name">Full name</label><input class="input" id="name" name="name" value="{{ old('name') }}" required autocomplete="name"><p class="hint">This is the name printed on your certificates.</p></div>
        <div><label class="label" for="email">Email</label><input class="input" id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email"></div>
        <div><label class="label" for="password">Password</label><input class="input" id="password" name="password" type="password" required autocomplete="new-password"><p class="hint">At least 8 characters.</p></div>
        <div><label class="label" for="password_confirmation">Confirm password</label><input class="input" id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"></div>
        <button class="btn-primary w-full">Create account</button>
    </form>
    <p class="mt-6 text-center text-sm text-muted">Already have an account? <a href="{{ route('login') }}" class="font-semibold text-pine">Log in</a></p>
</div>
@endsection
