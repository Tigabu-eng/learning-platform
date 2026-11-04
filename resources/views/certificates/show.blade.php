<!DOCTYPE html>
<html lang="en">
<head>
@include('partials.head', ['title' => 'Certificate – '.$certificate->course->title])
<style>
    @page { size: A4 landscape; margin: 0; }
    @media print { .no-print { display: none !important; } body { background: #fff !important; } .sheet { box-shadow: none !important; margin: 0 !important; } }
    .sheet { aspect-ratio: 297 / 210; }
</style>
</head>
<body class="bg-mist">
<div class="no-print mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-3 px-4 py-5">
    @include('partials.logo')
    <div class="flex gap-2">
        @auth<a href="{{ route('dashboard') }}" class="btn-ghost btn-sm">My learning</a>@endauth
        <button onclick="window.print()" class="btn-primary btn-sm">Print or save as PDF</button>
    </div>
</div>
<div class="sheet relative mx-auto mb-10 w-full max-w-5xl overflow-hidden bg-white shadow-xl">
    <svg class="absolute inset-0 size-full" viewBox="0 0 297 210" preserveAspectRatio="none" aria-hidden="true">
        <rect x="8" y="8" width="281" height="194" fill="none" stroke="#1f6f5c" stroke-width="1.2"/>
        <rect x="11" y="11" width="275" height="188" fill="none" stroke="#d9971a" stroke-width=".4"/>
        <path d="M11 192 C 70 192, 85 180, 140 182 S 232 197, 286 184" stroke="#d9971a" stroke-width=".7" fill="none" opacity=".5"/>
        @foreach ([[44, 190.6], [140, 182], [228, 192.4], [286, 184]] as [$x, $y])<circle cx="{{ $x }}" cy="{{ $y }}" r="1.6" fill="#d9971a" opacity=".55"/>@endforeach
    </svg>
    <div class="relative flex h-full flex-col items-center justify-center px-[9%] text-center">
        <div class="flex items-center gap-2 text-[clamp(10px,1.4vw,14px)] font-bold text-pine">
            @include('partials.mark', ['class' => 'size-[1.8em]'])
            {{ config('app.name') }} ({{ config('school.brand.short_name') }})™
        </div>
        <div class="mt-[3%] text-[clamp(12px,1.9vw,20px)] font-semibold text-muted">Certificate of completion</div>
        <div class="mt-[2.5%] text-[clamp(26px,5.4vw,58px)] font-extrabold leading-none tracking-tight">{{ $certificate->user->name }}</div>
        <div class="mt-[3%] text-[clamp(11px,1.5vw,16px)] text-muted">has successfully completed</div>
        <div class="mt-[1%] max-w-[80%] text-[clamp(16px,2.8vw,30px)] font-bold leading-tight text-pine-dark">{{ $certificate->course->title }}</div>
        @if ($certificate->final_score !== null)
            <div class="mt-[2%] text-[clamp(11px,1.4vw,15px)]">Final exam score: <strong>{{ $certificate->final_score }}%</strong></div>
        @endif
        <div class="mt-[6%] grid w-full grid-cols-3 items-end gap-4 text-[clamp(9px,1.15vw,13px)]">
            <div class="text-left"><div class="font-semibold">{{ $certificate->issued_at->format('F j, Y') }}</div><div class="border-t border-line pt-1 text-muted">Date issued</div></div>
            <div><div class="mx-auto grid size-[clamp(40px,6vw,72px)] place-items-center rounded-full bg-saffron text-[clamp(8px,1vw,11px)] font-extrabold leading-tight text-ink">VERIFIED</div></div>
            <div class="text-right"><div class="font-semibold">{{ $certificate->course->instructor->name }}</div><div class="border-t border-line pt-1 text-muted">Instructor</div></div>
        </div>
        <div class="mt-[4%] text-[clamp(8px,1vw,11px)] text-muted">Certificate ID {{ $certificate->code }} · Verify at {{ route('certificates.verify') }}?code={{ $certificate->code }}</div>
    </div>
</div>
</body>
</html>
