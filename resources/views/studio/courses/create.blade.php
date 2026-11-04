@extends('layouts.studio', ['title' => 'New course'])
@section('breadcrumb')<a href="{{ route('studio.courses.index') }}" class="hover:text-ink">Courses</a> / New course @endsection
@section('content')
<div class="max-w-xl">
    <h1 class="text-2xl font-bold">New course</h1>
    <p class="mt-1 text-muted">Start with the basics. You can change everything later.</p>
    <form method="POST" action="{{ route('studio.courses.store') }}" class="panel mt-6 space-y-5 p-6">@csrf
        <div><label class="label" for="title">Course title</label><input id="title" name="title" class="input" value="{{ old('title') }}" required maxlength="160" autofocus placeholder="e.g. Selling river cruises"></div>
        <div><label class="label" for="subtitle">One-line summary</label><input id="subtitle" name="subtitle" class="input" value="{{ old('subtitle') }}" maxlength="240" placeholder="What will students be able to do after this course?"></div>
        <div><label class="label" for="price">Price ({{ config('school.currency') }})</label><input id="price" name="price" type="number" step="0.01" min="0" class="input" value="{{ old('price', 0) }}"><p class="hint">Use 0 for a free course.</p></div>
        <button class="btn-primary">Create course</button>
    </form>
</div>
@endsection
