<a href="{{ route('home') }}" class="flex items-center gap-2.5 text-ink" aria-label="{{ config('app.name') }} home">
    @include('partials.mark', ['class' => 'size-8 shrink-0'])
    <span class="leading-none">
        <span class="block text-[15px] font-extrabold tracking-tight">Travel Advisor</span>
        <span class="mt-0.5 block text-[11px] font-semibold text-muted">Resource Center</span>
    </span>
</a>
