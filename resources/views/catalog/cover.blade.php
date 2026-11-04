{{-- Generated cover: the course's own initial on a lesson-path motif. --}}
@php $hue = crc32($course->slug) % 3; $bg = ['#1f6f5c', '#24504a', '#3c6b3a'][$hue]; @endphp
<svg viewBox="0 0 320 180" class="size-full" role="img" aria-label="{{ $course->title }}">
    <rect width="320" height="180" fill="{{ $bg }}"/>
    <path d="M-10 150 C 60 150, 70 60, 140 70 S 230 140, 330 40" stroke="#d9971a" stroke-width="3" fill="none" opacity=".9"/>
    @foreach ([[40, 146], [140, 70], [235, 118], [300, 56]] as $i => [$x, $y])
        <circle cx="{{ $x }}" cy="{{ $y }}" r="7" fill="{{ $i < 2 ? '#d9971a' : $bg }}" stroke="#d9971a" stroke-width="3"/>
    @endforeach
    <text x="22" y="62" fill="#fff" font-family="Schibsted Grotesk, sans-serif" font-weight="800" font-size="44" opacity=".95">{{ mb_strtoupper(mb_substr($course->title, 0, 1)) }}</text>
</svg>
