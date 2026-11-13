<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\User;
use App\Services\Checkout;
use App\Services\TpdUserResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TpdApiController extends Controller
{
    public function health()
    {
        return response()->json([
            'ok' => true,
            'app' => config('app.name'),
            'currency' => config('school.currency'),
            'version' => 'tpd-bridge-1',
        ]);
    }

    public function catalog(Request $request)
    {
        $statusFilter = $request->query('status', 'published');
        $query = Course::query()
            ->with('instructor:id,name')
            ->withCount(['chapters', 'lessons', 'enrollments'])
            ->withSum('lessons as total_minutes', 'duration_minutes');

        if ($statusFilter !== 'all') {
            $status = in_array($statusFilter, ['published', 'draft'], true) ? $statusFilter : 'published';
            $query->where('status', $status);
        }

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('subtitle', 'like', "%{$search}%");
            });
        }

        $courses = $query->latest()->get()->map(fn (Course $course) => [
            'id' => $course->id,
            'slug' => $course->slug,
            'title' => $course->title,
            'subtitle' => $course->subtitle,
            'description' => $course->description,
            'level' => $course->level,
            'price' => (float) $course->price,
            'price_label' => $course->priceLabel(),
            'currency' => config('school.currency'),
            'is_free' => $course->isFree(),
            'status' => $course->status,
            'thumbnail_url' => $course->thumbnail_path ? url(Storage::url($course->thumbnail_path)) : null,
            'chapters_count' => (int) $course->chapters_count,
            'lessons_count' => (int) $course->lessons_count,
            'enrollments_count' => (int) $course->enrollments_count,
            'total_minutes' => (int) ($course->total_minutes ?? 0),
            'certificate_enabled' => (bool) $course->certificate_enabled,
            'access_days' => $course->access_days,
            'instructor' => $course->instructor ? [
                'id' => $course->instructor->id,
                'name' => $course->instructor->name,
            ] : null,
            'learn_path' => route('learn.resume', $course, false),
            'catalog_path' => route('courses.show', $course, false),
            'studio_path' => route('studio.curriculum', $course, false),
            'updated_at' => $course->updated_at?->toIso8601String(),
        ]);

        return response()->json([
            'currency' => config('school.currency'),
            'courses' => $courses->values(),
        ]);
    }

    public function enrollments(Request $request, TpdUserResolver $resolver)
    {
        $data = $request->validate([
            'tpd_user_id' => 'nullable|integer|min:1',
            'email' => 'nullable|email|max:190',
        ]);

        if (empty($data['tpd_user_id']) && empty($data['email'])) {
            return response()->json(['message' => 'Provide tpd_user_id or email.'], 422);
        }

        $user = $resolver->findOnly(
            isset($data['tpd_user_id']) ? (int) $data['tpd_user_id'] : null,
            $data['email'] ?? null,
        );

        if (! $user) {
            return response()->json([
                'user' => null,
                'enrollments' => [],
            ]);
        }

        $enrollments = $user->enrollments()
            ->with(['course.instructor', 'certificate', 'order', 'lastLesson'])
            ->latest('last_activity_at')
            ->latest('enrolled_at')
            ->get()
            ->map(fn (Enrollment $enrollment) => $this->formatEnrollment($enrollment));

        return response()->json([
            'user' => [
                'id' => $user->id,
                'tpd_user_id' => $user->tpd_user_id,
                'email' => $user->email,
                'name' => $user->name,
                'role' => $user->role,
            ],
            'enrollments' => $enrollments->values(),
        ]);
    }

    public function enroll(Request $request, TpdUserResolver $resolver, Checkout $checkout)
    {
        $data = $request->validate([
            'tpd_user_id' => 'required|integer|min:1',
            'email' => 'required|email|max:190',
            'name' => 'required|string|max:120',
            'course' => 'required|string|max:160',
            'external_reference' => 'required|string|max:100',
            'amount' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'total' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:8',
            'gateway' => 'nullable|string|max:32',
            'allow_draft' => 'nullable|boolean',
            'meta' => 'nullable|array',
        ]);

        $courseQuery = Course::where('slug', $data['course']);
        if (ctype_digit($data['course'])) {
            $courseQuery->orWhere('id', (int) $data['course']);
        }
        $course = $courseQuery->first();

        if (! $course) {
            return response()->json(['message' => 'Course not found.'], 404);
        }

        if (! $course->isPublished() && ! $request->boolean('allow_draft')) {
            return response()->json(['message' => 'Course is not published.'], 422);
        }

        $existingOrder = Order::where('reference', $data['external_reference'])->first();
        if ($existingOrder) {
            if ((int) $existingOrder->course_id !== (int) $course->id) {
                return response()->json(['message' => 'External reference is already assigned to another course.'], 409);
            }

            if (! $existingOrder->isPaid()) {
                $checkout->fulfil($existingOrder);
            }

            $existingOrder = $existingOrder->fresh(['user', 'course', 'coupon']);
            $enrollment = $existingOrder->user->enrollments()
                ->with(['course.instructor', 'certificate', 'order', 'lastLesson'])
                ->where('course_id', $course->id)
                ->first();

            return response()->json([
                'idempotent' => true,
                'user' => [
                    'id' => $existingOrder->user->id,
                    'tpd_user_id' => $existingOrder->user->tpd_user_id,
                    'email' => $existingOrder->user->email,
                    'name' => $existingOrder->user->name,
                    'role' => $existingOrder->user->role,
                ],
                'order' => $this->formatOrder($existingOrder),
                'enrollment' => $enrollment ? $this->formatEnrollment($enrollment) : null,
            ], 200);
        }

        $result = DB::transaction(function () use ($data, $course, $resolver, $checkout) {
            $user = $resolver->resolveOrCreate(
                (int) $data['tpd_user_id'],
                $data['email'],
                $data['name'],
                'student',
            );

            $amount = round((float) ($data['amount'] ?? $course->price), 2);
            $discount = round((float) ($data['discount'] ?? 0), 2);
            $total = round((float) ($data['total'] ?? max(0, $amount - $discount)), 2);

            $order = Order::create([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'coupon_id' => null,
                'reference' => $data['external_reference'],
                'amount' => $amount,
                'discount' => $discount,
                'total' => $total,
                'currency' => strtoupper((string) ($data['currency'] ?? config('school.currency'))),
                'gateway' => $data['gateway'] ?? ($total > 0 ? 'tpd' : 'free'),
                'status' => 'pending',
                'meta' => array_merge($data['meta'] ?? [], [
                    'source' => 'tpd',
                    'tpd_user_id' => (int) $data['tpd_user_id'],
                ]),
            ]);

            $checkout->fulfil($order);

            $order = $order->fresh(['user', 'course']);
            $enrollment = $user->enrollments()
                ->with(['course.instructor', 'certificate', 'order', 'lastLesson'])
                ->where('course_id', $course->id)
                ->first();

            return compact('user', 'order', 'enrollment');
        });

        return response()->json([
            'idempotent' => false,
            'user' => [
                'id' => $result['user']->id,
                'tpd_user_id' => $result['user']->tpd_user_id,
                'email' => $result['user']->email,
                'name' => $result['user']->name,
                'role' => $result['user']->role,
            ],
            'order' => $this->formatOrder($result['order']),
            'enrollment' => $result['enrollment'] ? $this->formatEnrollment($result['enrollment']) : null,
        ], 201);
    }

    public function adminOverview()
    {
        $paid = Order::where('status', 'paid');

        $stats = [
            'users' => User::count(),
            'students' => User::where('role', 'student')->count(),
            'instructors' => User::where('role', 'instructor')->count(),
            'courses' => Course::count(),
            'published_courses' => Course::where('status', 'published')->count(),
            'draft_courses' => Course::where('status', 'draft')->count(),
            'enrollments' => Enrollment::count(),
            'enrollments_30d' => Enrollment::where('enrolled_at', '>=', now()->subDays(30))->count(),
            'completions' => Enrollment::whereNotNull('completed_at')->count(),
            'certificates' => Certificate::count(),
            'paid_orders' => (clone $paid)->count(),
            'total_revenue' => (float) (clone $paid)->sum('total'),
            'revenue_30d' => (float) (clone $paid)->where('paid_at', '>=', now()->subDays(30))->sum('total'),
            'currency' => config('school.currency'),
        ];

        $months = collect(range(5, 0))->map(fn ($m) => now()->startOfMonth()->subMonths($m));
        $byMonth = Order::where('status', 'paid')
            ->where('paid_at', '>=', $months->first())
            ->get(['total', 'paid_at'])
            ->groupBy(fn ($o) => $o->paid_at->format('Y-m'))
            ->map(fn ($g) => (float) $g->sum('total'));

        $monthlyRevenue = $months->map(fn ($m) => [
            'month' => $m->format('Y-m'),
            'label' => $m->format('M Y'),
            'value' => (float) ($byMonth[$m->format('Y-m')] ?? 0),
        ])->values();

        $courses = Course::with('instructor:id,name')
            ->withCount(['enrollments', 'lessons'])
            ->withSum(['orders as revenue' => fn ($q) => $q->where('status', 'paid')], 'total')
            ->latest()
            ->get()
            ->map(fn (Course $course) => [
                'id' => $course->id,
                'slug' => $course->slug,
                'title' => $course->title,
                'status' => $course->status,
                'price' => (float) $course->price,
                'enrollments_count' => (int) $course->enrollments_count,
                'lessons_count' => (int) $course->lessons_count,
                'revenue' => (float) ($course->revenue ?? 0),
                'instructor_name' => $course->instructor?->name,
                'studio_path' => route('studio.curriculum', $course, false),
                'edit_path' => route('studio.courses.edit', $course, false),
            ]);

        $recentOrders = Order::with(['user:id,tpd_user_id,name,email', 'course:id,slug,title'])
            ->latest()
            ->take(20)
            ->get()
            ->map(fn (Order $order) => $this->formatOrder($order));

        return response()->json([
            'stats' => $stats,
            'monthly_revenue' => $monthlyRevenue,
            'courses' => $courses->values(),
            'recent_orders' => $recentOrders->values(),
            'paths' => [
                'studio' => route('studio.dashboard', [], false),
                'admin' => route('admin.dashboard', [], false),
                'courses_create' => route('studio.courses.create', [], false),
            ],
        ]);
    }

    public function togglePublish(Course $course)
    {
        if (! $course->isPublished() && $course->lessons()->count() === 0) {
            return response()->json(['message' => 'Add at least one lesson before publishing.'], 422);
        }

        $course->update(['status' => $course->isPublished() ? 'draft' : 'published']);

        return response()->json([
            'id' => $course->id,
            'slug' => $course->slug,
            'status' => $course->status,
        ]);
    }

    private function formatEnrollment(Enrollment $enrollment): array
    {
        $course = $enrollment->course;
        $certificate = $enrollment->certificate;
        $order = $enrollment->order;

        return [
            'enrollment_id' => $enrollment->id,
            'course_id' => $course->id,
            'course_slug' => $course->slug,
            'course_title' => $course->title,
            'course_subtitle' => $course->subtitle,
            'thumbnail_url' => $course->thumbnail_path ? url(Storage::url($course->thumbnail_path)) : null,
            'progress_percent' => (int) $enrollment->progress_percent,
            'enrolled_at' => $enrollment->enrolled_at?->toIso8601String(),
            'expires_at' => $enrollment->expires_at?->toIso8601String(),
            'is_expired' => (bool) $enrollment->isExpired(),
            'completed_at' => $enrollment->completed_at?->toIso8601String(),
            'last_activity_at' => $enrollment->last_activity_at?->toIso8601String(),
            'learn_path' => route('learn.resume', $course, false),
            'certificate' => $certificate ? [
                'code' => $certificate->code,
                'final_score' => $certificate->final_score,
                'issued_at' => $certificate->issued_at?->toIso8601String(),
                'path' => route('certificates.show', $certificate, false),
                'verify_path' => route('certificates.verify', ['code' => $certificate->code], false),
            ] : null,
            'order' => $order ? [
                'reference' => $order->reference,
                'total' => (float) $order->total,
                'currency' => $order->currency,
                'gateway' => $order->gateway,
                'status' => $order->status,
                'paid_at' => $order->paid_at?->toIso8601String(),
            ] : null,
        ];
    }

    private function formatOrder(Order $order): array
    {
        return [
            'id' => $order->id,
            'reference' => $order->reference,
            'amount' => (float) $order->amount,
            'discount' => (float) $order->discount,
            'total' => (float) $order->total,
            'currency' => $order->currency,
            'gateway' => $order->gateway,
            'status' => $order->status,
            'paid_at' => $order->paid_at?->toIso8601String(),
            'created_at' => $order->created_at?->toIso8601String(),
            'meta' => $order->meta,
            'course' => $order->course ? [
                'id' => $order->course->id,
                'slug' => $order->course->slug,
                'title' => $order->course->title,
            ] : null,
            'user' => $order->user ? [
                'id' => $order->user->id,
                'tpd_user_id' => $order->user->tpd_user_id,
                'name' => $order->user->name,
                'email' => $order->user->email,
            ] : null,
        ];
    }
}
