<!DOCTYPE html>
<html lang="en">
<head>@include('partials.head')</head>
<body class="min-h-screen">
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
        <nav class="-mx-2 flex flex-wrap gap-x-2 font-semibold" aria-label="TARC links">
            <a href="{{ config('school.brand.site_url') }}" class="rounded-lg px-2 py-2 hover:text-ink">TARC main site</a>
            <a href="{{ config('school.brand.community_url') }}" class="rounded-lg px-2 py-2 hover:text-ink">Travel Pro Community</a>
            <a href="mailto:{{ config('school.brand.support_email') }}" class="rounded-lg px-2 py-2 hover:text-ink">Contact {{ config('school.brand.support_email') }}</a>
        </nav>
    </div>
    <p class="mx-auto max-w-6xl px-4 pb-8 text-xs text-muted sm:px-6">© {{ date('Y') }} {{ config('app.name') }}</p>
</footer>
@include('partials.toast')
</body>
</html>
