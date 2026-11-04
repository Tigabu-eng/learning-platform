@extends('layouts.studio', ['title' => 'All orders'])
@section('breadcrumb', 'All orders')
@section('content')
<div class="flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-2xl font-bold">All orders</h1>
    <form><label class="sr-only" for="status">Status</label>
        <select id="status" name="status" class="input w-auto" onchange="this.form.submit()"><option value="">All statuses</option>@foreach (['paid' => 'Paid', 'pending' => 'Pending', 'failed' => 'Not completed'] as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach</select>
    </form>
</div>
<div class="panel mt-6 overflow-x-auto">@include('partials.orders-table')</div>
<div class="mt-4">{{ $orders->links() }}</div>
@endsection
