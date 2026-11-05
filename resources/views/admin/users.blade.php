@extends('layouts.studio', ['title' => 'Users'])
@section('breadcrumb', 'Users')
@section('content')
<div class="flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-2xl font-bold">Users</h1>
    <form class="flex w-full flex-wrap gap-2 sm:w-auto">
        <label class="sr-only" for="q">Search</label><input id="q" name="q" value="{{ request('q') }}" class="input min-w-0 flex-1 sm:w-56 sm:flex-none" placeholder="Name or email">
        <label class="sr-only" for="role">Role</label>
        <select id="role" name="role" class="input w-auto" onchange="this.form.submit()"><option value="">All roles</option>@foreach (['admin', 'instructor', 'student'] as $r)<option value="{{ $r }}" @selected(request('role') === $r)>{{ ucfirst($r) }}s</option>@endforeach</select>
    </form>
</div>
<div class="panel mt-6 overflow-x-auto">
    <table class="table">
        <thead><tr><th>Name</th><th>Joined</th><th>Enrollments</th><th>Courses taught</th><th>Role</th></tr></thead>
        <tbody>
        @foreach ($users as $u)
            <tr>
                <td><div class="font-semibold">{{ $u->name }}</div><div class="text-xs text-muted">{{ $u->email }}</div></td>
                <td class="whitespace-nowrap">{{ $u->created_at->format('M j, Y') }}</td>
                <td>{{ $u->enrollments_count }}</td>
                <td>{{ $u->courses_count }}</td>
                <td>
                    <form method="POST" action="{{ route('admin.users.update', $u) }}" class="flex gap-2">@csrf @method('PUT')
                        <label class="sr-only" for="r{{ $u->id }}">Role for {{ $u->name }}</label>
                        <select id="r{{ $u->id }}" name="role" class="input w-auto py-1.5" onchange="this.form.submit()">@foreach (['admin', 'instructor', 'student'] as $r)<option value="{{ $r }}" @selected($u->role === $r)>{{ ucfirst($r) }}</option>@endforeach</select>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $users->links() }}</div>
@endsection
