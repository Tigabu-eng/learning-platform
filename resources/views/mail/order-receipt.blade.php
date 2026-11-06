<x-mail::message>
@if ((float) $order->total > 0)
# Thank you for your purchase

Hi {{ $user->name }},

Your payment for **{{ $course->title }}** went through, and you're enrolled. Keep this email as your receipt.
@else
# You're enrolled

Hi {{ $user->name }},

You're enrolled in **{{ $course->title }}**.
@endif

<x-mail::table>
| | |
|:--|--:|
| Course | {{ $course->title }} |
| Order | {{ $order->reference }} |
| Date | {{ ($order->paid_at ?? $order->created_at)->format('M j, Y') }} |
| Price | {{ money($order->amount, $order->currency) }} |
@if ((float) $order->discount > 0)
| Discount{{ $order->coupon ? ' ('.$order->coupon->code.')' : '' }} | −{{ money($order->discount, $order->currency) }} |
@endif
| **Total paid** | **{{ money($order->total, $order->currency) }}** |
</x-mail::table>

<x-mail::button :url="route('learn.resume', $course)">Start learning</x-mail::button>

Your orders are also listed in your account under **Orders**. Questions about this payment? Reply to this email or write to {{ config('school.brand.support_email') }}, and include the order number.

{{ config('app.name') }}
</x-mail::message>
