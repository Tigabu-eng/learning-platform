@if ($orders->isEmpty())
    <p class="p-8 text-center text-muted">No orders yet.</p>
@else
<table class="table">
    <thead><tr><th>Date</th><th>Student</th><th>Course</th><th>Reference</th><th>Coupon</th><th>Total</th><th>Gateway</th><th>Status</th></tr></thead>
    <tbody>
    @foreach ($orders as $o)
        <tr>
            <td class="whitespace-nowrap">{{ $o->created_at->format('M j, Y') }}</td>
            <td><div class="font-semibold">{{ $o->user->name }}</div><div class="text-xs text-muted">{{ $o->user->email }}</div></td>
            <td>{{ $o->course->title }}</td>
            <td class="text-xs text-muted">{{ $o->reference }}</td>
            <td>{{ $o->coupon?->code ?? '—' }}</td>
            <td class="whitespace-nowrap font-semibold">{{ money($o->total, $o->currency) }}</td>
            <td class="capitalize">{{ $o->gateway }}</td>
            <td>@include('partials.order-status', ['status' => $o->status])</td>
        </tr>
    @endforeach
    </tbody>
</table>
@endif
