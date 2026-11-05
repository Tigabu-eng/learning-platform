{{-- Simple accessible bar chart. $data = [['label' => ..., 'value' => ...]], $money = bool --}}
@php $max = max(1, collect($data)->max('value')); $money ??= false; $many = count($data) > 8; @endphp
<div class="grid h-40 min-w-0 items-end gap-1 sm:gap-1.5" style="grid-template-columns: repeat({{ max(1, count($data)) }}, minmax(0, 1fr))" role="img" aria-label="{{ $aria ?? 'Chart' }}">
    @foreach ($data as $d)
        @php $value = $money ? number_format($d['value']) : $d['value']; @endphp
        <div class="group flex h-full min-w-0 flex-col items-center justify-end gap-1.5" title="{{ $d['label'] }}: {{ $value }}">
            <div class="text-[11px] font-semibold text-muted opacity-0 group-hover:opacity-100 max-sm:hidden">{{ $value }}</div>
            <div class="w-full rounded-t {{ $d['value'] ? 'bg-pine' : 'bg-line' }}" style="height: {{ max(3, $d['value'] / $max * 100) }}%"></div>
            {{-- On phones, label every third bar so the dates stay readable. --}}
            <div @class(['w-full whitespace-nowrap text-center text-[11px] text-muted sm:truncate', 'max-sm:invisible' => $many && ($loop->remaining % 3) !== 0])>{{ $d['label'] }}</div>
        </div>
    @endforeach
</div>
