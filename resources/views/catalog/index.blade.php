@extends('layouts.app', ['title' => 'Courses'])
@section('content')
@php $q = trim((string) request('q')); @endphp

{{-- Intro: headline, text and search, centred --}}
<section class="mx-auto max-w-4xl pt-4 text-center sm:pt-8">
    <p class="text-sm font-semibold text-pine">TARC learning</p>
    <h1 class="mt-2 text-4xl font-extrabold leading-[1.05] sm:text-5xl lg:text-6xl">Your travel business deserves structure, growth and support.</h1>
    <p class="mx-auto mt-5 max-w-3xl text-lg text-muted sm:text-xl">Self-paced courses for travel professionals at every stage, from setting up your agency to earning your Recognized Travel Advisor certification.</p>

    <form action="{{ route('home') }}#courses" role="search" class="mx-auto mt-8 flex max-w-3xl gap-2 text-left">
        <label class="sr-only" for="q">Search courses</label>
        <div class="relative min-w-0 flex-1">
            <svg viewBox="0 0 20 20" class="pointer-events-none absolute left-4 top-1/2 size-5 -translate-y-1/2 text-muted" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="9" cy="9" r="5.5"/><path d="m13.2 13.2 3.3 3.3"/></svg>
            <input id="q" name="q" type="search" value="{{ $q }}" class="input h-14 pl-12 text-base" placeholder="Search courses">
        </div>
        <button class="btn-primary h-14 px-6 text-base">Search</button>
    </form>
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
