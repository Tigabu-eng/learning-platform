@switch($status)
    @case('paid')<span class="badge-green">Paid</span>@break
    @case('pending')<span class="badge-gold">Pending</span>@break
    @case('failed')<span class="badge-red">Not completed</span>@break
    @default<span class="badge-grey">{{ ucfirst($status) }}</span>
@endswitch
