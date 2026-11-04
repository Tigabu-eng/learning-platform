{{-- Simple accessible bar chart. $data = [['label' => ..., 'value' => ...]], $money = bool --}}
@php $max = max(1, collect($data)->max('value')); $money ??= false; @endphp
<div class="flex h-40 items-end gap-1.5" role="img" aria-label="{{ $aria ?? 'Chart' }}">
    @foreach ($data as $d)
        <div class="group flex h-full flex-1 flex-col items-center justify-end gap-1.5">
            <div class="text-[10px] font-semibold text-muted opacity-0 group-hover:opacity-100">{{ $money ? number_format($d['value']) : $d['value'] }}</div>
            <div class="w-full rounded-t {{ $d['value'] ? 'bg-pine' : 'bg-line' }}" style="height: {{ max(3, $d['value'] / $max * 100) }}%"></div>
            <div class="w-full truncate text-center text-[10px] text-muted">{{ $d['label'] }}</div>
        </div>
    @endforeach
</div>
