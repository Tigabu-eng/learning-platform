@extends('layouts.app', ['title' => 'Checkout'])
@section('content')
<div class="mx-auto grid max-w-4xl gap-8 md:grid-cols-[1fr_20rem]">
    <div>
        <h1 class="text-3xl font-extrabold">{{ $course->isFree() ? 'Enroll' : 'Checkout' }}</h1>
        <div class="panel mt-6 flex gap-4 p-5">
            <div class="aspect-[16/9] w-32 shrink-0 overflow-hidden rounded-lg bg-pine">
                @if ($course->thumbnailUrl())<img src="{{ $course->thumbnailUrl() }}" alt="" class="size-full object-cover">@else @include('catalog.cover') @endif
            </div>
            <div>
                <div class="font-bold">{{ $course->title }}</div>
                <div class="text-sm text-muted">{{ $course->instructor->name }}</div>
                <div class="mt-1 text-sm text-muted">{{ $course->access_days ? $course->access_days.' days of access' : 'Lifetime access' }}</div>
            </div>
        </div>

        @unless ($course->isFree())
            <form method="GET" action="{{ route('checkout.show', $course) }}" class="mt-6">
                <label class="label" for="coupon">Coupon code</label>
                <div class="flex gap-2">
                    <input id="coupon" name="coupon" value="{{ request('coupon') }}" class="input uppercase" placeholder="e.g. WELCOME20">
                    <button class="btn-ghost">Apply</button>
                </div>
                @if ($couponError)<p class="mt-1.5 text-sm text-brick">{{ $couponError }}</p>@endif
                @if ($coupon)<p class="mt-1.5 text-sm font-semibold text-pine">{{ $coupon->code }} applied — {{ $coupon->label() }}</p>@endif
            </form>
        @endunless
    </div>

    <div class="panel self-start p-6">
        <dl class="space-y-2 text-sm">
            <div class="flex justify-between"><dt class="text-muted">Price</dt><dd>{{ money($quote['amount']) }}</dd></div>
            @if ($quote['discount'] > 0)<div class="flex justify-between text-pine"><dt>Discount</dt><dd>− {{ money($quote['discount']) }}</dd></div>@endif
            <div class="flex justify-between border-t border-line pt-3 text-lg font-bold"><dt>Total</dt><dd>{{ money($quote['total']) }}</dd></div>
        </dl>
        <form method="POST" action="{{ route('checkout.pay', $course) }}" class="mt-6">@csrf
            <input type="hidden" name="coupon" value="{{ $coupon?->code }}">
            <button class="btn-primary w-full">{{ $quote['total'] > 0 ? 'Pay '.money($quote['total']) : 'Enroll now' }}</button>
        </form>
        @if ($quote['total'] > 0)
            <p class="mt-3 text-center text-xs text-muted">
                @switch($gateway)
                    @case('chapa') You’ll pay securely with Chapa (Telebirr, CBE Birr, cards). @break
                    @case('stripe') You’ll pay securely with Stripe. @break
                    @default Test mode — no real money is taken.
                @endswitch
            </p>
        @endif
    </div>
</div>
@endsection
