@php
    $uid = $question?->id ?? 'new';
    $type = old('_qid') == $uid ? old('type', $question?->type ?? 'single') : ($question?->type ?? 'single');
    $opts = $question && in_array($question->type, ['single', 'multiple']) ? $question->options->values() : collect();
    $rows = $opts->isNotEmpty() ? $opts->map(fn ($o) => ['text' => $o->text, 'correct' => $o->is_correct]) : collect(range(1, 4))->map(fn () => ['text' => '', 'correct' => false]);
    $tf = $question && $question->type === 'true_false' ? ($question->options->firstWhere('is_correct', true)?->text === 'True' ? 'true' : 'false') : 'true';
@endphp
<form method="POST" action="{{ $action }}" data-question-form class="space-y-4">
    @csrf @if ($method !== 'POST') @method($method) @endif
    <input type="hidden" name="_qid" value="{{ $uid }}">
    <div class="grid gap-4 sm:grid-cols-[1fr_8rem]">
        <div><label class="label" for="type-{{ $uid }}">Question type</label>
            <select id="type-{{ $uid }}" name="type" class="input">@foreach (\App\Models\Question::TYPES as $k => $v)<option value="{{ $k }}" @selected($type === $k)>{{ $v }}</option>@endforeach</select></div>
        <div><label class="label" for="points-{{ $uid }}">Points</label><input id="points-{{ $uid }}" name="points" type="number" min="1" max="100" value="{{ $question?->points ?? 1 }}" class="input"></div>
    </div>
    <div><label class="label" for="topic-{{ $uid }}">Topic or module <span class="font-normal text-muted">(optional)</span></label>
        <input id="topic-{{ $uid }}" name="topic" list="question-topics" maxlength="120" value="{{ old('_qid') == $uid ? old('topic') : $question?->topic }}" class="input" placeholder="e.g. Module 4">
        <p class="hint">When each attempt draws from the bank, every topic gets a fair share of the questions.</p></div>
    <div><label class="label" for="prompt-{{ $uid }}">Question</label><textarea id="prompt-{{ $uid }}" name="prompt" rows="2" class="input" required data-rich="compact">{{ \App\Support\RichText::forEditor(old('_qid') == $uid ? old('prompt') : $question?->prompt, false) }}</textarea>@error('prompt')@if (old('_qid') == $uid)<p class="mt-1 text-sm text-brick">{{ $message }}</p>@endif @enderror</div>

    <div data-show-for="single multiple">
        <div class="label">Answer choices <span class="font-normal text-muted">— mark the correct one(s)</span></div>
        <div data-option-list class="space-y-2">
            @foreach ($rows as $i => $row)
                <div data-option class="flex items-center gap-2">
                    <input data-correct type="{{ $type === 'multiple' ? 'checkbox' : 'radio' }}" name="correct[]" value="{{ $i }}" @checked($row['correct']) class="check" aria-label="Correct answer">
                    <input type="text" name="options[{{ $i }}][text]" value="{{ $row['text'] }}" class="input" placeholder="Choice">
                    <button type="button" data-remove-option class="btn-danger btn-sm" aria-label="Remove choice">×</button>
                </div>
            @endforeach
        </div>
        <button type="button" data-add-option class="mt-2 text-sm font-semibold text-pine">+ Add choice</button>
    </div>

    <div data-show-for="true_false">
        <div class="label">Correct answer</div>
        <div class="flex gap-4 text-sm">
            <label class="flex items-center gap-2"><input type="radio" name="tf_answer" value="true" class="check" @checked($tf === 'true')> True</label>
            <label class="flex items-center gap-2"><input type="radio" name="tf_answer" value="false" class="check" @checked($tf === 'false')> False</label>
        </div>
    </div>

    <div data-show-for="short">
        <label class="label" for="acc-{{ $uid }}">Accepted answers</label>
        <textarea id="acc-{{ $uid }}" name="accepted_answers" rows="3" class="input" placeholder="One per line">{{ $question?->accepted_answers }}</textarea>
        <p class="hint">Matching ignores capital letters and extra spaces.</p>
    </div>

    <div><label class="label" for="exp-{{ $uid }}">Explanation <span class="font-normal text-muted">(optional)</span></label><textarea id="exp-{{ $uid }}" name="explanation" rows="2" class="input" data-rich="compact">{{ \App\Support\RichText::forEditor($question?->explanation, false) }}</textarea><p class="hint">Shown to students after they submit.</p></div>
    <button class="btn-primary btn-sm">{{ $question ? 'Save question' : 'Add question' }}</button>
</form>
