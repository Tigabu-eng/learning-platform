@extends('layouts.app', ['title' => 'Test payment'])
@section('content')
<div class="mx-auto max-w-md">
    <div class="rounded-lg border border-saffron/50 bg-saffron-soft px-4 py-3 text-sm text-[#7a5408]">
        <strong>Test mode.</strong> This page stands in for a real payment provider so you can try the full checkout. Set <code>PAYMENT_GATEWAY=chapa</code> or <code>stripe</code> in <code>.env</code> to take real payments.
    </div>
    <div class="panel mt-6 p-6">
        <div class="text-sm text-muted">Order {{ $order->reference }}</div>
        <div class="mt-1 text-lg font-bold">{{ $order->course->title }}</div>
        <div class="mt-4 text-3xl font-extrabold">{{ money($order->total, $order->currency) }}</div>
        <form method="POST" class="mt-6 grid grid-cols-2 gap-3">@csrf
            <button name="decision" value="decline" class="btn-ghost">Decline payment</button>
            <button name="decision" value="approve" class="btn-primary">Approve payment</button>
        </form>
    </div>
</div>
@endsection
