<?php

namespace Database\Seeders;

use App\Models\Coupon;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Order;
use App\Models\Quiz;
use App\Models\User;
use App\Services\Progress;
use App\Services\QuizEngine;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Demo school for Travel Advisor Resource Center. All accounts use the password "password".
 *   admin@tarc.test      — admin
 *   maya@tarc.test       — instructor
 *   daniel@tarc.test     — student, halfway through "Becoming a Travel Advisor"
 *   aisha@tarc.test      — student, finished it with a certificate
 *   liam@tarc.test       — student, enrolled in the free course
 *
 * The .test domain is reserved, so these addresses can never belong to a real person.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $pw = 'password';
        User::create(['name' => 'TARC Admin', 'email' => 'admin@tarc.test', 'password' => $pw, 'role' => 'admin']);
        $maya = User::create(['name' => 'Maya Thompson', 'email' => 'maya@tarc.test', 'password' => $pw, 'role' => 'instructor',
            'bio' => 'Independent travel advisor for 12 years, specializing in group tours and river cruises.']);
        $daniel = User::create(['name' => 'Daniel Reyes', 'email' => 'daniel@tarc.test', 'password' => $pw]);
        $aisha = User::create(['name' => 'Aisha Bello', 'email' => 'aisha@tarc.test', 'password' => $pw]);
        $liam = User::create(['name' => 'Liam Carter', 'email' => 'liam@tarc.test', 'password' => $pw]);

        $main = $this->becomingAnAdvisor($maya);
        $free = $this->clientDiscovery($maya);
        $this->draftCourse($maya);

        Coupon::create(['code' => 'WELCOME20', 'type' => 'percent', 'value' => 20, 'created_by' => $maya->id, 'max_uses' => 100]);
        Coupon::create(['code' => 'ADVISOR50', 'type' => 'fixed', 'value' => 50, 'course_id' => $main->id, 'created_by' => $maya->id]);

        $progress = app(Progress::class);
        $engine = app(QuizEngine::class);

        // Aisha: paid with a coupon, finished everything, passed the final exam → certificate.
        $e = $this->buy($aisha, $main, 11, 'WELCOME20');
        foreach ($main->orderedLessons() as $lesson) {
            if ($lesson->isAssessment()) {
                $this->passQuiz($engine, $lesson->quiz, $e, perfect: $lesson->type !== 'exam');
            } else {
                $progress->complete($e, $lesson);
            }
        }

        // Daniel: paid, through chapter one, failed the first quiz once then passed.
        $e = $this->buy($daniel, $main, 4);
        foreach ($main->orderedLessons()->take(4) as $lesson) {
            if ($lesson->isAssessment()) {
                $this->failQuiz($engine, $lesson->quiz, $e);
                $this->passQuiz($engine, $lesson->quiz, $e, perfect: true);
            } else {
                $progress->complete($e, $lesson);
            }
        }
        $e->update(['last_lesson_id' => $main->orderedLessons()[4]->id, 'last_activity_at' => now()->subDay()]);

        // Liam: free course, first lesson done.
        $e = $progress->enroll($liam, $free);
        $e->update(['enrolled_at' => now()->subDays(2)]);
        $progress->complete($e, $free->orderedLessons()->first());

        // Aisha also took the free course.
        $e = $progress->enroll($aisha, $free);
        $e->update(['enrolled_at' => now()->subDays(7)]);
    }

    private function buy(User $user, Course $course, int $daysAgo, ?string $code = null)
    {
        $coupon = $code ? Coupon::where('code', $code)->first() : null;
        $discount = $coupon ? $coupon->discountFor((float) $course->price) : 0;
        $order = Order::create([
            'user_id' => $user->id, 'course_id' => $course->id, 'coupon_id' => $coupon?->id,
            'reference' => 'TARC-'.strtoupper(Str::random(10)), 'amount' => $course->price, 'discount' => $discount,
            'total' => $course->price - $discount, 'currency' => config('school.currency'), 'gateway' => 'test',
            'status' => 'paid', 'paid_at' => now()->subDays($daysAgo), 'created_at' => now()->subDays($daysAgo),
        ]);
        if ($coupon) $coupon->increment('uses');
        $e = app(Progress::class)->enroll($user, $course, $order->id);
        $e->update(['enrolled_at' => now()->subDays($daysAgo)]);
        return $e;
    }

    private function correctAnswers(Quiz $quiz): array
    {
        $answers = [];
        foreach ($quiz->questions()->with('options')->get() as $q) {
            $answers[$q->id] = match ($q->type) {
                'multiple' => $q->options->where('is_correct', true)->pluck('id')->map(fn ($i) => (string) $i)->values()->all(),
                'short' => trim(explode("\n", $q->accepted_answers)[0]),
                default => (string) $q->options->firstWhere('is_correct', true)->id,
            };
        }
        return $answers;
    }

    private function passQuiz(QuizEngine $engine, Quiz $quiz, $enrollment, bool $perfect): void
    {
        $answers = $this->correctAnswers($quiz);
        if (! $perfect) array_pop($answers); // miss one question
        $engine->submit($engine->start($quiz, $enrollment), $answers);
    }

    private function failQuiz(QuizEngine $engine, Quiz $quiz, $enrollment): void
    {
        $answers = array_slice($this->correctAnswers($quiz), 0, 1, true);
        $engine->submit($engine->start($quiz, $enrollment), $answers);
    }

    private function lesson($chapter, string $type, string $title, array $extra = []): Lesson
    {
        return $chapter->lessons()->create(array_merge([
            'course_id' => $chapter->course_id, 'type' => $type, 'title' => $title,
            'position' => ($chapter->lessons()->max('position') ?? 0) + 1,
        ], $extra));
    }

    /** $questions: [prompt, type, options|accepted answers, correct index(es), explanation] */
    private function quiz(Lesson $lesson, array $settings, array $questions): Quiz
    {
        $quiz = Quiz::create(['lesson_id' => $lesson->id] + $settings);
        foreach ($questions as $i => [$prompt, $type, $choices, $correct, $why]) {
            $q = $quiz->questions()->create([
                'type' => $type, 'prompt' => $prompt, 'explanation' => $why, 'points' => 1, 'position' => $i + 1,
                'accepted_answers' => $type === 'short' ? implode("\n", $choices) : null,
            ]);
            if ($type === 'true_false') $choices = ['True', 'False'];
            if ($type !== 'short') {
                foreach ($choices as $j => $text) {
                    $q->options()->create(['text' => $text, 'is_correct' => in_array($j, (array) $correct, true), 'position' => $j + 1]);
                }
            }
        }
        return $quiz;
    }

    private function becomingAnAdvisor(User $instructor): Course
    {
        $course = Course::create([
            'instructor_id' => $instructor->id,
            'title' => 'Becoming a Travel Advisor',
            'subtitle' => 'How the business works, how you get paid, and how to book your first clients with confidence.',
            'description' => "This course is for people starting out as travel advisors, and for experienced agents moving from a storefront agency to working independently.\n\n## You will learn to\n\n- Explain how advisors earn commission and when to charge a service fee\n- Choose between joining a host agency and running fully independent\n- Pick a niche that fits your experience and your market\n- Run a client discovery call and turn it into a proposal\n- Check passport, visa and insurance requirements before you book\n\nEach chapter ends with a short quiz. Pass the final exam to earn your certificate.",
            'price' => 149, 'level' => 'Beginner', 'status' => 'published', 'certificate_enabled' => true,
        ]);

        $c1 = $course->chapters()->create(['title' => 'How the travel business works', 'position' => 1]);
        $this->lesson($c1, 'text', 'What a travel advisor actually does', ['is_preview' => true, 'duration_minutes' => 6, 'content' => "Clients can book a flight on their phone in two minutes. They come to an advisor for everything the booking engines can’t do:\n\n- **Judgment** — which ship, which hotel wing, which month to avoid\n- **Access** — perks, upgrades and amenities through supplier relationships\n- **Advocacy** — someone on their side when a flight is cancelled at midnight\n\n> Your value is the problems your clients never see."]);
        $this->lesson($c1, 'video', 'Host agency or fully independent?', ['duration_minutes' => 9, 'content' => "A **host agency** lends you its accreditation, supplier contracts and back office in exchange for a share of your commission. Going **fully independent** means getting your own accreditation and negotiating every relationship yourself.\n\nMost new advisors start with a host and move on once their book of business is steady.\n\n*Add your video link to this lesson in the studio.*"]);
        $this->lesson($c1, 'text', 'How advisors get paid', ['duration_minutes' => 7, 'content' => "Income comes from three places:\n\n| Source | Paid by | When |\n|---|---|---|\n| Commission | The supplier (cruise line, tour operator, hotel) | Usually after the client travels |\n| Service or planning fees | The client | Up front, when you start planning |\n| Overrides and bonuses | Suppliers or your host | When you hit sales targets |\n\nCommission rates vary widely by supplier and product, so always confirm the rate in writing before you quote."]);
        $q1 = $this->lesson($c1, 'quiz', 'Chapter check: the business', ['duration_minutes' => 5, 'content' => 'Five quick questions. You need 70% to pass and you can retake it.']);
        $this->quiz($q1, ['passing_score' => 70, 'show_answers' => true], [
            ['Who typically pays a travel advisor’s commission?', 'single', ['The supplier, such as the cruise line or tour operator', 'The client, at the end of the trip', 'The airport', 'The tourism board'], 0, 'Suppliers pay commission for the business you bring them.'],
            ['What does a host agency usually provide? Select all that apply.', 'multiple', ['Supplier contracts and accreditation', 'Back-office and booking tools', 'A guaranteed number of clients', 'Training and support'], [0, 1, 3], 'Hosts don’t hand you clients — finding them is still your job.'],
            ['Commission is usually paid when the client books, before they travel.', 'true_false', [], 1, 'Most suppliers pay after travel, which is why many advisors also charge planning fees.'],
            ['A fee the client pays you up front for planning work is called a… (two words)', 'short', ['service fee', 'planning fee', 'service fees', 'planning fees'], null, 'Service or planning fees cover your time whether or not the client books.'],
            ['Which is the strongest reason a client uses an advisor instead of booking online?', 'single', ['Expert judgment and someone to advocate for them', 'It is always cheaper', 'Online booking is not allowed for cruises', 'Advisors can skip airport security'], 0, 'Price is rarely the reason; expertise and support are.'],
        ]);

        $c2 = $course->chapters()->create(['title' => 'Working with clients and suppliers', 'position' => 2]);
        $this->lesson($c2, 'text', 'Choosing your niche', ['duration_minutes' => 8, 'content' => "Generalists compete on price. Specialists compete on knowledge.\n\n## Good niches have\n\n1. Travelers who spend enough to make commission worthwhile\n2. Trips complex enough that people want help\n3. A community you can reach — a club, a church, an alumni network\n\nExamples: multigenerational family trips, accessible travel, destination weddings, river cruises, safaris."]);
        $dl = $this->lesson($c2, 'download', 'Client discovery questionnaire', ['duration_minutes' => 3, 'content' => 'Use these questions on your first call with every new client.']);
        Storage::disk('local')->put('attachments/client-discovery-questionnaire.csv', "Question,Why it matters\nWho is travelling and how old are they?,Shapes room types and activities\nWhat was your favourite trip and why?,Reveals travel style\nWhat dates are fixed and which are flexible?,Opens better pricing\nWhat budget range feels comfortable?,Avoids proposals they can't afford\nAny mobility dietary or medical needs?,Prevents problems on the trip\nDo your passports expire within the next year?,Catches document issues early\n");
        $dl->update(['file_path' => 'attachments/client-discovery-questionnaire.csv', 'file_name' => 'client-discovery-questionnaire.csv']);
        $this->lesson($c2, 'text', 'Travel documents and insurance', ['duration_minutes' => 8, 'content' => "Document problems ruin more trips than weather does. Before you take a deposit:\n\n1. **Passport validity** — many countries require passports to be valid for several months beyond the travel dates. Check each destination’s current rule.\n2. **Visas and entry permits** — confirm with the official government source for the traveler’s nationality, not a blog post.\n3. **Travel insurance** — offer it on every booking and record the client’s decision in writing.\n\n> Rules change often. Always check official sources close to the booking date."]);
        $q2 = $this->lesson($c2, 'quiz', 'Chapter check: clients and suppliers', ['duration_minutes' => 5]);
        $this->quiz($q2, ['passing_score' => 70, 'show_answers' => true, 'shuffle_options' => true], [
            ['Which is the best source for a destination’s current visa requirements?', 'single', ['The official government source for the traveler’s nationality', 'A travel forum', 'Last year’s brochure', 'The client’s friend who went there'], 0, 'Entry rules change — rely on official sources.'],
            ['You should offer travel insurance on every booking and record the client’s decision.', 'true_false', [], 0, 'A written record protects both you and the client.'],
            ['What makes a strong niche? Select all that apply.', 'multiple', ['Travelers who spend enough to make commission worthwhile', 'Trips complex enough that people want help', 'A community you can reach', 'The lowest possible prices'], [0, 1, 2], 'Competing on price is the opposite of specializing.'],
            ['When should you check a client’s passport expiry date?', 'single', ['Before taking a deposit', 'At the airport', 'After the trip', 'Only for cruises'], 0, 'Catching it early leaves time to renew.'],
        ]);

        $c3 = $course->chapters()->create(['title' => 'Final exam', 'position' => 3]);
        $ex = $this->lesson($c3, 'exam', 'Travel advisor final exam', ['duration_minutes' => 20, 'content' => "This exam covers the whole course. You have **20 minutes** and **3 attempts**. Score 75% or more to earn your certificate."]);
        $this->quiz($ex, ['is_final' => true, 'passing_score' => 75, 'max_attempts' => 3, 'time_limit_minutes' => 20, 'shuffle_questions' => true, 'require_all_lessons' => true, 'show_answers' => true], [
            ['A host agency earns money mainly by…', 'single', ['Keeping a share of the commission its advisors earn', 'Charging travelers at the airport', 'Selling advertising', 'Government grants'], 0, 'Hosts split commission in exchange for their services.'],
            ['Which income sources are common for travel advisors? Select all that apply.', 'multiple', ['Supplier commission', 'Service or planning fees', 'Supplier bonuses and overrides', 'Airport tax refunds'], [0, 1, 2], 'Airport taxes are passed through, not income.'],
            ['Commission rates are the same for every supplier.', 'true_false', [], 1, 'Rates vary by supplier and product — confirm before quoting.'],
            ['What is the main purpose of a client discovery call?', 'single', ['Understanding who is travelling, their style, budget and constraints', 'Collecting payment as quickly as possible', 'Choosing your own vacation', 'Confirming flight numbers'], 0, 'Discovery comes before any proposal.'],
            ['An advisor who focuses on one type of travel, such as river cruises, is called a… (one word)', 'short', ['specialist', 'niche specialist'], null, 'Specialists compete on knowledge, not price.'],
            ['You should confirm entry requirements with official sources close to the booking date.', 'true_false', [], 0, 'Requirements change frequently.'],
            ['A client declines travel insurance. What should you do?', 'single', ['Record their decision in writing', 'Add it anyway', 'Cancel the booking', 'Nothing'], 0, 'Documenting the decision protects you.'],
            ['Why do many advisors charge planning fees?', 'single', ['Commission often arrives after travel, and not every plan becomes a booking', 'Fees are required by law', 'Suppliers forbid commission', 'To discourage clients'], 0, 'Fees pay for your time regardless of the outcome.'],
        ]);

        return $course;
    }

    private function clientDiscovery(User $instructor): Course
    {
        $course = Course::create([
            'instructor_id' => $instructor->id,
            'title' => 'Client Discovery Calls',
            'subtitle' => 'A free mini course on the first conversation with a new client.',
            'description' => "Learn a simple structure for discovery calls that leaves clients feeling heard and gives you everything you need for a winning proposal.",
            'price' => 0, 'level' => 'All levels', 'status' => 'published', 'sequential' => true,
        ]);
        $c1 = $course->chapters()->create(['title' => 'The call', 'position' => 1]);
        $this->lesson($c1, 'text', 'Why discovery comes first', ['duration_minutes' => 4, 'is_preview' => true, 'content' => "Proposals built on guesses get ignored. Ten minutes of good questions saves hours of rework and makes clients far more likely to book."]);
        $this->lesson($c1, 'text', 'A five-part call structure', ['duration_minutes' => 8, 'content' => "1. Who is travelling\n2. Past trips they loved and hated\n3. Dates and flexibility\n4. Budget comfort zone\n5. Must-haves and deal-breakers\n\nEnd by agreeing on when you’ll send the proposal."]);
        $c2 = $course->chapters()->create(['title' => 'Check your understanding', 'position' => 2]);
        $ex = $this->lesson($c2, 'exam', 'Discovery calls final exam', ['duration_minutes' => 10]);
        $this->quiz($ex, ['is_final' => true, 'passing_score' => 70, 'max_attempts' => null, 'time_limit_minutes' => 10, 'require_all_lessons' => true, 'show_answers' => true], [
            ['What should happen before you write a proposal?', 'single', ['A discovery call', 'Taking a deposit', 'Booking flights', 'Sending a contract'], 0, 'Discovery gives the proposal its foundation.'],
            ['Asking about trips the client disliked is a waste of time.', 'true_false', [], 1, 'Dislikes tell you what to avoid.'],
            ['Which belong in a discovery call? Select all that apply.', 'multiple', ['Who is travelling', 'Budget comfort zone', 'Your own commission rate'], [0, 1], 'Your commission is not the client’s concern on the first call.'],
        ]);
        return $course;
    }

    private function draftCourse(User $instructor): void
    {
        $course = Course::create(['instructor_id' => $instructor->id, 'title' => 'Group Travel Essentials', 'subtitle' => 'Plan, price and run group trips without the headaches.', 'price' => 99]);
        $c = $course->chapters()->create(['title' => 'Getting started', 'position' => 1]);
        $this->lesson($c, 'text', 'Is group travel right for you?', ['content' => 'Draft lesson.']);
    }
}
