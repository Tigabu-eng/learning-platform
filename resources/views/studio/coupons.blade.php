@extends('layouts.studio', ['title' => 'Coupons'])
@section('breadcrumb', 'Coupons')
@section('content')
<h1 class="text-2xl font-bold">Coupons</h1>
<div class="mt-6 grid gap-6 xl:grid-cols-[1fr_20rem]">
    <div class="panel overflow-x-auto">
        @if ($coupons->isEmpty())
            <p class="p-8 text-center text-muted">No coupons yet. Create one to run a launch discount.</p>
        @else
        <table class="table">
            <thead><tr><th>Code</th><th>Discount</th><th>Applies to</th><th>Used</th><th>Expires</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @foreach ($coupons as $c)
                <tr>
                    <td class="font-bold">{{ $c->code }}</td>
                    <td>{{ $c->label() }}</td>
                    <td>{{ $c->course?->title ?? 'All my courses' }}</td>
                    <td>{{ $c->uses }}{{ $c->max_uses ? ' / '.$c->max_uses : '' }}</td>
                    <td class="whitespace-nowrap">{{ $c->expires_at?->format('M j, Y') ?? 'Never' }}</td>
                    <td>@if ($c->active)<span class="badge-green">On</span>@else<span class="badge-grey">Off</span>@endif</td>
                    <td class="whitespace-nowrap text-right">
                        <form method="POST" action="{{ route('studio.coupons.toggle', $c) }}" class="inline">@csrf<button class="btn-ghost btn-sm">{{ $c->active ? 'Turn off' : 'Turn on' }}</button></form>
                        <form method="POST" action="{{ route('studio.coupons.destroy', $c) }}" class="inline" data-confirm="Delete coupon {{ $c->code }}?">@csrf @method('DELETE')<button class="btn-danger btn-sm">Delete</button></form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        @endif
    </div>
    <form method="POST" action="{{ route('studio.coupons.store') }}" class="panel space-y-4 self-start p-5">@csrf
        <h2 class="font-bold">New coupon</h2>
        <div><label class="label" for="code">Code</label><input id="code" name="code" class="input uppercase" value="{{ old('code') }}" required placeholder="WELCOME20"></div>
        <div class="grid grid-cols-2 gap-3">
            <div><label class="label" for="type">Type</label><select id="type" name="type" class="input"><option value="percent">Percent</option><option value="fixed" @selected(old('type') === 'fixed')>Fixed amount</option></select></div>
            <div><label class="label" for="value">Value</label><input id="value" name="value" type="number" step="0.01" min="0.01" class="input" value="{{ old('value') }}" required></div>
        </div>
        <div><label class="label" for="course_id">Course</label><select id="course_id" name="course_id" class="input"><option value="">All my courses</option>@foreach ($courses as $co)<option value="{{ $co->id }}" @selected(old('course_id') == $co->id)>{{ $co->title }}</option>@endforeach</select></div>
        <div class="grid grid-cols-2 gap-3">
            <div><label class="label" for="max_uses">Max uses</label><input id="max_uses" name="max_uses" type="number" min="1" class="input" value="{{ old('max_uses') }}" placeholder="No limit"></div>
            <div><label class="label" for="expires_at">Expires</label><input id="expires_at" name="expires_at" type="date" class="input" value="{{ old('expires_at') }}"></div>
        </div>
        <button class="btn-primary w-full">Create coupon</button>
    </form>
</div>
@endsection
