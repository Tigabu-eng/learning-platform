<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use Illuminate\Http\Request;

class StudioController extends Controller
{
    public static function courseIds(Request $request)
    {
        $u = $request->user();
        return $u->isAdmin() ? Course::pluck('id') : $u->courses()->pluck('id');
    }

    public function dashboard(Request $request)
    {
        $ids = self::courseIds($request);
        $paid = Order::whereIn('course_id', $ids)->where('status', 'paid');

        $stats = [
            'revenue' => (clone $paid)->sum('total'),
            'revenue30' => (clone $paid)->where('paid_at', '>=', now()->subDays(30))->sum('total'),
            'students' => Enrollment::whereIn('course_id', $ids)->distinct('user_id')->count('user_id'),
            'enrollments30' => Enrollment::whereIn('course_id', $ids)->where('enrolled_at', '>=', now()->subDays(30))->count(),
            'completions' => Enrollment::whereIn('course_id', $ids)->whereNotNull('completed_at')->count(),
            'courses' => $ids->count(),
        ];

        // Daily enrollments for the last 14 days
        $days = collect(range(13, 0))->map(fn ($d) => now()->subDays($d)->startOfDay());
        $counts = Enrollment::whereIn('course_id', $ids)->where('enrolled_at', '>=', $days->first())
            ->get(['enrolled_at'])->groupBy(fn ($e) => $e->enrolled_at->format('Y-m-d'))->map->count();
        $trend = $days->map(fn ($d) => ['label' => $d->format('M j'), 'value' => $counts[$d->format('Y-m-d')] ?? 0]);

        $courses = Course::whereIn('id', $ids)->withCount(['enrollments', 'lessons'])
            ->withAvg('enrollments', 'progress_percent')->latest()->get();
        $recent = Enrollment::whereIn('course_id', $ids)->with(['user', 'course'])->latest('enrolled_at')->take(8)->get();

        return view('studio.dashboard', compact('stats', 'trend', 'courses', 'recent'));
    }

    public function sales(Request $request)
    {
        $orders = Order::whereIn('course_id', self::courseIds($request))->with(['user', 'course', 'coupon'])->latest()->paginate(25);
        return view('studio.sales', compact('orders'));
    }
}
