<a href="{{ route('courses.show', $course) }}" class="group flex flex-col overflow-hidden rounded-xl border border-line bg-white hover:border-pine/50">
    <div class="aspect-[16/9] overflow-hidden bg-pine">
        @if ($course->thumbnailUrl())
            <img src="{{ $course->thumbnailUrl() }}" alt="" class="size-full object-cover">
        @else
            @include('catalog.cover', ['course' => $course])
        @endif
    </div>
    <div class="flex flex-1 flex-col p-5">
        <h3 class="text-lg font-bold leading-snug group-hover:text-pine">{{ $course->title }}</h3>
        @if ($course->subtitle)<p class="mt-1.5 line-clamp-2 text-sm text-muted">{{ $course->subtitle }}</p>@endif
        <div class="mt-auto flex items-center justify-between pt-5 text-sm">
            <span class="text-muted">{{ $course->instructor->name }} · {{ $course->lessons_count }} lessons</span>
            <span class="font-bold">{{ $course->priceLabel() }}</span>
        </div>
    </div>
</a>
