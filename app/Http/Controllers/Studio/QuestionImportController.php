<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\Question;
use App\Services\QuestionImporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** Upload or paste a question bank, check the preview, then add it to the quiz. */
class QuestionImportController extends Controller
{
    public function __construct(private QuestionImporter $importer) {}

    public function preview(Request $request, Lesson $lesson)
    {
        CourseController::authorizeCourse($request, $lesson->course);
        abort_unless($lesson->quiz, 404);
        $request->validate([
            'file' => 'nullable|file|max:20480|required_without:text',
            'text' => 'nullable|string|max:2000000|required_without:file',
            'default_topic' => 'nullable|string|max:120',
        ], [
            'file.required_without' => 'Choose a file or paste your questions.',
            'text.required_without' => 'Choose a file or paste your questions.',
        ]);

        try {
            $items = $request->hasFile('file')
                ? $this->importer->parseFile($request->file('file'))
                : $this->importer->parseText((string) $request->input('text'));
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        } catch (\Throwable $e) {
            report($e);
            throw ValidationException::withMessages(['file' => "That file couldn't be read. Check it opens in Excel or Word, or paste the questions instead."]);
        }
        if (! $items) {
            throw ValidationException::withMessages(['file' => 'No questions found. Check the format guide below the upload box.']);
        }

        $default = QuestionImporter::normalizeTopic($request->input('default_topic'));
        $existing = $this->importer->existingPrompts($lesson->quiz);
        $seen = [];
        foreach ($items as &$item) {
            if ($item['topic'] === '' && $default !== '') $item['topic'] = $default;
            $key = Question::normalize($item['prompt']);
            $item['duplicate'] = ! $item['errors'] && (isset($existing[$key]) || isset($seen[$key]));
            $seen[$key] = true;
        }
        unset($item);

        $token = Str::random(32);
        Storage::disk('local')->put($this->path($request, $lesson, $token), json_encode($items));

        return view('studio.question-import', [
            'lesson' => $lesson, 'course' => $lesson->course, 'items' => $items, 'token' => $token,
            'source' => $request->hasFile('file') ? $request->file('file')->getClientOriginalName() : 'Pasted text',
        ]);
    }

    public function store(Request $request, Lesson $lesson)
    {
        CourseController::authorizeCourse($request, $lesson->course);
        abort_unless($lesson->quiz, 404);
        $token = (string) $request->validate(['token' => 'required|alpha_num|size:32'])['token'];
        $path = $this->path($request, $lesson, $token);
        $json = Storage::disk('local')->get($path);
        if ($json === null) {
            return redirect()->to(route('studio.lessons.edit', $lesson).'#import')->withErrors(['file' => 'That preview has expired. Upload the file again.']);
        }
        Storage::disk('local')->delete($path);

        [$added, $skipped] = $this->importer->import($lesson->quiz, json_decode($json, true));
        $message = $added === 1 ? 'Imported 1 question.' : "Imported $added questions.";
        if ($skipped) $message .= " Skipped $skipped (duplicates or rows with problems).";
        return redirect()->route('studio.lessons.edit', $lesson)->with('status', $message);
    }

    public function template(Request $request, Lesson $lesson)
    {
        CourseController::authorizeCourse($request, $lesson->course);
        $rows = [
            QuestionImporter::TEMPLATE_HEADERS,
            ['Module 1', 'single', 'Which body accredits travel agencies to sell airline tickets worldwide?', 'IATA', 'CLIA', 'ASTA', 'UNWTO', '', 'A', '1', 'IATA accreditation lets agencies issue airline tickets.'],
            ['Module 4', 'multiple', 'Which of these belong in a client discovery call? Select all that apply.', 'Budget', 'Travel dates', "The advisor's commission rate", 'Who is travelling', '', 'A, B, D', '1', ''],
            ['Module 8', 'true_false', 'A host agency can provide booking tools and supplier relationships to independent advisors.', '', '', '', '', '', 'True', '1', ''],
            ['Module 12', 'short', 'What does the abbreviation FIT stand for in travel?', '', '', '', '', '', 'Flexible independent travel | Free independent traveller', '1', 'Both forms are in common use.'],
        ];
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF"); // so Excel reads it as UTF-8
        foreach ($rows as $r) fputcsv($out, $r, ',', '"', '');
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="question-bank-template.csv"',
        ]);
    }

    private function path(Request $request, Lesson $lesson, string $token): string
    {
        return "imports/lesson-{$lesson->id}-user-{$request->user()->id}-{$token}.json";
    }
}
