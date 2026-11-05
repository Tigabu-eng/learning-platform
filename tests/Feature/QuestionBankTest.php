<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\Progress;
use App\Services\QuestionImporter;
use App\Services\QuizEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class QuestionBankTest extends TestCase
{
    use RefreshDatabase;

    private Course $course;
    private Lesson $exam;
    private Quiz $quiz;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');

        $instructor = User::factory()->create(['role' => 'instructor']);
        $this->course = Course::create(['instructor_id' => $instructor->id, 'title' => 'RTA', 'status' => 'published']);
        $chapter = $this->course->chapters()->create(['title' => 'Final', 'position' => 1]);
        $this->exam = $chapter->lessons()->create(['course_id' => $this->course->id, 'title' => 'Final exam', 'type' => 'exam', 'position' => 1]);
        $this->quiz = Quiz::create(['lesson_id' => $this->exam->id, 'is_final' => true, 'shuffle_questions' => true, 'passing_score' => 70]);
    }

    /** @param array<string,int> $topics topic => number of questions */
    private function bank(array $topics): void
    {
        $pos = 0;
        foreach ($topics as $topic => $n) {
            for ($i = 1; $i <= $n; $i++) {
                $q = $this->quiz->questions()->create(['topic' => $topic ?: null, 'type' => 'true_false', 'prompt' => "$topic question $i", 'points' => 1, 'position' => ++$pos]);
                $q->options()->createMany([['text' => 'True', 'is_correct' => true, 'position' => 1], ['text' => 'False', 'is_correct' => false, 'position' => 2]]);
            }
        }
    }

    private function student(): array
    {
        $s = User::factory()->create();
        return [$s, app(Progress::class)->enroll($s, $this->course)];
    }

    private function topicsOf(QuizAttempt $a): array
    {
        $counts = Question::whereIn('id', $a->question_order)->get()->countBy(fn ($q) => (string) $q->topic)->all();
        ksort($counts);
        return $counts;
    }

    // ---- Drawing ----------------------------------------------------------------------------

    public function test_each_attempt_draws_a_balanced_random_set_from_the_bank(): void
    {
        $this->bank(['Module 1' => 10, 'Module 2' => 6, 'Module 3' => 4]);
        $this->quiz->update(['questions_per_attempt' => 10]);

        $sets = [];
        foreach (range(1, 5) as $_) {
            [, $enrollment] = $this->student();
            $attempt = app(QuizEngine::class)->start($this->quiz->fresh(), $enrollment);
            $this->assertCount(10, $attempt->question_order);
            $this->assertCount(10, array_unique($attempt->question_order));
            $this->assertSame(['Module 1' => 5, 'Module 2' => 3, 'Module 3' => 2], $this->topicsOf($attempt));
            $sets[] = collect($attempt->question_order)->sort()->values()->all();
        }
        $this->assertGreaterThan(1, count(array_unique(array_map('json_encode', $sets))), 'students should not all get the same questions');
    }

    public function test_uneven_topics_round_to_exactly_the_draw_size(): void
    {
        $this->bank(['A' => 7, 'B' => 7, 'C' => 7, '' => 2]); // 23 questions, untagged ones included
        $this->quiz->update(['questions_per_attempt' => 10]);
        [, $enrollment] = $this->student();
        $attempt = app(QuizEngine::class)->start($this->quiz->fresh(), $enrollment);
        $this->assertCount(10, $attempt->question_order);
        foreach (['A', 'B', 'C'] as $t) $this->assertContains($this->topicsOf($attempt)[$t], [3, 4]);
    }

    public function test_retakes_get_unseen_questions_first_and_are_scored_on_their_own_set(): void
    {
        $this->bank(['' => 20]);
        $this->quiz->update(['questions_per_attempt' => 10, 'max_attempts' => 3]);
        [, $enrollment] = $this->student();
        $engine = app(QuizEngine::class);

        $first = $engine->start($this->quiz->fresh(), $enrollment);
        $engine->submit($first, []);
        $this->assertSame(10, $first->fresh()->points_total);
        $this->assertSame(0, $first->fresh()->score);

        $second = $engine->start($this->quiz->fresh(), $enrollment);
        $this->assertEmpty(array_intersect($first->question_order, $second->question_order));

        $allTrue = collect($second->question_order)->mapWithKeys(fn ($id) => [$id => (string) Question::find($id)->options->firstWhere('is_correct', true)->id])->all();
        $engine->submit($second, $allTrue);
        $this->assertSame(100, $second->fresh()->score);
        $this->assertTrue($second->fresh()->passed);
    }

    public function test_blank_or_oversized_draw_uses_the_whole_bank(): void
    {
        $this->bank(['' => 6]);
        [, $enrollment] = $this->student();
        $this->assertCount(6, app(QuizEngine::class)->start($this->quiz->fresh(), $enrollment)->question_order);

        $this->quiz->update(['questions_per_attempt' => 50]);
        [, $other] = $this->student();
        $this->assertCount(6, app(QuizEngine::class)->start($this->quiz->fresh(), $other)->question_order);
    }

    public function test_studio_saves_the_draw_size_and_students_see_it(): void
    {
        $this->bank(['Module 1' => 30]);
        $this->actingAs($this->course->instructor)->put(route('studio.lessons.update', $this->exam), [
            'title' => 'Final exam', 'chapter_id' => $this->exam->chapter_id, 'passing_score' => 75, 'questions_per_attempt' => 12,
        ])->assertSessionHasNoErrors();
        $this->assertSame(12, $this->quiz->fresh()->questions_per_attempt);
        $this->get(route('studio.lessons.edit', $this->exam))->assertOk()->assertSee('Question bank')->assertSee('Each attempt draws 12 at random');

        [$student] = $this->student();
        $this->actingAs($student)->get(route('learn.lesson', [$this->course, $this->exam]))->assertOk()->assertSee('12');
    }

    public function test_topics_typed_in_the_studio_are_normalised(): void
    {
        $this->actingAs($this->course->instructor)->post(route('studio.questions.store', $this->exam), [
            'type' => 'true_false', 'topic' => 'module 4: client discovery', 'prompt' => 'x', 'points' => 1, 'tf_answer' => 'true',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Module 4', Question::first()->topic);
    }

    // ---- Parsing ----------------------------------------------------------------------------

    public function test_pasted_text_format(): void
    {
        $items = app(QuestionImporter::class)->parseText(<<<'TXT'
        RTA final exam question bank

        Module 1: Foundations
        1. Which body accredits agencies
        to issue airline tickets?
        A. IATA
        B. CLIA
        C) ASTA
        Answer: A
        Explanation: IATA accreditation.

        2. Select the discovery call topics.
        a) Budget
        b) Dates
        c) Commission
        Answer: a, b
        Points: 2

        Topic: Module 8
        Q3: Host agencies can provide booking tools.
        Answer: True

        4. What does FIT stand for?
        Answer: Flexible independent travel | Free independent traveller

        5. Pick the cruise line association.
        A. IATA
        B. CLIA *
        C. ASTA

        6. Broken question
        A. Only one choice
        Answer: Z
        TXT);

        $this->assertCount(6, $items);
        [$one, $two, $three, $four, $five, $six] = $items;
        $this->assertSame(['Module 1', 'single', "Which body accredits agencies\nto issue airline tickets?"], [$one['topic'], $one['type'], $one['prompt']]);
        $this->assertSame([true, false, false], array_column($one['options'], 'correct'));
        $this->assertSame('ASTA', $one['options'][2]['text']);
        $this->assertSame('IATA accreditation.', $one['explanation']);
        $this->assertSame(['multiple', 2, [true, true, false]], [$two['type'], $two['points'], array_column($two['options'], 'correct')]);
        $this->assertSame(['Module 8', 'true_false', [true, false]], [$three['topic'], $three['type'], array_column($three['options'], 'correct')]);
        $this->assertSame(['short', "Flexible independent travel\nFree independent traveller"], [$four['type'], $four['accepted_answers']]);
        $this->assertSame(['CLIA', true], [$five['options'][1]['text'], $five['options'][1]['correct']]);
        foreach ([$one, $two, $three, $four, $five] as $ok) $this->assertSame([], $ok['errors']);
        $this->assertCount(2, $six['errors']); // one choice, unknown answer letter
    }

    public function test_answer_given_as_letter_and_text_or_text_only(): void
    {
        $items = app(QuestionImporter::class)->parseText("1. Q?\nA. Host agency\nB. Supplier\nAnswer: B. Supplier\n\n2. Q2?\nA. Host agency\nB. Supplier\nAnswer: host agency");
        $this->assertSame([false, true], array_column($items[0]['options'], 'correct'));
        $this->assertSame([true, false], array_column($items[1]['options'], 'correct'));
    }

    public function test_the_downloadable_template_parses_cleanly(): void
    {
        $csv = $this->actingAs($this->course->instructor)->get(route('studio.questions.template', $this->exam))
            ->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8')->getContent();
        $path = tempnam(sys_get_temp_dir(), 'tpl');
        file_put_contents($path, $csv);
        $importer = app(QuestionImporter::class);
        $items = $importer->parseRows($importer->readCsv($path));
        unlink($path);

        $this->assertCount(4, $items);
        $this->assertSame(['single', 'multiple', 'true_false', 'short'], array_column($items, 'type'));
        $this->assertSame(['Module 1', 'Module 4', 'Module 8', 'Module 12'], array_column($items, 'topic'));
        $this->assertSame([[], [], [], []], array_column($items, 'errors'));
    }

    public function test_excel_upload_preview_then_import(): void
    {
        $file = $this->xlsx([
            ['Module', 'Question', 'A', 'B', 'C', 'D', 'Answer', 'Points'],
            ['1', 'Which body accredits agencies?', 'IATA', 'CLIA', 'ASTA', 'UNWTO', 'A', '1'],
            ['2', 'Pick the cruise association', 'IATA', 'CLIA', '', '', 'B', ''],
            ['2', 'Is TARC for travel advisors?', '', '', '', '', 'TRUE', ''],
            ['3', 'Missing its answer', 'Yes', 'No', '', '', '', ''],
        ]);

        $this->actingAs($this->course->instructor)
            ->post(route('studio.questions.import.preview', $this->exam), ['file' => $file])
            ->assertOk()
            ->assertSee('3 questions')->assertSee('ready to import', false)
            ->assertSee('Mark the correct answer');
        $token = $this->tokenFromStorage();

        $this->post(route('studio.questions.import.store', $this->exam), ['token' => $token])
            ->assertRedirect(route('studio.lessons.edit', $this->exam))
            ->assertSessionHas('status', 'Imported 3 questions. Skipped 1 (duplicates or rows with problems).');

        $questions = $this->quiz->questions()->with('options')->get();
        $this->assertSame(['Module 1', 'Module 2', 'Module 2'], $questions->pluck('topic')->all());
        $this->assertSame(['single', 'single', 'true_false'], $questions->pluck('type')->all());
        $this->assertSame('CLIA', $questions[1]->options->firstWhere('is_correct', true)->text);
        $this->assertSame([], Storage::disk('local')->files('imports'), 'preview file is removed after import');

        // Same file again: everything is already there.
        $this->post(route('studio.questions.import.preview', $this->exam), ['file' => $this->xlsx([
            ['Module', 'Question', 'A', 'B', 'Answer'], ['1', 'Which body accredits agencies?', 'IATA', 'CLIA', 'A'],
        ])])->assertOk()->assertSee('Already added');
        $this->post(route('studio.questions.import.store', $this->exam), ['token' => $this->tokenFromStorage()])
            ->assertSessionHas('status', 'Imported 0 questions. Skipped 1 (duplicates or rows with problems).');
        $this->assertSame(3, $this->quiz->questions()->count());
    }

    public function test_word_upload_with_automatic_numbering(): void
    {
        $file = $this->docx([
            ['Module 4: Client discovery', null],
            ['What should a discovery call cover first?', ['num' => 1, 'lvl' => 0]],
            ['The client\'s goals', ['num' => 2, 'lvl' => 0]],
            ['Your fees', ['num' => 2, 'lvl' => 0]],
            ['Answer: A', null],
            ['Host agencies help independent advisors.', ['num' => 1, 'lvl' => 0]],
            ['Answer: True', null],
        ]);
        $items = app(QuestionImporter::class)->parseFile($file);

        $this->assertCount(2, $items);
        $this->assertSame(['Module 4', 'What should a discovery call cover first?', ['The client\'s goals', 'Your fees'], [true, false]],
            [$items[0]['topic'], $items[0]['prompt'], array_column($items[0]['options'], 'text'), array_column($items[0]['options'], 'correct')]);
        $this->assertSame(['true_false', []], [$items[1]['type'], $items[1]['errors']]);
    }

    public function test_pasted_text_import_and_default_topic(): void
    {
        $this->actingAs($this->course->instructor)->post(route('studio.questions.import.preview', $this->exam), [
            'text' => "1. First?\nAnswer: True\n\n2. Second?\nAnswer: False", 'default_topic' => '7',
        ])->assertOk()->assertSee('Module 7');
        $this->post(route('studio.questions.import.store', $this->exam), ['token' => $this->tokenFromStorage()]);
        $this->assertSame(['Module 7', 'Module 7'], $this->quiz->questions()->pluck('topic')->all());
    }

    public function test_import_needs_input_and_rejects_unknown_files(): void
    {
        $this->actingAs($this->course->instructor)->post(route('studio.questions.import.preview', $this->exam), [])->assertSessionHasErrors('file');
        $this->post(route('studio.questions.import.preview', $this->exam), ['file' => UploadedFile::fake()->createWithContent('bank.pdf', '%PDF')])
            ->assertSessionHasErrors(['file' => 'Upload an .xlsx, .csv, .docx or .txt file.']);
        $this->post(route('studio.questions.import.preview', $this->exam), ['file' => UploadedFile::fake()->createWithContent('bank.xlsx', 'not a zip')])
            ->assertSessionHasErrors('file');
        $this->post(route('studio.questions.import.store', $this->exam), ['token' => str_repeat('a', 32)])->assertSessionHasErrors('file');
    }

    public function test_only_the_course_team_can_import(): void
    {
        $other = User::factory()->create(['role' => 'instructor']);
        $this->actingAs($other)->post(route('studio.questions.import.preview', $this->exam), ['text' => "1. Q?\nAnswer: True"])->assertForbidden();
        $this->get(route('studio.questions.template', $this->exam))->assertForbidden();

        // A preview made by the instructor can't be confirmed by someone else.
        $this->actingAs($this->course->instructor)->post(route('studio.questions.import.preview', $this->exam), ['text' => "1. Q?\nAnswer: True"]);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post(route('studio.questions.import.store', $this->exam), ['token' => $this->tokenFromStorage()])->assertSessionHasErrors('file');
        $this->assertSame(0, $this->quiz->questions()->count());
    }

    // ---- Helpers ----------------------------------------------------------------------------

    private function tokenFromStorage(): string
    {
        $files = Storage::disk('local')->files('imports');
        $this->assertCount(1, $files);
        preg_match('/-([A-Za-z0-9]{32})\.json$/', $files[0], $m);
        return $m[1];
    }

    /** A minimal .xlsx with shared strings, like Excel writes. */
    private function xlsx(array $rows): UploadedFile
    {
        $strings = []; $sheetRows = '';
        foreach ($rows as $r => $row) {
            $cells = '';
            foreach ($row as $c => $value) {
                if ($value === '') continue;
                $ref = chr(65 + $c).($r + 1);
                if (ctype_digit($value)) { $cells .= "<c r=\"$ref\"><v>$value</v></c>"; continue; }
                if ($value === 'TRUE') { $cells .= "<c r=\"$ref\" t=\"b\"><v>1</v></c>"; continue; }
                $strings[] = htmlspecialchars($value, ENT_XML1);
                $cells .= "<c r=\"$ref\" t=\"s\"><v>".(count($strings) - 1).'</v></c>';
            }
            $sheetRows .= '<row r="'.($r + 1).'">'.$cells.'</row>';
        }
        $ns = 'xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"';
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('xl/sharedStrings.xml', "<sst $ns>".implode('', array_map(fn ($s) => "<si><t>$s</t></si>", $strings)).'</sst>');
        $zip->addFromString('xl/worksheets/sheet1.xml', "<worksheet $ns><sheetData>$sheetRows</sheetData></worksheet>");
        $zip->close();
        return new UploadedFile($path, 'bank.xlsx', null, null, true);
    }

    /** A minimal .docx. List 1 is decimal (questions), list 2 is upper-letter (choices). */
    private function docx(array $paragraphs): UploadedFile
    {
        $w = 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"';
        $body = '';
        foreach ($paragraphs as [$text, $num]) {
            $ppr = $num ? "<w:pPr><w:numPr><w:ilvl w:val=\"{$num['lvl']}\"/><w:numId w:val=\"{$num['num']}\"/></w:numPr></w:pPr>" : '';
            $body .= "<w:p>$ppr<w:r><w:t>".htmlspecialchars($text, ENT_XML1).'</w:t></w:r></w:p>';
        }
        $numbering = "<w:numbering $w>"
            .'<w:abstractNum w:abstractNumId="10"><w:lvl w:ilvl="0"><w:numFmt w:val="decimal"/></w:lvl></w:abstractNum>'
            .'<w:abstractNum w:abstractNumId="20"><w:lvl w:ilvl="0"><w:numFmt w:val="upperLetter"/></w:lvl></w:abstractNum>'
            .'<w:num w:numId="1"><w:abstractNumId w:val="10"/></w:num><w:num w:numId="2"><w:abstractNumId w:val="20"/></w:num>'
            .'</w:numbering>';
        $path = tempnam(sys_get_temp_dir(), 'docx');
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('word/document.xml', "<w:document $w><w:body>$body</w:body></w:document>");
        $zip->addFromString('word/numbering.xml', $numbering);
        $zip->close();
        return new UploadedFile($path, 'bank.docx', null, null, true);
    }
}
