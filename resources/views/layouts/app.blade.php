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
                <a href="{{ config('school.brand.site_url') }}" class="hover:text-ink">TARC main site</a>
            </nav>
        </div>
        @include('partials.usermenu')
    </div>
</header>
<main class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
    @include('partials.flash')
    @yield('content')
</main>
<footer class="border-t border-line bg-white">
    <div class="mx-auto flex max-w-6xl flex-col gap-4 px-4 py-8 text-sm text-muted sm:px-6 md:flex-row md:items-center md:justify-between">
        <div>
            <p class="font-semibold text-ink">{{ config('app.name') }} ({{ config('school.brand.short_name') }})™</p>
            <p class="mt-1">Resources for travel professionals at every stage of their journey.</p>
        </div>
        <nav class="flex flex-wrap gap-x-6 gap-y-2 font-semibold" aria-label="TARC links">
            <a href="{{ config('school.brand.site_url') }}" class="hover:text-ink">TARC main site</a>
            <a href="{{ config('school.brand.community_url') }}" class="hover:text-ink">Travel Pro Community</a>
            <a href="mailto:{{ config('school.brand.support_email') }}" class="hover:text-ink">Contact {{ config('school.brand.support_email') }}</a>
        </nav>
    </div>
    <p class="mx-auto max-w-6xl px-4 pb-8 text-xs text-muted sm:px-6">© {{ date('Y') }} {{ config('app.name') }}</p>
</footer>
@include('partials.toast')
</body>
</html>
