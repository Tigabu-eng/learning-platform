@extends('layouts.studio', ['title' => 'All courses'])
@section('breadcrumb', 'All courses')
@section('content')
<h1 class="text-2xl font-bold">All courses</h1>
<div class="panel mt-6 overflow-x-auto">
    <table class="table">
        <thead><tr><th>Course</th><th>Instructor</th><th>Status</th><th>Price</th><th>Lessons</th><th>Students</th><th>Revenue</th><th></th></tr></thead>
        <tbody>
        @foreach ($courses as $c)
            <tr>
                <td class="font-semibold">{{ $c->title }}</td>
                <td>{{ $c->instructor->name }}</td>
                <td>@if ($c->isPublished())<span class="badge-green">Published</span>@else<span class="badge-grey">Draft</span>@endif</td>
                <td class="whitespace-nowrap">{{ $c->priceLabel() }}</td>
                <td>{{ $c->lessons_count }}</td>
                <td>{{ $c->enrollments_count }}</td>
                <td class="whitespace-nowrap">{{ money($c->revenue ?? 0) }}</td>
                <td class="text-right"><a href="{{ route('studio.curriculum', $c) }}" class="font-semibold text-pine">Manage</a></td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endsection
