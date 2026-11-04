<nav class="px-4 py-6" aria-label="Course contents">
    @foreach ($course->chapters as $chapter)
        <div class="mb-6">
            <h2 class="mb-1 px-1 text-xs font-semibold text-muted">{{ $chapter->title }}</h2>
            <ol class="path">
                @foreach ($chapter->lessons as $l)
                    @php $d = in_array($l->id, $done, true); $cur = $l->id === $lesson->id; $lk = $locks[$l->id]; @endphp
                    <li @class(['path-item', 'is-done' => $d, 'is-current' => $cur, 'is-locked' => $lk && ! $d])>
                        <span @class(['path-node', 'is-exam' => $l->type === 'exam'])>
                            @if ($d)<svg viewBox="0 0 12 12" class="size-2 {{ $l->type === 'exam' ? '-rotate-45' : '' }}" fill="none" stroke="#fff" stroke-width="2.4"><path d="m2.5 6.2 2.2 2.2 4.8-4.8"/></svg>@endif
                        </span>
                        <a href="{{ route('learn.lesson', [$course, $l]) }}" @class([
                            'flex items-start gap-2 rounded-lg px-2 py-2 text-sm leading-snug',
                            'bg-white font-semibold shadow-sm ring-1 ring-line' => $cur,
                            'hover:bg-white/70' => ! $cur,
                            'text-muted' => $lk && ! $cur,
                        ]) @if($cur) aria-current="page" @endif>
                            <span class="min-w-0 flex-1">{{ $l->title }}</span>
                            @if ($lk)<svg viewBox="0 0 24 24" class="mt-0.5 size-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" aria-label="Locked"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 1 1 8 0v3"/></svg>@endif
                        </a>
                    </li>
                @endforeach
            </ol>
        </div>
    @endforeach
    @if ($certificate)
        <a href="{{ route('certificates.show', $certificate) }}" class="mt-2 flex items-center gap-2 rounded-lg bg-saffron-soft px-3 py-2.5 text-sm font-semibold text-[#7a5408]">Your certificate</a>
    @endif
</nav>
