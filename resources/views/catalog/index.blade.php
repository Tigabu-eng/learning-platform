@extends('layouts.app', ['title' => 'Courses'])
@section('content')
@php $q = trim((string) request('q')); @endphp

{{-- Intro: text and search on the left, why-TARC panel on the right --}}
<section class="grid grid-cols-1 items-center gap-8 lg:grid-cols-[minmax(0,1fr)_24rem] lg:gap-12">
    <div>
        <p class="text-sm font-semibold text-pine">TARC learning</p>
        <h1 class="mt-2 max-w-2xl text-4xl font-extrabold leading-[1.05] sm:text-5xl">Your travel business deserves structure, growth and support.</h1>
        <p class="mt-4 max-w-xl text-lg text-muted">Self-paced courses for travel professionals at every stage, from setting up your agency to earning your Recognized Travel Advisor certification.</p>

        <form action="{{ route('home') }}#courses" role="search" class="mt-7 flex max-w-xl gap-2">
            <label class="sr-only" for="q">Search courses</label>
            <div class="relative min-w-0 flex-1">
                <svg viewBox="0 0 20 20" class="pointer-events-none absolute left-3.5 top-1/2 size-5 -translate-y-1/2 text-muted" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="9" cy="9" r="5.5"/><path d="m13.2 13.2 3.3 3.3"/></svg>
                <input id="q" name="q" type="search" value="{{ $q }}" class="input h-12 pl-11 text-base" placeholder="Search courses">
            </div>
            <button class="btn-primary h-12 px-5">Search</button>
        </form>
    </div>

    <aside class="rounded-2xl bg-pine-dark p-6 text-white sm:p-8" aria-label="Why learn with TARC">
        <h2 class="text-lg font-bold">Why learn with TARC</h2>
        <ul class="mt-5 space-y-5 text-[15px]">
            @foreach ([
                ['Built by travel advisors', 'Practical lessons on the parts of the industry you sell every day.', '<path d="M10 2.5 3 6v4c0 4 3 6.6 7 7.5 4-.9 7-3.5 7-7.5V6l-7-3.5z"/><path d="m7 10 2 2 4-4"/>'],
                ['Learn at your own pace', 'Short lessons and quizzes you can take anywhere, on any device.', '<circle cx="10" cy="10" r="7.5"/><path d="M10 6v4.2l2.8 1.8"/>'],
                ['A certificate you can prove', 'Pass the final exam to earn a certificate clients and suppliers can verify online.', '<rect x="3" y="3.5" width="14" height="10" rx="1.5"/><path d="M7 17.5 10 15l3 2.5V13"/>'],
            ] as [$title, $text, $icon])
                <li class="flex gap-3.5">
                    <span class="grid size-9 shrink-0 place-items-center rounded-full bg-white/10 text-saffron">
                        <svg viewBox="0 0 20 20" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icon !!}</svg>
                    </span>
                    <span><span class="block font-semibold text-white">{{ $title }}</span><span class="mt-0.5 block text-white/75">{{ $text }}</span></span>
                </li>
            @endforeach
        </ul>
    </aside>
</section>

{{-- Courses --}}
<section id="courses" class="mt-14 scroll-mt-6">
    <div class="flex flex-wrap items-end justify-between gap-3 border-b border-line pb-4">
        <div>
            <h2 class="text-2xl font-bold">{{ $q !== '' ? 'Results for “'.$q.'”' : 'Courses' }}</h2>
            <p class="mt-1 text-sm text-muted">{{ $courses->count() }} {{ Str::plural('course', $courses->count()) }}{{ $q !== '' ? ' found' : '' }}</p>
        </div>
        @if ($q !== '')<a href="{{ route('home') }}#courses" class="-my-2 py-2 text-sm font-semibold text-pine">Clear search</a>@endif
    </div>

    @if ($courses->isEmpty())
        <div class="panel mt-6 p-10 text-center">
            <p class="font-semibold">{{ $q !== '' ? 'No courses match “'.$q.'”.' : 'No courses are published yet.' }}</p>
            <p class="mt-1 text-sm text-muted">{{ $q !== '' ? 'Try a shorter word, or browse all courses.' : 'Check back soon.' }}</p>
            @if ($q !== '')<a href="{{ route('home') }}#courses" class="btn-ghost mt-4">Show all courses</a>@endif
        </div>
    @else
        <div class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($courses as $course) @include('catalog.card') @endforeach
        </div>
    @endif
</section>
@endsection
