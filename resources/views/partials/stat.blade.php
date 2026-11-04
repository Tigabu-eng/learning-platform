<div class="panel p-5">
    <div class="text-sm text-muted">{{ $label }}</div>
    <div class="mt-1 text-2xl font-extrabold tabular-nums">{{ $value }}</div>
    @isset($sub)<div class="mt-0.5 text-xs text-muted">{{ $sub }}</div>@endisset
</div>
