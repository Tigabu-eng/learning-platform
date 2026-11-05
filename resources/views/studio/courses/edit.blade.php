@extends('layouts.studio', ['title' => 'Settings – '.$course->title])
@section('breadcrumb')<a href="{{ route('studio.courses.index') }}" class="hover:text-ink">Courses</a> / {{ $course->title }} / Settings @endsection
@section('content')
<form method="POST" action="{{ route('studio.courses.update', $course) }}" enctype="multipart/form-data" class="grid max-w-5xl grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
    @csrf @method('PUT')
    <div class="space-y-6">
        <section class="panel space-y-5 p-6">
            <h2 class="font-bold">Sales page</h2>
            <div><label class="label" for="title">Title</label><input id="title" name="title" class="input" value="{{ old('title', $course->title) }}" required></div>
            <div><label class="label" for="subtitle">Summary</label><input id="subtitle" name="subtitle" class="input" value="{{ old('subtitle', $course->subtitle) }}"></div>
            <div><label class="label" for="description">Description</label><textarea id="description" name="description" rows="10" class="input font-[inherit]" data-rich>{{ \App\Support\RichText::forEditor(old('description', $course->description)) }}</textarea></div>
            <div><label class="label" for="slug">Web address</label>
                <div class="flex items-center rounded-lg border border-line bg-mist text-sm"><span class="pl-3 text-muted">{{ url('/courses') }}/</span><input id="slug" name="slug" class="input rounded-l-none border-0 border-l" value="{{ old('slug', $course->slug) }}" required></div>
            </div>
            <div><label class="label" for="level">Level</label>
                <select id="level" name="level" class="input">@foreach (['Beginner', 'Intermediate', 'Advanced', 'All levels'] as $lv)<option @selected(old('level', $course->level) === $lv)>{{ $lv }}</option>@endforeach</select>
            </div>
            <div><label class="label" for="thumbnail">Course image</label>
                @if ($course->thumbnailUrl())<img src="{{ $course->thumbnailUrl() }}" alt="" class="mb-3 aspect-[16/9] w-60 rounded-lg object-cover">@endif
                <input id="thumbnail" name="thumbnail" type="file" accept="image/*" class="text-sm"><p class="hint">16:9, at least 1280×720. Without one, a cover is generated.</p></div>
        </section>
        <section class="panel space-y-4 p-6">
            <h2 class="font-bold">Learning rules</h2>
            <label class="flex gap-3"><input type="checkbox" name="sequential" value="1" class="check mt-0.5" @checked($course->sequential)><span><span class="block text-sm font-semibold">Lessons must be completed in order</span><span class="text-xs text-muted">Students can’t skip ahead until they finish the lesson before.</span></span></label>
            <label class="flex gap-3"><input type="checkbox" name="drip_enabled" value="1" class="check mt-0.5" @checked($course->drip_enabled)><span><span class="block text-sm font-semibold">Release lessons over time</span><span class="text-xs text-muted">Set “days after enrollment” on each lesson to open it on a schedule.</span></span></label>
            <label class="flex gap-3"><input type="checkbox" name="certificate_enabled" value="1" class="check mt-0.5" @checked($course->certificate_enabled)><span><span class="block text-sm font-semibold">Issue a certificate on completion</span><span class="text-xs text-muted">Issued when every lesson is complete and the final exam (if any) is passed.</span></span></label>
        </section>
    </div>
    <div class="space-y-6">
        <section class="panel space-y-5 p-6">
            <h2 class="font-bold">Pricing & access</h2>
            <div><label class="label" for="price">Price ({{ config('school.currency') }})</label><input id="price" name="price" type="number" step="0.01" min="0" class="input" value="{{ old('price', $course->price) }}"><p class="hint">0 makes the course free.</p></div>
            <div><label class="label" for="access_days">Access length (days)</label><input id="access_days" name="access_days" type="number" min="1" class="input" value="{{ old('access_days', $course->access_days) }}" placeholder="Lifetime"><p class="hint">Leave empty for lifetime access.</p></div>
        </section>
        <button class="btn-primary w-full">Save settings</button>
    </div>
</form>
<form method="POST" action="{{ route('studio.courses.destroy', $course) }}" class="mt-10 max-w-5xl border-t border-line pt-6" data-confirm="Delete this course and all of its lessons? This can’t be undone.">@csrf @method('DELETE')
    <button class="btn-danger btn-sm">Delete course</button>
</form>
@endsection
