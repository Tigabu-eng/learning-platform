@extends('layouts.studio', ['title' => 'Curriculum – '.$course->title])
@section('breadcrumb')<a href="{{ route('studio.courses.index') }}" class="hover:text-ink">Courses</a> / {{ $course->title }} / Curriculum @endsection
@section('content')
@php $hasExam = $course->chapters->flatMap->lessons->contains('type', 'exam'); @endphp
<div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_18rem]">
    <div class="min-w-0">
        <div data-curriculum data-reorder-url="{{ route('studio.curriculum.reorder', $course) }}" class="space-y-4">
            @foreach ($course->chapters as $chapter)
                <section data-chapter="{{ $chapter->id }}" class="panel">
                    <header class="flex items-center gap-3 border-b border-line px-4 py-3">
                        <button type="button" data-chapter-handle class="drag-handle text-muted" aria-label="Drag to reorder chapter">@include('studio.partials.grip')</button>
                        <details class="min-w-0 flex-1">
                            <summary class="cursor-pointer list-none font-bold">{{ $chapter->title }} <span class="ml-1 text-xs font-normal text-muted">rename</span></summary>
                            <form method="POST" action="{{ route('studio.chapters.update', $chapter) }}" class="mt-2 flex gap-2">@csrf @method('PUT')
                                <input name="title" value="{{ $chapter->title }}" class="input" required><button class="btn-ghost btn-sm">Save</button>
                            </form>
                        </details>
                        <form method="POST" action="{{ route('studio.chapters.destroy', $chapter) }}" data-confirm="Delete “{{ $chapter->title }}” and its {{ $chapter->lessons->count() }} lessons?">@csrf @method('DELETE')
                            <button class="btn-danger btn-sm">Delete</button>
                        </form>
                    </header>
                    <ul data-lessons class="min-h-6 divide-y divide-line/70">
                        @foreach ($chapter->lessons as $lesson)
                            <li data-lesson="{{ $lesson->id }}" class="flex items-center gap-3 bg-white px-4 py-2.5">
                                <span class="drag-handle text-muted" aria-label="Drag to reorder">@include('studio.partials.grip')</span>
                                @include('partials.lesson-icon', ['type' => $lesson->type])
                                <a href="{{ route('studio.lessons.edit', $lesson) }}" class="min-w-0 flex-1 truncate text-sm font-semibold hover:text-pine">{{ $lesson->title }}</a>
                                <span class="hidden items-center gap-2 text-xs text-muted sm:flex">
                                    @if ($lesson->quiz)<span>{{ $lesson->quiz->questions()->count() }} questions</span>@endif
                                    @if ($lesson->is_preview)<span class="badge-gold">Free preview</span>@endif
                                    @if ($course->drip_enabled && $lesson->drip_days)<span class="badge-grey">Day {{ $lesson->drip_days }}</span>@endif
                                    <span>{{ $lesson->typeLabel() }}</span>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                    <form method="POST" action="{{ route('studio.lessons.store', $chapter) }}" class="flex flex-wrap gap-2 border-t border-line bg-mist/50 px-4 py-3">@csrf
                        <label class="sr-only" for="lt{{ $chapter->id }}">New lesson title</label>
                        <input id="lt{{ $chapter->id }}" name="title" class="input min-w-48 flex-1" placeholder="New lesson title" required>
                        <label class="sr-only" for="ty{{ $chapter->id }}">Lesson type</label>
                        <select id="ty{{ $chapter->id }}" name="type" class="input w-auto">
                            @foreach (\App\Models\Lesson::TYPES as $k => $v)
                                @if ($k !== 'exam' || ! $hasExam)<option value="{{ $k }}">{{ $v }}</option>@endif
                            @endforeach
                        </select>
                        <button class="btn-primary">Add lesson</button>
                    </form>
                </section>
            @endforeach
        </div>
        <form method="POST" action="{{ route('studio.chapters.store', $course) }}" class="mt-4 flex gap-2">@csrf
            <label class="sr-only" for="newchapter">New chapter title</label>
            <input id="newchapter" name="title" class="input max-w-sm" placeholder="New chapter title" required>
            <button class="btn-ghost">Add chapter</button>
        </form>
    </div>
    <aside class="space-y-4 text-sm">
        <div class="panel p-5">
            <h2 class="font-bold">Building your course</h2>
            <ul class="mt-3 space-y-2 text-muted">
                <li>Drag lessons between chapters, or drag chapters by their handle. The order saves automatically.</li>
                <li>Mark a lesson as a free preview to show it on the sales page.</li>
                <li>Add one final exam. Passing it is required for the certificate.</li>
            </ul>
        </div>
        <div class="panel p-5">
            <h2 class="font-bold">Checklist</h2>
            @php $lc = $course->chapters->sum(fn ($c) => $c->lessons->count()); @endphp
            <ul class="mt-3 space-y-2">
                <li class="flex gap-2"><span>{{ $lc ? '✓' : '○' }}</span> At least one lesson</li>
                <li class="flex gap-2"><span>{{ $hasExam ? '✓' : '○' }}</span> Final exam</li>
                <li class="flex gap-2"><span>{{ $course->description ? '✓' : '○' }}</span> <a href="{{ route('studio.courses.edit', $course) }}" class="text-pine">Course description</a></li>
                <li class="flex gap-2"><span>{{ $course->isPublished() ? '✓' : '○' }}</span> Published</li>
            </ul>
        </div>
        <a href="{{ route('learn.resume', $course) }}" class="btn-ghost w-full">Preview as a student</a>
    </aside>
</div>
@endsection
