@extends('layouts.app', ['title' => 'Log in'])
@section('content')
<div class="mx-auto max-w-sm">
    <h1 class="text-3xl font-extrabold">Welcome back</h1>
    <p class="mt-2 text-muted">Log in to continue learning.</p>
    <form method="POST" action="{{ route('login') }}" class="panel mt-8 space-y-5 p-6">@csrf
        <div><label class="label" for="email">Email</label><input class="input" id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email"></div>
        <div><label class="label" for="password">Password</label><input class="input" id="password" name="password" type="password" required autocomplete="current-password"></div>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="remember" class="check"> Keep me logged in</label>
        <button class="btn-primary w-full">Log in</button>
    </form>
    <p class="mt-6 text-center text-sm text-muted">New here? <a href="{{ route('register') }}" class="font-semibold text-pine">Create an account</a></p>
</div>
@endsection
