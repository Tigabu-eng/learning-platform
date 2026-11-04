<?php

namespace App\Http\Controllers;

use App\Models\Course;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function index(Request $request)
    {
        $courses = Course::where('status', 'published')
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w->where('title', 'like', "%$s%")->orWhere('subtitle', 'like', "%$s%")))
            ->with('instructor')->withCount(['lessons', 'enrollments'])->latest()->get();

        return view('catalog.index', compact('courses'));
    }

    public function show(Request $request, Course $course)
    {
        abort_unless($course->isPublished() || $course->canBeManagedBy($request->user()), 404);
        $course->load(['chapters.lessons', 'instructor']);
        $enrollment = $request->user()?->enrollmentFor($course);
        return view('catalog.show', compact('course', 'enrollment'));
    }
}
