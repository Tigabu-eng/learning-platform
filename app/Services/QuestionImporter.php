<?php

namespace App\Services;

use App\Models\Question;
use App\Models\Quiz;
use App\Support\RichText;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use ZipArchive;

/**
 * Turns a question bank file (.xlsx, .csv, .docx, .txt) or pasted text into questions.
 *
 * Each parsed item: ['source', 'type', 'topic', 'prompt', 'options' => [['text', 'correct']],
 * 'accepted_answers', 'points', 'explanation', 'errors' => [...]].
 * The formats are described in the Studio import panel and in the downloadable template.
 */
class QuestionImporter
{
    public const MAX_OPTIONS = 8;

    public const TEMPLATE_HEADERS = ['Topic', 'Type', 'Question', 'Option A', 'Option B', 'Option C', 'Option D', 'Option E', 'Correct', 'Points', 'Explanation'];

    public function parseFile(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        return match (strtolower($file->getClientOriginalExtension())) {
            'xlsx' => $this->parseRows($this->readXlsx($path)),
            'csv' => $this->parseRows($this->readCsv($path)),
            'docx' => $this->parseText($this->readDocx($path)),
            'txt', 'md' => $this->parseText((string) file_get_contents($path)),
            default => throw new RuntimeException('Upload an .xlsx, .csv, .docx or .txt file.'),
        };
    }

    // ---- Spreadsheets -------------------------------------------------------------------

    /** First row is the header. Column names are matched loosely ("Option A", "A", "Choice A"...). */
    public function parseRows(array $rows): array
    {
        $rows = array_values(array_filter($rows, fn ($r) => implode('', array_map('trim', $r)) !== ''));
        if (count($rows) < 2) return [];

        $cols = [];
        foreach ($rows[0] as $i => $name) {
            $key = $this->columnKey((string) $name);
            if ($key !== null && ! isset($cols[$key])) $cols[$key] = $i;
        }
        if (! isset($cols['prompt'])) {
            throw new RuntimeException('The first row needs column names, including a "Question" column. Download the template to see the layout.');
        }

        $items = [];
        foreach (array_slice($rows, 1) as $n => $row) {
            $get = fn ($key) => isset($cols[$key]) ? trim((string) ($row[$cols[$key]] ?? '')) : '';
            $options = [];
            foreach (range('a', 'h') as $letter) {
                if (($text = $get('opt_'.$letter)) !== '') $options[$letter] = $text;
            }
            $items[] = $this->build([
                'source' => 'Row '.($n + 2),
                'topic' => $get('topic'),
                'type' => $get('type'),
                'prompt' => $get('prompt'),
                'options' => $options,
                'answer' => $get('correct'),
                'points' => $get('points'),
                'explanation' => $get('explanation'),
            ]);
        }
        return $items;
    }

    private function columnKey(string $name): ?string
    {
        $n = trim(preg_replace('/[^a-z0-9]+/', ' ', strtolower($name)));
        if (preg_match('/^(?:option|choice|answer option)?\s*([a-h])$/', $n, $m)) return 'opt_'.$m[1];
        return match ($n) {
            'topic', 'module', 'section', 'category', 'chapter' => 'topic',
            'type', 'question type' => 'type',
            'question', 'prompt', 'question text' => 'prompt',
            'correct', 'answer', 'correct answer', 'correct answers', 'answers', 'key', 'answer key' => 'correct',
            'points', 'point', 'marks', 'score' => 'points',
            'explanation', 'feedback', 'rationale' => 'explanation',
            default => null,
        };
    }

    public function readCsv(string $path): array
    {
        $content = (string) file_get_contents($path);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content); // Excel's UTF-8 marker
        if (! mb_check_encoding($content, 'UTF-8')) $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        $first = strtok($content, "\n");
        $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';

        $h = fopen('php://temp', 'r+');
        fwrite($h, $content);
        rewind($h);
        $rows = [];
        while (($r = fgetcsv($h, null, $delimiter, '"', '')) !== false) $rows[] = $r;
        fclose($h);
        return $rows;
    }

    /** Reads the first worksheet of an .xlsx file. */
    public function readXlsx(string $path): array
    {
        $zip = $this->openZip($path, 'xlsx');
        $shared = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $doc = $this->xml($xml);
            foreach ($doc->si as $si) {
                $shared[] = isset($si->t) ? (string) $si->t : implode('', array_map('strval', $si->xpath('.//*[local-name()="t"]')));
            }
        }
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheet === false) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                if (preg_match('#^xl/worksheets/[^/]+\.xml$#', $zip->getNameIndex($i))) { $sheet = $zip->getFromIndex($i); break; }
            }
        }
        $zip->close();
        if ($sheet === false) throw new RuntimeException('That spreadsheet has no worksheet.');

        $rows = [];
        foreach ($this->xml($sheet)->sheetData->row as $row) {
            $cells = []; $next = 0;
            foreach ($row->c as $c) {
                $col = isset($c['r']) ? $this->columnIndex(preg_replace('/\d+/', '', (string) $c['r'])) : $next;
                $next = $col + 1;
                $type = (string) $c['t'];
                $value = match ($type) {
                    's' => $shared[(int) $c->v] ?? '',
                    'inlineStr' => implode('', array_map('strval', $c->xpath('.//*[local-name()="t"]'))),
                    'b' => ((string) $c->v) === '1' ? 'TRUE' : 'FALSE',
                    default => (string) $c->v,
                };
                $cells[$col] = $value;
            }
            if ($cells) {
                $filled = array_fill(0, max(array_keys($cells)) + 1, '');
                $rows[] = array_replace($filled, $cells);
            }
        }
        return $rows;
    }

    private function columnIndex(string $letters): int
    {
        $n = 0;
        foreach (str_split(strtoupper($letters)) as $ch) $n = $n * 26 + (ord($ch) - 64);
        return max(0, $n - 1);
    }

    // ---- Word documents and plain text ---------------------------------------------------

    /**
     * Paragraph text from a .docx. Word's automatic numbering isn't part of the text, so it is
     * put back: a lettered list item becomes "A.", "B." (answer choices) and a numbered one
     * becomes "1." (a question). For bullets or unknown formats the list level decides:
     * top level is a question, deeper levels are choices.
     */
    public function readDocx(string $path): string
    {
        $zip = $this->openZip($path, 'docx');
        $xml = $zip->getFromName('word/document.xml');
        $numberingXml = $zip->getFromName('word/numbering.xml');
        $zip->close();
        $formats = $numberingXml !== false ? $this->listFormats($numberingXml) : [];
        if ($xml === false) throw new RuntimeException('That Word file has no document text.');

        $doc = new \DOMDocument();
        $doc->loadXML($xml, LIBXML_NONET);
        $xp = new \DOMXPath($doc);
        $xp->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $lines = []; $letter = 0;
        foreach ($xp->query('//w:body//w:p') as $p) {
            $text = '';
            foreach ($xp->query('.//w:t|.//w:tab|.//w:br', $p) as $node) {
                $text .= match ($node->localName) { 't' => $node->textContent, 'tab' => ' ', default => "\n" };
            }
            $text = trim($text);
            if ($text === '') { $lines[] = ''; continue; }

            $numbered = $xp->query('./w:pPr/w:numPr', $p)->length > 0;
            if ($numbered && ! $this->isMarked($text)) {
                $level = (int) $xp->query('./w:pPr/w:numPr/w:ilvl/@w:val', $p)->item(0)?->nodeValue;
                $numId = (string) $xp->query('./w:pPr/w:numPr/w:numId/@w:val', $p)->item(0)?->nodeValue;
                $format = $formats[$numId][$level] ?? '';
                $isChoice = str_contains(strtolower($format), 'letter') || (! in_array($format, ['decimal', 'decimalZero'], true) && $level > 0);
                if ($isChoice) { $text = chr(65 + min($letter++, 7)).'. '.$text; }
                else { $text = '1. '.$text; $letter = 0; }
            } else {
                $letter = 0; // choices are consecutive; anything else starts a new set
            }
            $lines[] = $text;
        }
        return implode("\n", $lines);
    }

    /** [numId => [level => numFmt]] from word/numbering.xml, e.g. "decimal", "upperLetter", "bullet". */
    private function listFormats(string $xml): array
    {
        $doc = new \DOMDocument();
        if (! @$doc->loadXML($xml, LIBXML_NONET)) return [];
        $xp = new \DOMXPath($doc);
        $xp->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $abstract = [];
        foreach ($xp->query('//w:abstractNum') as $a) {
            foreach ($xp->query('./w:lvl', $a) as $lvl) {
                $abstract[$a->getAttribute('w:abstractNumId')][(int) $lvl->getAttribute('w:ilvl')] = (string) $xp->query('./w:numFmt/@w:val', $lvl)->item(0)?->nodeValue;
            }
        }
        $formats = [];
        foreach ($xp->query('//w:num') as $num) {
            $absId = (string) $xp->query('./w:abstractNumId/@w:val', $num)->item(0)?->nodeValue;
            $formats[$num->getAttribute('w:numId')] = $abstract[$absId] ?? [];
            foreach ($xp->query('./w:lvlOverride/w:lvl', $num) as $lvl) {
                $formats[$num->getAttribute('w:numId')][(int) $lvl->getAttribute('w:ilvl')] = (string) $xp->query('./w:numFmt/@w:val', $lvl)->item(0)?->nodeValue;
            }
        }
        return $formats;
    }

    /** True when the text already starts with its own number/letter or is a labelled line. */
    private function isMarked(string $text): bool
    {
        return (bool) preg_match('/^(?:(?:q(?:uestion)?\s*)?\d+\s*[.):]|\(?[a-h][.)]\s|(?:answer|correct answer|ans|explanation|points?|topic|module)\b)/i', $text);
    }

    /**
     * Plain-text format:
     *
     *   Topic: Module 4            (applies to the questions below it; "Module 4: ..." on its own line also works)
     *   1. Question text?
     *   A. Choice
     *   B. Choice
     *   Answer: B                  (several: "A, C"; true/false: "True"; short answer: "IATA | International Air Transport Association")
     *   Explanation: ...           (optional)
     *   Points: 2                  (optional)
     *
     * A choice ending with "*" is also taken as correct.
     */
    public function parseText(string $text): array
    {
        $text = str_replace(["\r\n", "\r", "\u{00A0}"], ["\n", "\n", ' '], $text);
        $items = []; $topic = ''; $current = null; $last = null; $lineNo = 0;

        $flush = function () use (&$current, &$items) {
            if ($current) $items[] = $this->build($current);
            $current = null;
        };

        foreach (explode("\n", $text) as $raw) {
            $lineNo++;
            $line = trim($raw);
            if ($line === '') continue;

            if (preg_match('/^(?:topic|module|section)\s*[:\-–]\s*(.+)$/iu', $line, $m) && preg_match('/^topic|^section|^module\s*:/i', $line)) {
                $flush();
                $topic = preg_match('/^module/i', $line) ? 'Module '.trim($m[1]) : trim($m[1]);
                continue;
            }
            if (preg_match('/^module\s+\d+\b/i', $line) && ! ($current && ! $current['options'] && $current['answer'] === '')) {
                $flush();
                $topic = $line;
                continue;
            }
            if (preg_match('/^(?:q(?:uestion)?\s*)?\d+\s*[.):]\s*(.+)$/i', $line, $m)) {
                $flush();
                $current = ['source' => 'Line '.$lineNo, 'topic' => $topic, 'type' => '', 'prompt' => trim($m[1]), 'options' => [], 'answer' => '', 'points' => '', 'explanation' => ''];
                $last = 'prompt';
                continue;
            }
            if (! $current) continue; // text before the first question (titles, instructions)

            if (preg_match('/^(?:correct answers?|answers?|ans|key)\s*[:\-–]\s*(.+)$/iu', $line, $m)) { $current['answer'] = trim($m[1]); $last = null; continue; }
            if (preg_match('/^(?:explanation|rationale|feedback)\s*[:\-–]\s*(.*)$/iu', $line, $m)) { $current['explanation'] = trim($m[1]); $last = 'explanation'; continue; }
            if (preg_match('/^(?:points?|marks?)\s*[:\-–]\s*(\d+)\s*$/iu', $line, $m)) { $current['points'] = $m[1]; $last = null; continue; }
            if (preg_match('/^(?:type)\s*[:\-–]\s*(.+)$/iu', $line, $m)) { $current['type'] = trim($m[1]); $last = null; continue; }
            if (preg_match('/^\(?([a-h])\s*[.)]\s*(.+)$/i', $line, $m) && $current['answer'] === '') {
                $current['options'][strtolower($m[1])] = trim($m[2]);
                $last = 'opt_'.strtolower($m[1]);
                continue;
            }
            // Continuation of the previous line.
            if ($last === 'prompt') $current['prompt'] .= "\n".$line;
            elseif ($last === 'explanation') $current['explanation'] .= ' '.$line;
            elseif ($last && str_starts_with($last, 'opt_')) $current['options'][substr($last, 4)] .= ' '.$line;
        }
        $flush();
        return $items;
    }

    // ---- Shared ---------------------------------------------------------------------------

    /** "4", "module 4", "Module 4: Client discovery" -> "Module 4", so groups match across files. */
    public static function normalizeTopic(?string $topic): string
    {
        $t = trim(preg_replace('/\s+/', ' ', (string) $topic));
        if (preg_match('/^\d+$/', $t)) return 'Module '.$t;
        if (preg_match('/^module\s*[:#\-–]?\s*(\d+)\b/iu', $t, $m)) return 'Module '.$m[1];
        return mb_substr($t, 0, 120);
    }

    /** Normalises one raw question and records what's wrong with it. */
    private function build(array $raw): array
    {
        $errors = [];
        $answer = trim($raw['answer']);
        $options = [];
        foreach ($raw['options'] as $letter => $text) {
            $star = (bool) preg_match('/\s*\*$/', $text);
            $options[$letter] = ['text' => trim(preg_replace('/\s*\*$/', '', $text)), 'star' => $star];
        }

        $type = $this->type($raw['type'], $options, $answer);
        if ($type === null) { $errors[] = 'Unknown question type "'.$raw['type'].'".'; $type = 'single'; }

        $item = [
            'source' => $raw['source'],
            'type' => $type,
            'topic' => self::normalizeTopic($raw['topic']),
            'prompt' => trim($raw['prompt']),
            'options' => [],
            'accepted_answers' => null,
            'points' => 1,
            'explanation' => trim($raw['explanation']) ?: null,
        ];

        if ($item['prompt'] === '') $errors[] = 'The question text is empty.';
        if ($raw['points'] !== '') {
            if (ctype_digit($raw['points']) && (int) $raw['points'] >= 1 && (int) $raw['points'] <= 100) $item['points'] = (int) $raw['points'];
            else $errors[] = 'Points must be a whole number from 1 to 100.';
        }

        if ($type === 'short') {
            $accepted = array_values(array_filter(array_map('trim', preg_split('/\s*[|\n]\s*/', $answer))));
            if (! $accepted) $errors[] = 'Add the accepted answer (separate alternatives with "|").';
            $item['accepted_answers'] = implode("\n", $accepted);
        } elseif ($type === 'true_false') {
            $value = $this->truthy($answer);
            if ($value === null) $errors[] = 'The answer must be True or False.';
            $item['options'] = [['text' => 'True', 'correct' => $value === true], ['text' => 'False', 'correct' => $value === false]];
        } else {
            if (count($options) < 2) $errors[] = 'Add at least two answer choices.';
            if (count($options) > self::MAX_OPTIONS) $errors[] = 'Use at most '.self::MAX_OPTIONS.' answer choices.';
            $correct = $this->correctLetters($answer, $options);
            if ($correct === null) $errors[] = 'The answer "'.$answer.'" doesn\'t match any choice. Use the letter, e.g. B.';
            elseif (! $correct) $errors[] = 'Mark the correct answer (e.g. "Answer: B").';
            elseif ($type === 'single' && count($correct) > 1) $errors[] = 'One-answer questions can have only one correct choice. Set the type to "multiple" for several.';
            foreach ($options as $letter => $o) {
                $item['options'][] = ['text' => mb_substr($o['text'], 0, 255), 'correct' => in_array($letter, $correct ?? [], true)];
            }
        }

        return $item + ['errors' => $errors];
    }

    private function type(string $given, array $options, string $answer): ?string
    {
        $g = trim(preg_replace('/[^a-z]+/', ' ', strtolower($given)));
        if ($g !== '') {
            return match (true) {
                in_array($g, ['single', 'mc', 'mcq', 'multiple choice', 'one answer', 'single choice', 'radio'], true) => 'single',
                in_array($g, ['multiple', 'multi', 'multiple answer', 'multiple answers', 'select all', 'checkbox', 'several'], true) => 'multiple',
                in_array($g, ['true false', 'tf', 'truefalse', 'true or false', 'boolean'], true) => 'true_false',
                in_array($g, ['short', 'short answer', 'text', 'fill in', 'fill in the blank', 'open'], true) => 'short',
                default => null,
            };
        }
        if (! $options) return $this->truthy($answer) !== null ? 'true_false' : 'short';
        $stars = count(array_filter($options, fn ($o) => $o['star']));
        $letters = $this->correctLetters($answer, $options) ?? [];
        return max($stars, count($letters)) > 1 ? 'multiple' : 'single';
    }

    /** Letters of the correct choices: from "B", "A, C", "a;c", the choice text itself, or "*" marks. Null if unmatched. */
    private function correctLetters(string $answer, array $options): ?array
    {
        if ($answer === '') {
            return array_keys(array_filter($options, fn ($o) => $o['star']));
        }
        // "B. Host agency" or "(b) host agency": the letter decides.
        if (preg_match('/^\(?([a-h])\s*[.)]\s+\S/i', trim($answer), $m)) {
            $letter = strtolower($m[1]);
            return isset($options[$letter]) ? [$letter] : null;
        }
        $parts = preg_split('/\s*(?:,|;|\band\b|&|\s)\s*/i', strtolower(trim($answer)), -1, PREG_SPLIT_NO_EMPTY);
        $parts = array_map(fn ($p) => trim($p, " .)("), $parts);
        if ($parts && array_filter($parts, fn ($p) => preg_match('/^[a-h]$/', $p)) === $parts) {
            foreach ($parts as $p) if (! isset($options[$p])) return null;
            return array_values(array_unique($parts));
        }
        foreach ($options as $letter => $o) {
            if (Question::normalize($o['text']) === Question::normalize($answer)) return [$letter];
        }
        return null;
    }

    private function truthy(string $answer): ?bool
    {
        return match (Question::normalize($answer)) {
            'true', 't', 'yes', '1' => true,
            'false', 'f', 'no', '0' => false,
            default => null,
        };
    }

    private function openZip(string $path, string $kind): ZipArchive
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) throw new RuntimeException("That file couldn't be opened as .$kind. Save it again from Excel or Word and retry.");
        return $zip;
    }

    private function xml(string $xml): \SimpleXMLElement
    {
        $doc = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET);
        if ($doc === false) throw new RuntimeException("That file couldn't be read.");
        return $doc;
    }

    // ---- Saving ---------------------------------------------------------------------------

    /** Adds the valid items to the quiz, skipping questions whose text is already in it. Returns [added, skipped]. */
    public function import(Quiz $quiz, array $items): array
    {
        return DB::transaction(function () use ($quiz, $items) {
            $existing = $quiz->questions()->pluck('prompt')->map(fn ($p) => Question::normalize(RichText::plain($p)))->flip()->all();
            $position = (int) ($quiz->questions()->max('position') ?? 0);
            $added = 0; $skipped = 0;
            foreach ($items as $item) {
                $key = Question::normalize($item['prompt']);
                if ($item['errors'] || isset($existing[$key])) { $skipped++; continue; }
                $existing[$key] = true;
                $q = $quiz->questions()->create([
                    'topic' => $item['topic'] !== '' ? $item['topic'] : null,
                    'type' => $item['type'],
                    'prompt' => $item['prompt'],
                    'explanation' => $item['explanation'],
                    'accepted_answers' => $item['accepted_answers'],
                    'points' => $item['points'],
                    'position' => ++$position,
                ]);
                foreach ($item['options'] as $i => $o) {
                    $q->options()->create(['text' => $o['text'], 'is_correct' => $o['correct'], 'position' => $i + 1]);
                }
                $added++;
            }
            return [$added, $skipped];
        });
    }

    /** Text of questions already in the quiz, normalised, for marking duplicates in the preview. */
    public function existingPrompts(Quiz $quiz): array
    {
        return $quiz->questions()->pluck('prompt')->map(fn ($p) => Question::normalize(RichText::plain($p)))->flip()->all();
    }
}
