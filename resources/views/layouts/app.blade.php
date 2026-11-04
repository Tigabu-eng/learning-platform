<!DOCTYPE html>
<html lang="en">
<head>@include('partials.head')</head>
<body class="min-h-screen">
<header class="border-b border-line bg-white">
    <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6">
        <div class="flex items-center gap-8">
            @include('partials.logo')
            <nav class="hidden items-center gap-6 text-sm font-semibold text-muted md:flex">
                <a href="{{ route('home') }}" @class(['hover:text-ink', 'text-ink' => request()->routeIs('home', 'courses.*')])>Courses</a>
                @auth<a href="{{ route('dashboard') }}" @class(['hover:text-ink', 'text-ink' => request()->routeIs('dashboard')])>My learning</a>@endauth
                <a href="{{ route('certificates.verify') }}" @class(['hover:text-ink', 'text-ink' => request()->routeIs('certificates.verify')])>Verify a certificate</a>
            </nav>
        </div>
        @include('partials.usermenu')
    </div>
</header>
<main class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
    @include('partials.flash')
    @yield('content')
</main>
<footer class="mx-auto max-w-6xl px-4 pb-10 text-xs text-muted sm:px-6">© {{ date('Y') }} {{ config('app.name') }}</footer>
@include('partials.toast')
</body>
</html>
