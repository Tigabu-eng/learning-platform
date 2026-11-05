<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Controller;
use App\Models\Chapter;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Quiz;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CurriculumController extends Controller
{
    public function show(Request $request, Course $course)
    {
        CourseController::authorizeCourse($request, $course);
        $course->load('chapters.lessons.quiz');
        return view('studio.curriculum', compact('course'));
    }

    public function storeChapter(Request $request, Course $course)
    {
        CourseController::authorizeCourse($request, $course);
        $data = $request->validate(['title' => 'required|string|max:160']);
        $course->chapters()->create($data + ['position' => ($course->chapters()->max('position') ?? 0) + 1]);
        return back()->with('status', 'Chapter added.');
    }

    public function updateChapter(Request $request, Chapter $chapter)
    {
        CourseController::authorizeCourse($request, $chapter->course);
        $chapter->update($request->validate(['title' => 'required|string|max:160']));
        return back()->with('status', 'Chapter renamed.');
    }

    public function destroyChapter(Request $request, Chapter $chapter)
    {
        CourseController::authorizeCourse($request, $chapter->course);
        $chapter->delete();
        return back()->with('status', 'Chapter deleted.');
    }

    public function storeLesson(Request $request, Chapter $chapter)
    {
        $course = $chapter->course;
        CourseController::authorizeCourse($request, $course);
        $data = $request->validate([
            'title' => 'required|string|max:160',
            'type' => ['required', Rule::in(array_keys(Lesson::TYPES))],
        ]);
        if ($data['type'] === 'exam' && $course->finalExam()) {
            return back()->withErrors(['type' => 'This course already has a final exam.']);
        }
        $lesson = $chapter->lessons()->create($data + [
            'course_id' => $course->id,
            'position' => ($chapter->lessons()->max('position') ?? 0) + 1,
        ]);
        if ($lesson->isAssessment()) {
            Quiz::create([
                'lesson_id' => $lesson->id,
                'is_final' => $lesson->type === 'exam',
                'passing_score' => $lesson->type === 'exam' ? 75 : 70,
                'max_attempts' => $lesson->type === 'exam' ? 3 : null,
                'time_limit_minutes' => $lesson->type === 'exam' ? 30 : null,
                'require_all_lessons' => $lesson->type === 'exam',
                'shuffle_questions' => $lesson->type === 'exam',
            ]);
        }
        return redirect()->route('studio.lessons.edit', $lesson);
    }

    public function editLesson(Request $request, Lesson $lesson)
    {
        CourseController::authorizeCourse($request, $lesson->course);
        $lesson->load(['course.chapters', 'quiz.questions.options']);
        return view('studio.lesson', ['lesson' => $lesson, 'course' => $lesson->course]);
    }

    public function updateLesson(Request $request, Lesson $lesson)
    {
        CourseController::authorizeCourse($request, $lesson->course);
        $data = $request->validate([
            'title' => 'required|string|max:160',
            'chapter_id' => ['required', Rule::exists('chapters', 'id')->where('course_id', $lesson->course_id)],
            'content' => 'nullable|string',
            'video_url' => 'nullable|url|max:500',
            'video' => 'nullable|file|mimetypes:video/mp4,video/webm,video/quicktime|max:512000',
            'attachment' => 'nullable|file|max:102400',
            'duration_minutes' => 'nullable|integer|min:0|max:1000',
            'drip_days' => 'nullable|integer|min:0|max:3650',
            // quiz settings
            'passing_score' => 'nullable|integer|min:0|max:100',
            'max_attempts' => 'nullable|integer|min:1|max:100',
            'time_limit_minutes' => 'nullable|integer|min:1|max:600',
            'questions_per_attempt' => 'nullable|integer|min:1|max:1000',
        ]);
        $data['is_preview'] = $request->boolean('is_preview');

        if ($request->hasFile('video')) {
            if ($lesson->video_path) Storage::disk('public')->delete($lesson->video_path);
            $data['video_path'] = $request->file('video')->store('videos', 'public');
        }
        if ($request->boolean('remove_video') && $lesson->video_path) {
            Storage::disk('public')->delete($lesson->video_path);
            $data['video_path'] = null;
        }
        if ($request->hasFile('attachment')) {
            if ($lesson->file_path) Storage::disk('local')->delete($lesson->file_path);
            $data['file_path'] = $request->file('attachment')->store('attachments', 'local');
            $data['file_name'] = $request->file('attachment')->getClientOriginalName();
        }

        $quizFields = ['passing_score', 'max_attempts', 'time_limit_minutes', 'questions_per_attempt'];
        $quizData = array_intersect_key($data, array_flip($quizFields));
        $lessonData = array_diff_key($data, array_flip(array_merge($quizFields, ['video', 'attachment'])));
        if ($lessonData['chapter_id'] != $lesson->chapter_id) {
            $lessonData['position'] = (Lesson::where('chapter_id', $lessonData['chapter_id'])->max('position') ?? 0) + 1;
        }
        $lesson->update($lessonData);

        if ($lesson->quiz) {
            foreach (['shuffle_questions', 'shuffle_options', 'show_answers', 'require_all_lessons'] as $f) $quizData[$f] = $request->boolean($f);
            $quizData['passing_score'] ??= 70;
            $lesson->quiz->update($quizData);
        }

        return back()->with('status', 'Lesson saved.');
    }

    public function destroyLesson(Request $request, Lesson $lesson)
    {
        $course = $lesson->course;
        CourseController::authorizeCourse($request, $course);
        $lesson->delete();
        return redirect()->route('studio.curriculum', $course)->with('status', 'Lesson deleted.');
    }

    /** Payload: {chapters: [{id, lessons: [id, ...]}, ...]} in display order. */
    public function reorder(Request $request, Course $course)
    {
        CourseController::authorizeCourse($request, $course);
        $payload = $request->validate(['chapters' => 'required|array', 'chapters.*.id' => 'required|integer', 'chapters.*.lessons' => 'array']);
        $chapterIds = $course->chapters()->pluck('id')->map(fn ($i) => (int) $i)->all();
        $lessonIds = $course->lessons()->pluck('id')->map(fn ($i) => (int) $i)->all();

        DB::transaction(function () use ($payload, $chapterIds, $lessonIds) {
            foreach (array_values($payload['chapters']) as $ci => $ch) {
                if (! in_array((int) $ch['id'], $chapterIds, true)) continue;
                Chapter::whereKey($ch['id'])->update(['position' => $ci + 1]);
                foreach (array_values($ch['lessons'] ?? []) as $li => $lid) {
                    if (! in_array((int) $lid, $lessonIds, true)) continue;
                    Lesson::whereKey($lid)->update(['chapter_id' => $ch['id'], 'position' => $li + 1]);
                }
            }
        });
        return response()->json(['saved' => true]);
    }
}
