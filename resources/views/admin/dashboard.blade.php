@extends('layouts.studio', ['title' => 'Admin'])
@section('breadcrumb', 'Admin overview')
@section('content')
<h1 class="text-2xl font-bold">School overview</h1>
<div class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
    @include('partials.stat', ['label' => 'Revenue', 'value' => money($stats['revenue']), 'sub' => $stats['orders'].' paid orders'])
    @include('partials.stat', ['label' => 'Users', 'value' => number_format($stats['users']), 'sub' => $stats['students'].' students · '.$stats['instructors'].' instructors'])
    @include('partials.stat', ['label' => 'Courses', 'value' => $stats['courses'], 'sub' => $stats['published'].' published'])
    @include('partials.stat', ['label' => 'Enrollments', 'value' => number_format($stats['enrollments']), 'sub' => $stats['certificates'].' certificates issued'])
</div>
<div class="mt-6 grid gap-6 lg:grid-cols-[1fr_22rem]">
    <div class="panel p-5"><h2 class="font-bold">Revenue by month ({{ config('school.currency') }})</h2><div class="mt-5">@include('partials.bars', ['data' => $revenue, 'money' => true, 'aria' => 'Revenue by month'])</div></div>
    <div class="panel p-5">
        <h2 class="font-bold">Most popular courses</h2>
        <ol class="mt-3 divide-y divide-line/70 text-sm">
            @forelse ($topCourses as $c)
                <li class="flex items-center justify-between gap-3 py-2.5"><div class="min-w-0"><div class="truncate font-semibold">{{ $c->title }}</div><div class="text-xs text-muted">{{ $c->instructor->name }}</div></div><span class="shrink-0 text-xs font-semibold">{{ $c->enrollments_count }} students</span></li>
            @empty <li class="py-4 text-muted">No courses yet.</li> @endforelse
        </ol>
    </div>
</div>
<div class="panel mt-6 overflow-x-auto">
    <div class="flex items-center justify-between px-5 pt-5"><h2 class="font-bold">Recent orders</h2><a href="{{ route('admin.orders') }}" class="text-sm font-semibold text-pine">All orders</a></div>
    <div class="mt-2">@include('partials.orders-table', ['orders' => $recentOrders])</div>
</div>
@endsection
