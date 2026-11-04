@extends('layouts.studio', ['title' => 'Quiz results – '.$course->title])
@section('breadcrumb')<a href="{{ route('studio.courses.index') }}" class="hover:text-ink">Courses</a> / {{ $course->title }} / Quiz results @endsection
@section('content')
@if ($reports->isEmpty())
    <div class="panel p-10 text-center text-muted">This course has no quizzes or exams yet.</div>
@else
<div class="space-y-6">
    @foreach ($reports as $r)
        <section class="panel">
            <header class="flex flex-wrap items-center justify-between gap-4 border-b border-line px-5 py-4">
                <div class="flex items-center gap-2">@include('partials.lesson-icon', ['type' => $r['lesson']->type])<h2 class="font-bold">{{ $r['lesson']->title }}</h2></div>
                <dl class="flex gap-6 text-sm">
                    <div><dt class="text-xs text-muted">Students</dt><dd class="font-bold">{{ $r['students'] }}</dd></div>
                    <div><dt class="text-xs text-muted">Attempts</dt><dd class="font-bold">{{ $r['attempts'] }}</dd></div>
                    <div><dt class="text-xs text-muted">Average</dt><dd class="font-bold">{{ $r['average'] !== null ? $r['average'].'%' : '—' }}</dd></div>
                    <div><dt class="text-xs text-muted">Students passed</dt><dd class="font-bold">{{ $r['passRate'] !== null ? $r['passRate'].'%' : '—' }}</dd></div>
                </dl>
            </header>
            <table class="table">
                <thead><tr><th>Question</th><th class="w-24">Answered</th><th class="w-56">Answered correctly</th></tr></thead>
                <tbody>
                @foreach ($r['questions'] as $i => $q)
                    <tr>
                        <td><span class="mr-2 text-muted">{{ $i + 1 }}.</span>{{ \Illuminate\Support\Str::limit($q['question']->prompt, 110) }}</td>
                        <td>{{ $q['answered'] }}</td>
                        <td>@if ($q['rate'] !== null)
                            <div class="flex items-center gap-2"><div class="h-2 flex-1 rounded-full bg-mist"><div class="h-2 rounded-full {{ $q['rate'] < 50 ? 'bg-brick' : 'bg-pine' }}" style="width: {{ $q['rate'] }}%"></div></div><span class="w-10 text-right text-xs font-semibold">{{ $q['rate'] }}%</span></div>
                        @else<span class="text-muted">—</span>@endif</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </section>
    @endforeach
    <p class="text-xs text-muted">Questions most students get wrong are shown in red — they often point to a lesson that needs clearer explanation.</p>
</div>
@endif
@endsection
