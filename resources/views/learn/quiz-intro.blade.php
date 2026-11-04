@php $label = $quiz->is_final ? 'exam' : 'quiz'; @endphp
<div class="mt-8 panel overflow-hidden {{ $quiz->is_final ? 'border-saffron/50' : '' }}">
    <dl class="grid grid-cols-2 gap-px bg-line sm:grid-cols-4">
        @foreach ([
            ['Questions', $questions],
            ['Pass mark', $quiz->passing_score.'%'],
            ['Time limit', $quiz->time_limit_minutes ? $quiz->time_limit_minutes.' min' : 'None'],
            ['Attempts', $quiz->max_attempts ? $used.' of '.$quiz->max_attempts : $used.' used'],
        ] as [$k, $v])
            <div class="bg-white p-4"><dt class="text-xs text-muted">{{ $k }}</dt><dd class="mt-1 text-lg font-bold">{{ $v }}</dd></div>
        @endforeach
    </dl>
    <div class="p-5">
        @if ($passed)
            <p class="font-semibold text-pine">You passed this {{ $label }} with {{ $best }}%.</p>
        @elseif ($best !== null)
            <p class="font-semibold">Your best score so far is {{ $best }}%. You need {{ $quiz->passing_score }}% to pass.</p>
        @elseif ($quiz->is_final)
            <p class="text-sm text-muted">Pass the final exam to complete the course and earn your certificate.{{ $quiz->time_limit_minutes ? ' The timer starts when you begin and keeps running if you leave the page.' : '' }}</p>
        @endif

        <div class="mt-4 flex flex-wrap gap-2">
            @if ($open)
                <a href="{{ route('attempts.show', $open) }}" class="btn-primary">Resume {{ $label }}</a>
            @elseif ($questions === 0)
                <p class="text-sm text-muted">This {{ $label }} has no questions yet.</p>
            @elseif ($canAttempt)
                <form method="POST" action="{{ route('quiz.start', [$lesson->course, $lesson]) }}">@csrf
                    <button class="{{ $quiz->is_final ? 'btn-accent' : 'btn-primary' }}">{{ $used ? 'Try again' : 'Start '.$label }}</button>
                </form>
            @elseif (! $passed && $used)
                <p class="text-sm text-brick">You’ve used all {{ $quiz->max_attempts }} attempts. Contact your instructor if you need another.</p>
            @endif
        </div>

        @if ($attempts->isNotEmpty())
            <h3 class="mt-6 text-sm font-semibold">Your attempts</h3>
            <ul class="mt-2 divide-y divide-line/70 text-sm">
                @foreach ($attempts as $a)
                    <li class="flex items-center justify-between py-2">
                        <span>{{ $a->submitted_at->format('M j, Y g:i a') }}</span>
                        <span class="flex items-center gap-3">
                            <span class="font-semibold">{{ $a->score }}%</span>
                            @if ($a->passed)<span class="badge-green">Passed</span>@else<span class="badge-red">Not passed</span>@endif
                            <a href="{{ route('attempts.show', $a) }}" class="font-semibold text-pine">Review</a>
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
