<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CouponController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $coupons = Coupon::with('course')->when(! $user->isAdmin(), fn ($q) => $q->where('created_by', $user->id))->latest()->get();
        $courses = Course::whereIn('id', StudioController::courseIds($request))->orderBy('title')->get();
        return view('studio.coupons', compact('coupons', 'courses'));
    }

    public function store(Request $request)
    {
        $ids = StudioController::courseIds($request)->all();
        $data = $request->validate([
            'code' => 'required|alpha_dash|max:40|unique:coupons,code',
            'course_id' => ['nullable', Rule::in($ids)],
            'type' => 'required|in:percent,fixed',
            'value' => ['required', 'numeric', 'min:0.01', $request->type === 'percent' ? 'max:100' : 'max:100000'],
            'max_uses' => 'nullable|integer|min:1',
            'expires_at' => 'nullable|date|after_or_equal:today',
        ]);
        $data['code'] = strtoupper($data['code']);
        Coupon::create($data + ['created_by' => $request->user()->id]);
        return back()->with('status', 'Coupon '.$data['code'].' created.');
    }

    public function toggle(Request $request, Coupon $coupon)
    {
        $this->own($request, $coupon);
        $coupon->update(['active' => ! $coupon->active]);
        return back()->with('status', $coupon->active ? 'Coupon turned on.' : 'Coupon turned off.');
    }

    public function destroy(Request $request, Coupon $coupon)
    {
        $this->own($request, $coupon);
        $coupon->delete();
        return back()->with('status', 'Coupon deleted.');
    }

    private function own(Request $request, Coupon $coupon): void
    {
        abort_unless($request->user()->isAdmin() || $coupon->created_by === $request->user()->id, 403);
    }
}
