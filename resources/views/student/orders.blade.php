@extends('layouts.app', ['title' => 'Purchases'])
@section('content')
<h1 class="text-3xl font-extrabold">Purchases</h1>
<div class="panel mt-8 overflow-x-auto">
    @if ($orders->isEmpty())
        <p class="p-8 text-center text-muted">No purchases yet.</p>
    @else
    <table class="table">
        <thead><tr><th>Date</th><th>Course</th><th>Reference</th><th>Total</th><th>Status</th></tr></thead>
        <tbody>
        @foreach ($orders as $o)
            <tr>
                <td class="whitespace-nowrap">{{ $o->created_at->format('M j, Y') }}</td>
                <td>{{ $o->course->title }}</td>
                <td class="text-muted">{{ $o->reference }}</td>
                <td class="whitespace-nowrap">{{ money($o->total, $o->currency) }}@if ($o->discount > 0) <span class="text-xs text-muted">({{ money($o->discount, $o->currency) }} off)</span>@endif</td>
                <td>@include('partials.order-status', ['status' => $o->status])</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div>
@endsection
