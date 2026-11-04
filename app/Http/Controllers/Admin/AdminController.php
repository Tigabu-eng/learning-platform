<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function dashboard()
    {
        $paid = Order::where('status', 'paid');
        $stats = [
            'users' => User::count(),
            'students' => User::where('role', 'student')->count(),
            'instructors' => User::where('role', 'instructor')->count(),
            'courses' => Course::count(),
            'published' => Course::where('status', 'published')->count(),
            'enrollments' => Enrollment::count(),
            'certificates' => Certificate::count(),
            'revenue' => (clone $paid)->sum('total'),
            'orders' => (clone $paid)->count(),
        ];
        $months = collect(range(5, 0))->map(fn ($m) => now()->startOfMonth()->subMonths($m));
        $byMonth = Order::where('status', 'paid')->where('paid_at', '>=', $months->first())->get(['total', 'paid_at'])
            ->groupBy(fn ($o) => $o->paid_at->format('Y-m'))->map(fn ($g) => $g->sum('total'));
        $revenue = $months->map(fn ($m) => ['label' => $m->format('M'), 'value' => (float) ($byMonth[$m->format('Y-m')] ?? 0)]);
        $topCourses = Course::withCount('enrollments')->with('instructor')->orderByDesc('enrollments_count')->take(5)->get();
        $recentOrders = Order::with(['user', 'course'])->latest()->take(8)->get();
        return view('admin.dashboard', compact('stats', 'revenue', 'topCourses', 'recentOrders'));
    }

    public function users(Request $request)
    {
        $users = User::withCount(['enrollments', 'courses'])
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%")))
            ->when($request->role, fn ($q, $r) => $q->where('role', $r))
            ->orderBy('name')->paginate(30)->withQueryString();
        return view('admin.users', compact('users'));
    }

    public function updateUser(Request $request, User $user)
    {
        $data = $request->validate(['role' => 'required|in:admin,instructor,student']);
        if ($user->id === $request->user()->id && $data['role'] !== 'admin') {
            return back()->withErrors(['role' => 'You can’t remove your own admin access.']);
        }
        $user->update($data);
        return back()->with('status', $user->name.' is now '.($data['role'] === 'admin' ? 'an admin' : 'a'.($data['role'] === 'instructor' ? 'n instructor' : ' student')).'.');
    }

    public function courses()
    {
        $courses = Course::with('instructor')->withCount(['enrollments', 'lessons'])->withSum(['orders as revenue' => fn ($q) => $q->where('status', 'paid')], 'total')->latest()->get();
        return view('admin.courses', compact('courses'));
    }

    public function orders(Request $request)
    {
        $orders = Order::with(['user', 'course', 'coupon'])->when($request->status, fn ($q, $s) => $q->where('status', $s))->latest()->paginate(30)->withQueryString();
        return view('admin.orders', compact('orders'));
    }
}
