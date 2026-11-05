<!DOCTYPE html>
<html lang="en">
<head>@include('partials.head')</head>
<body class="flex min-h-screen min-h-svh flex-col">
<header class="border-b border-line bg-white">
    @php
        $links = array_filter([
            ['Courses', route('home'), request()->routeIs('home', 'courses.*')],
            auth()->check() ? ['My learning', route('dashboard'), request()->routeIs('dashboard')] : null,
            ['Verify a certificate', route('certificates.verify'), request()->routeIs('certificates.verify')],
            ['TARC main site', config('school.brand.site_url'), false],
        ]);
    @endphp
    <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-3 px-4 sm:px-6">
        <div class="flex min-w-0 items-center gap-6 lg:gap-8">
            @include('partials.logo')
            <nav class="hidden items-center gap-1 text-sm font-semibold text-muted md:flex">
                @foreach ($links as [$label, $href, $on])
                    <a href="{{ $href }}" @class(['rounded-lg px-2.5 py-2 hover:bg-mist hover:text-ink', 'text-ink' => $on]) @if ($on) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
            </nav>
        </div>
        <div class="flex shrink-0 items-center gap-1.5">
            @include('partials.usermenu')
            {{-- Phones and small tablets: the same links in a menu --}}
            <details class="relative md:hidden" data-menu>
                <summary class="grid size-10 cursor-pointer list-none place-items-center rounded-lg text-ink hover:bg-mist" aria-label="Menu">
                    <svg viewBox="0 0 20 20" class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M3 5.5h14M3 10h14M3 14.5h14"/></svg>
                </summary>
                <nav class="absolute right-0 z-30 mt-2 w-60 overflow-hidden rounded-xl border border-line bg-white py-1 text-[15px] font-semibold shadow-lg" aria-label="Main">
                    @foreach ($links as [$label, $href, $on])
                        <a href="{{ $href }}" @class(['block px-4 py-3 hover:bg-mist', 'text-pine' => $on]) @if ($on) aria-current="page" @endif>{{ $label }}</a>
                    @endforeach
                </nav>
            </details>
        </div>
    </div>
</header>
<main class="mx-auto w-full max-w-6xl flex-1 px-4 py-10 sm:px-6">
    @include('partials.flash')
    @yield('content')
</main>
{{-- Footer: TARC navy band, always at the bottom of the screen on short pages (body is a flex column). --}}
<footer class="bg-pine-dark text-[15px] text-white/75">
    <div class="mx-auto grid max-w-6xl grid-cols-2 gap-x-6 gap-y-8 px-4 py-10 sm:px-6 md:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)] md:py-12">
        <div class="col-span-2 md:col-span-1">
            <a href="{{ config('school.brand.site_url') }}" class="inline-flex items-center gap-3 text-white">
                <span class="grid size-12 shrink-0 place-items-center rounded-full bg-white">@include('partials.mark', ['class' => 'size-10'])</span>
                <span class="leading-tight">
                    <span class="block font-bold">{{ config('app.name') }}</span>
                    <span class="block text-sm text-white/70">{{ config('school.brand.short_name') }}™ Learning</span>
                </span>
            </a>
            <p class="mt-4 max-w-sm leading-relaxed">Resources for travel professionals at every stage of their journey.</p>
        </div>
        <nav aria-label="Learning">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-white">Learning</h2>
            <ul class="mt-2">
                <li><a href="{{ route('home') }}" class="-mx-1 inline-block px-1 py-1.5 hover:text-white hover:underline">Courses</a></li>
                @auth<li><a href="{{ route('dashboard') }}" class="-mx-1 inline-block px-1 py-1.5 hover:text-white hover:underline">My learning</a></li>@endauth
                <li><a href="{{ route('certificates.verify') }}" class="-mx-1 inline-block px-1 py-1.5 hover:text-white hover:underline">Verify a certificate</a></li>
            </ul>
        </nav>
        <nav aria-label="TARC">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-white">TARC</h2>
            <ul class="mt-2">
                <li><a href="{{ config('school.brand.site_url') }}" class="-mx-1 inline-block px-1 py-1.5 hover:text-white hover:underline">Main site</a></li>
                <li><a href="{{ config('school.brand.community_url') }}" class="-mx-1 inline-block px-1 py-1.5 hover:text-white hover:underline">Travel Pro Community</a></li>
                <li><a href="mailto:{{ config('school.brand.support_email') }}" class="-mx-1 inline-block px-1 py-1.5 hover:text-white hover:underline">Contact us</a></li>
            </ul>
        </nav>
    </div>
    <div class="border-t border-white/15">
        <div class="mx-auto flex max-w-6xl flex-col gap-1 px-4 py-5 text-sm text-white/60 sm:flex-row sm:justify-between sm:px-6">
            <p>© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
            <p>Questions? <a href="mailto:{{ config('school.brand.support_email') }}" class="text-white/80 underline-offset-2 hover:text-white hover:underline">{{ config('school.brand.support_email') }}</a></p>
        </div>
    </div>
</footer>
@include('partials.toast')
</body>
</html>
