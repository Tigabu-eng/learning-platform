@extends('layouts.app', ['title' => 'Verify a certificate'])
@section('content')
<div class="mx-auto max-w-lg">
    <h1 class="text-3xl font-extrabold">Verify a certificate</h1>
    <p class="mt-2 text-muted">Enter the certificate ID printed at the bottom of the certificate.</p>
    <form class="mt-6 flex gap-2">
        <label for="code" class="sr-only">Certificate ID</label>
        <input id="code" name="code" value="{{ $code }}" class="input uppercase" placeholder="ABCD-EFGH-IJKL" required>
        <button class="btn-primary">Verify</button>
    </form>
    @if ($code)
        @if ($certificate)
            <div class="panel mt-8 border-pine/40 p-6">
                <span class="badge-green">Valid certificate</span>
                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-muted">Awarded to</dt><dd class="font-semibold">{{ $certificate->user->name }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-muted">Course</dt><dd class="text-right font-semibold">{{ $certificate->course->title }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-muted">Issued</dt><dd>{{ $certificate->issued_at->format('F j, Y') }}</dd></div>
                    @if ($certificate->final_score !== null)<div class="flex justify-between gap-4"><dt class="text-muted">Final exam</dt><dd>{{ $certificate->final_score }}%</dd></div>@endif
                </dl>
                <a href="{{ route('certificates.show', $certificate) }}" class="btn-ghost btn-sm mt-5">View certificate</a>
            </div>
        @else
            <div class="mt-8 rounded-lg border border-brick/25 bg-brick-soft px-4 py-3 text-sm text-brick">No certificate has the ID {{ $code }}. Check the ID for typos and try again.</div>
        @endif
    @endif
</div>
@endsection
