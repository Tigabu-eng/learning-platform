@extends('layouts.app', ['title' => 'Courses'])
@section('content')
<section class="mb-12 grid items-end gap-8 md:grid-cols-[1fr_auto]">
    <div>
        <h1 class="max-w-2xl text-4xl font-extrabold leading-[1.05] sm:text-5xl">Training for travel advisors, from first booking to certified pro.</h1>
        <p class="mt-4 max-w-xl text-lg text-muted">Each course is a path of short lessons and quizzes. Pass the final exam to earn a certificate your clients and suppliers can verify.</p>
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
    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($courses as $course) @include('catalog.card') @endforeach
    </div>
@endif
@endsection
