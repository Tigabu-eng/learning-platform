<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class StudentController extends Controller
{
    public function dashboard(Request $request)
    {
        $user = $request->user();
        $enrollments = $user->enrollments()->with(['course.instructor', 'certificate', 'lastLesson'])->latest('last_activity_at')->latest('enrolled_at')->get();
        $certificates = $user->certificates()->with('course')->latest('issued_at')->get();
        return view('student.dashboard', compact('enrollments', 'certificates'));
    }

    public function orders(Request $request)
    {
        $orders = $request->user()->orders()->with('course')->latest()->get();
        return view('student.orders', compact('orders'));
    }
}
