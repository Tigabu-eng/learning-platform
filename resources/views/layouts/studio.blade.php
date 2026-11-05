<!DOCTYPE html>
<html lang="en">
<head>@include('partials.head')</head>
<body class="min-h-screen">
<div class="flex min-h-screen flex-col lg:flex-row">
    <aside class="border-b border-line bg-white lg:w-60 lg:shrink-0 lg:border-b-0 lg:border-r">
        <div class="flex h-16 items-center px-5">@include('partials.logo')</div>
        @php
            $nav = [
                ['studio.dashboard', 'Overview', 'studio.dashboard'],
                ['studio.courses.index', 'Courses', 'studio.courses.*|studio.curriculum|studio.lessons.*|studio.students*|studio.reports.*'],
                ['studio.coupons.index', 'Coupons', 'studio.coupons.*'],
                ['studio.sales', 'Sales', 'studio.sales'],
            ];
            if (auth()->user()->isAdmin()) {
                $nav[] = ['admin.dashboard', 'Admin overview', 'admin.dashboard'];
                $nav[] = ['admin.users', 'Users', 'admin.users'];
                $nav[] = ['admin.courses', 'All courses', 'admin.courses'];
                $nav[] = ['admin.orders', 'All orders', 'admin.orders'];
            }
        @endphp
        <nav class="flex gap-1 overflow-x-auto px-3 pb-3 text-sm font-semibold lg:flex-col lg:pb-6">
            @foreach ($nav as [$route, $label, $active])
                @if ($label === 'Admin overview')<div class="mt-4 hidden px-3 pb-1 text-xs font-medium text-muted lg:block">Admin</div>@endif
                <a href="{{ route($route) }}" @class([
                    'whitespace-nowrap rounded-lg px-3 py-2',
                    'bg-pine-soft text-pine-dark' => request()->routeIs(...explode('|', $active)),
                    'text-muted hover:bg-mist hover:text-ink' => ! request()->routeIs(...explode('|', $active)),
                ])>{{ $label }}</a>
            @endforeach
            <a href="{{ route('home') }}" class="whitespace-nowrap rounded-lg px-3 py-2 text-muted hover:bg-mist hover:text-ink lg:mt-4">View school</a>
        </nav>
    </aside>
    <div class="min-w-0 flex-1">
        <header class="flex h-16 items-center justify-between gap-4 border-b border-line bg-white px-4 sm:px-8">
            <div class="min-w-0 truncate text-sm text-muted [&_a]:py-3">@yield('breadcrumb')</div>
            @include('partials.usermenu')
        </header>
        <main class="px-4 py-8 sm:px-8">
            @isset($course)
                @if ($course instanceof \App\Models\Course && $course->exists && ! request()->routeIs('admin.*'))
                    @include('studio.partials.course-nav')
                @endif
            @endisset
            @include('partials.flash')
            @yield('content')
        </main>
    </div>
</div>
@include('partials.toast')
</body>
</html>
