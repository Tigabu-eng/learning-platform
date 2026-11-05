@extends('layouts.studio', ['title' => 'Import preview'])
@section('breadcrumb')<a href="{{ route('studio.courses.index') }}" class="hover:text-ink">Courses</a> / <a href="{{ route('studio.curriculum', $course) }}" class="hover:text-ink">{{ $course->title }}</a> / <a href="{{ route('studio.lessons.edit', $lesson) }}" class="hover:text-ink">{{ $lesson->title }}</a> / Import @endsection
@section('content')
@php
    $items = collect($items);
    $bad = $items->filter(fn ($i) => $i['errors']);
    $dupes = $items->where('duplicate', true);
    $ready = $items->count() - $bad->count() - $dupes->count();
    $byTopic = $items->reject(fn ($i) => $i['errors'] || $i['duplicate'])->groupBy(fn ($i) => $i['topic'] === '' ? 'No topic' : $i['topic'])->map->count()->sortKeys();
    $types = \App\Models\Question::TYPES;
@endphp
<div class="max-w-5xl">
    <h1 class="text-2xl font-extrabold">Check before importing</h1>
    <p class="mt-1 text-sm text-muted">From <span class="font-semibold text-ink">{{ $source }}</span>: {{ $items->count() }} questions found.</p>

    <div class="mt-5 grid gap-3 sm:grid-cols-3">
        <div class="panel p-4"><div class="text-2xl font-extrabold text-pine">{{ $ready }}</div><div class="text-sm text-muted">ready to import</div></div>
        <div class="panel p-4"><div class="text-2xl font-extrabold {{ $bad->count() ? 'text-brick' : '' }}">{{ $bad->count() }}</div><div class="text-sm text-muted">with problems (skipped)</div></div>
        <div class="panel p-4"><div class="text-2xl font-extrabold">{{ $dupes->count() }}</div><div class="text-sm text-muted">already in this {{ $lesson->type === 'exam' ? 'exam' : 'quiz' }} (skipped)</div></div>
    </div>

    @if ($byTopic->isNotEmpty())
        <div class="mt-4 flex flex-wrap gap-1.5 text-xs">
            @foreach ($byTopic as $t => $n)<span class="rounded-full border border-line bg-white px-2.5 py-1"><span class="font-semibold">{{ $t }}</span> · {{ $n }}</span>@endforeach
        </div>
    @endif

    @if ($bad->count())
        <div class="mt-5 rounded-lg border border-brick/30 bg-brick-soft px-4 py-3 text-sm">
            <p class="font-semibold text-brick">{{ $bad->count() }} {{ Str::plural('question', $bad->count()) }} can't be imported as they are.</p>
            <p class="mt-1">You can import the rest now, then fix these in the file and import it again. Questions already added are skipped the second time.</p>
        </div>
    @endif

    <div class="mt-6 flex flex-wrap items-center gap-3">
        @if ($ready > 0)
            <form method="POST" action="{{ route('studio.questions.import.store', $lesson) }}">@csrf<input type="hidden" name="token" value="{{ $token }}">
                <button class="btn-primary">Import {{ $ready }} {{ Str::plural('question', $ready) }}</button></form>
        @endif
        <a href="{{ route('studio.lessons.edit', $lesson) }}#import" class="btn-ghost">Cancel</a>
    </div>

    <ol class="mt-6 space-y-3">
        @foreach ($items->sortByDesc(fn ($i) => (bool) $i['errors']) as $item)
            <li @class(['panel p-4', 'border-brick/40' => $item['errors'], 'opacity-60' => $item['duplicate']])>
                <div class="flex flex-wrap items-center gap-2 text-xs text-muted">
                    <span class="font-semibold">{{ $item['source'] }}</span>
                    @if ($item['topic'] !== '')<span>· {{ $item['topic'] }}</span>@endif
                    <span>· {{ $types[$item['type']] ?? $item['type'] }} · {{ $item['points'] }} pt</span>
                    @if ($item['errors'])<span class="badge-red">Problem</span>@elseif ($item['duplicate'])<span class="badge bg-mist text-muted">Already added</span>@endif
                </div>
                <p class="mt-1.5 whitespace-pre-line font-semibold">{{ $item['prompt'] ?: '(no question text)' }}</p>
                @if ($item['type'] === 'short')
                    <p class="mt-1 text-sm">Accepted: <span class="font-semibold text-pine">{{ str_replace("\n", ' | ', (string) $item['accepted_answers']) }}</span></p>
                @elseif ($item['options'])
                    <ul class="mt-1.5 space-y-0.5 text-sm">
                        @foreach ($item['options'] as $i => $o)
                            <li @class(['font-semibold text-pine' => $o['correct']])>{{ chr(65 + $i) }}. {{ $o['text'] }} @if ($o['correct'])✓@endif</li>
                        @endforeach
                    </ul>
                @endif
                @foreach ($item['errors'] as $e)<p class="mt-1.5 text-sm font-semibold text-brick">{{ $e }}</p>@endforeach
            </li>
        @endforeach
    </ol>
</div>
@endsection
