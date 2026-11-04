<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CourseController extends Controller
{
    public function index(Request $request)
    {
        $courses = Course::whereIn('id', StudioController::courseIds($request))
            ->withCount(['lessons', 'enrollments'])->with('instructor')->latest()->get();
        return view('studio.courses.index', compact('courses'));
    }

    public function create() { return view('studio.courses.create'); }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:160',
            'subtitle' => 'nullable|string|max:240',
            'price' => 'nullable|numeric|min:0',
        ]);
        $course = Course::create($data + ['instructor_id' => $request->user()->id, 'price' => $data['price'] ?? 0]);
        $course->chapters()->create(['title' => 'Getting started', 'position' => 1]);
        return redirect()->route('studio.curriculum', $course)->with('status', 'Course created. Add your first lessons.');
    }

    public function edit(Request $request, Course $course)
    {
        $this->authorizeCourse($request, $course);
        return view('studio.courses.edit', compact('course'));
    }

    public function update(Request $request, Course $course)
    {
        $this->authorizeCourse($request, $course);
        $data = $request->validate([
            'title' => 'required|string|max:160',
            'slug' => 'required|alpha_dash|max:160|unique:courses,slug,'.$course->id,
            'subtitle' => 'nullable|string|max:240',
            'description' => 'nullable|string',
            'level' => 'required|in:Beginner,Intermediate,Advanced,All levels',
            'price' => 'required|numeric|min:0',
            'access_days' => 'nullable|integer|min:1|max:3650',
            'thumbnail' => 'nullable|image|max:4096',
        ]);
        foreach (['sequential', 'drip_enabled', 'certificate_enabled'] as $flag) $data[$flag] = $request->boolean($flag);

        if ($request->hasFile('thumbnail')) {
            if ($course->thumbnail_path) Storage::disk('public')->delete($course->thumbnail_path);
            $data['thumbnail_path'] = $request->file('thumbnail')->store('thumbnails', 'public');
        }
        unset($data['thumbnail']);
        $course->update($data);
        return redirect()->route('studio.courses.edit', $course)->with('status', 'Settings saved.');
    }

    public function togglePublish(Request $request, Course $course)
    {
        $this->authorizeCourse($request, $course);
        if (! $course->isPublished() && $course->lessons()->count() === 0) {
            return back()->withErrors(['publish' => 'Add at least one lesson before publishing.']);
        }
        $course->update(['status' => $course->isPublished() ? 'draft' : 'published']);
        return back()->with('status', $course->isPublished() ? 'Course published.' : 'Course moved to draft.');
    }

    public function destroy(Request $request, Course $course)
    {
        $this->authorizeCourse($request, $course);
        if ($course->enrollments()->exists()) {
            return back()->withErrors(['delete' => 'This course has students. Move it to draft instead of deleting it.']);
        }
        $course->delete();
        return redirect()->route('studio.courses.index')->with('status', 'Course deleted.');
    }

    public static function authorizeCourse(Request $request, Course $course): void
    {
        abort_unless($course->canBeManagedBy($request->user()), 403);
    }
}
