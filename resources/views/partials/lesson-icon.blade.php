@php
    $paths = [
        'text' => '<path d="M5 4h10M5 8h10M5 12h6"/>',
        'video' => '<rect x="2.5" y="4" width="11" height="12" rx="2"/><path d="m13.5 8 4-2.5v9l-4-2.5"/>',
        'download' => '<path d="M10 3v10m0 0-4-4m4 4 4-4M4 16h12"/>',
        'quiz' => '<circle cx="10" cy="10" r="7"/><path d="M8 8a2 2 0 1 1 2.8 1.8c-.5.3-.8.7-.8 1.2v.5M10 14h.01"/>',
        'exam' => '<path d="M10 2.5 3 6v4c0 4 3 6.5 7 7.5 4-1 7-3.5 7-7.5V6z"/><path d="m7 10 2 2 4-4"/>',
    ];
@endphp
<svg viewBox="0 0 20 20" class="size-4 shrink-0 {{ $type === 'exam' ? 'text-saffron' : 'text-muted' }}" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-label="{{ \App\Models\Lesson::TYPES[$type] ?? $type }}">{!! $paths[$type] ?? $paths['text'] !!}</svg>
