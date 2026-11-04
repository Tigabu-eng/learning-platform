@if (session('status'))
    <div class="mb-6 rounded-lg border border-pine/25 bg-pine-soft px-4 py-3 text-sm font-medium text-pine-dark" role="status">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="mb-6 rounded-lg border border-brick/25 bg-brick-soft px-4 py-3 text-sm text-brick" role="alert">
        @if ($errors->count() === 1)
            {{ $errors->first() }}
        @else
            <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        @endif
    </div>
@endif
