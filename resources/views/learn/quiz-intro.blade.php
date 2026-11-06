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
                @php $locked = $quiz->needsAccessCheck() && ! $lesson->course->canBeManagedBy(auth()->user()); @endphp
                <form method="POST" action="{{ route('quiz.start', [$lesson->course, $lesson]) }}" class="{{ $locked ? 'w-full max-w-md space-y-3' : '' }}">@csrf
                    @if ($locked)
                        <div class="rounded-lg border border-line bg-mist p-4">
                            <label class="label" for="access_code">{{ $quiz->access_mode === 'code' ? 'Proctor access code' : 'Exam password' }}</label>
                            <input id="access_code" name="access_code" class="input font-mono text-lg tracking-wider" autocomplete="off" autocapitalize="{{ $quiz->access_mode === 'code' ? 'characters' : 'off' }}" spellcheck="false" required
                                   placeholder="{{ $quiz->access_mode === 'code' ? 'XXXX-XXXX' : '' }}">
                            @error('access_code')<p class="mt-2 text-sm font-semibold text-brick">{{ $message }}</p>@enderror
                            <p class="hint">Your proctor enters this when you’re ready to begin. Questions about proctoring? Email {{ config('school.brand.support_email') }}.</p>
                            @if ($quiz->access_mode === 'code')
                                @php $proctorReq = \App\Models\ProctorRequest::where('quiz_id', $quiz->id)->whereHas('enrollment', fn ($e) => $e->where('user_id', auth()->id())->where('course_id', $lesson->course_id))->latest('id')->first(); @endphp
                                <p class="mt-3 border-t border-line pt-3 text-sm">
                                    @if ($proctorReq?->isPending())
                                        Your proctor request is <span class="font-semibold">waiting for review</span>.
                                    @elseif ($proctorReq?->status === 'approved' && $proctorReq->code?->isActive())
                                        Approved: <span class="font-semibold">{{ $proctorReq->proctor_name }}</span> has your code.
                                    @elseif ($proctorReq?->status === 'declined')
                                        Your last proctor request was declined.
                                    @else
                                        Don’t have a proctor yet?
                                    @endif
                                    <a href="{{ route('proctor-requests.create', [$lesson->course, $lesson]) }}" class="-my-2 inline-block py-2 font-semibold text-pine">{{ $proctorReq ? 'View or update your request' : 'Request a proctored exam' }} →</a>
                                </p>
                            @endif
                        </div>
                    @endif
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
