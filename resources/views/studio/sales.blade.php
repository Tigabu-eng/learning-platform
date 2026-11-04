@extends('layouts.studio', ['title' => 'Sales'])
@section('breadcrumb', 'Sales')
@section('content')
<h1 class="text-2xl font-bold">Sales</h1>
<div class="panel mt-6 overflow-x-auto">@include('partials.orders-table')</div>
<div class="mt-4">{{ $orders->links() }}</div>
@endsection
