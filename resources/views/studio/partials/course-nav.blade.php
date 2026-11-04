<div class="mb-8">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <h1 class="truncate text-2xl font-bold">{{ $course->title }}</h1>
            <div class="mt-1 flex items-center gap-2 text-sm text-muted">
                @if ($course->isPublished())<span class="badge-green">Published</span>@else<span class="badge-grey">Draft</span>@endif
                <span>{{ $course->priceLabel() }}</span>
            </div>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('courses.show', $course) }}" class="btn-ghost btn-sm" target="_blank" rel="noopener">Preview sales page</a>
            <form method="POST" action="{{ route('studio.courses.publish', $course) }}">@csrf
                <button class="{{ $course->isPublished() ? 'btn-ghost' : 'btn-primary' }} btn-sm">{{ $course->isPublished() ? 'Move to draft' : 'Publish course' }}</button>
            </form>
        </div>
    </div>
    <nav class="mt-5 flex gap-1 overflow-x-auto border-b border-line text-sm font-semibold">
        @foreach ([
            ['studio.curriculum', 'Curriculum', 'studio.curriculum|studio.lessons.*'],
            ['studio.courses.edit', 'Settings & pricing', 'studio.courses.edit'],
            ['studio.students', 'Students', 'studio.students*'],
            ['studio.reports.quizzes', 'Quiz results', 'studio.reports.*'],
        ] as [$route, $label, $active])
            <a href="{{ route($route, $course) }}" @class([
                '-mb-px whitespace-nowrap border-b-2 px-3 py-2.5',
                'border-pine text-ink' => request()->routeIs(...explode('|', $active)),
                'border-transparent text-muted hover:text-ink' => ! request()->routeIs(...explode('|', $active)),
            ])>{{ $label }}</a>
        @endforeach
    </nav>
</div>
