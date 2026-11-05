@extends('layouts.app', ['title' => 'Courses'])
@section('content')
<section class="mb-12 grid items-end grid-cols-1 gap-8 md:grid-cols-[minmax(0,1fr)_auto]">
    <div>
        <p class="text-sm font-semibold text-pine">TARC learning</p>
        <h1 class="mt-2 max-w-2xl text-4xl font-extrabold leading-[1.05] sm:text-5xl">Your travel business deserves structure, growth and support.</h1>
        <p class="mt-4 max-w-xl text-lg text-muted">Self-paced courses for travel professionals at every stage, from setting up your agency to earning your Recognized Travel Advisor certification. Pass the final exam to get a certificate that clients, suppliers and host agencies can verify.</p>
    </div>
    <form action="{{ route('home') }}" class="w-full md:w-80">
        <label class="sr-only" for="q">Search courses</label>
        <input id="q" name="q" value="{{ request('q') }}" class="input py-2.5" placeholder="Search courses">
    </form>
</section>

@if ($courses->isEmpty())
    <div class="panel p-10 text-center">
        <p class="font-semibold">{{ request('q') ? 'No courses match “'.request('q').'”.' : 'No courses are published yet.' }}</p>
        @if (request('q'))<a href="{{ route('home') }}" class="mt-3 inline-block text-sm font-semibold text-pine">Show all courses</a>@endif
    </div>
@else
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($courses as $course) @include('catalog.card') @endforeach
    </div>
@endif
@endsection
