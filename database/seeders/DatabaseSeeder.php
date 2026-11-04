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
 *   steven@tarc.test     — instructor: Steven Gould, TARC founder, teaches the RTA Certification Program
 *   maya@tarc.test       — instructor: fictional, teaches TA Launchpad and the free course
 *   daniel@tarc.test     — student, halfway through the RTA Certification Program
 *   aisha@tarc.test      — student, finished the RTA program with a certificate
 *   liam@tarc.test       — student, enrolled in the free Client Discovery Calls course
 *
 * The .test domain is reserved, so these addresses can never belong to a real person.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $pw = 'password';
        User::create(['name' => 'TARC Admin', 'email' => 'admin@tarc.test', 'password' => $pw, 'role' => 'admin']);
        $steven = User::create(['name' => 'Steven Gould', 'email' => 'steven@tarc.test', 'password' => $pw, 'role' => 'instructor',
            'bio' => 'Founder and Chief Innovation Officer of Travel Advisor Resource Center (TARC), and instructor of the Recognized Travel Advisor (RTA) Certification Program.']);
        $maya = User::create(['name' => 'Maya Thompson', 'email' => 'maya@tarc.test', 'password' => $pw, 'role' => 'instructor',
            'bio' => 'Independent travel advisor for 12 years, specializing in group tours and river cruises.']);
        $daniel = User::create(['name' => 'Daniel Reyes', 'email' => 'daniel@tarc.test', 'password' => $pw]);
        $aisha = User::create(['name' => 'Aisha Bello', 'email' => 'aisha@tarc.test', 'password' => $pw]);
        $liam = User::create(['name' => 'Liam Carter', 'email' => 'liam@tarc.test', 'password' => $pw]);

        $main = $this->rtaCertification($steven);
        $free = $this->clientDiscovery($maya);
        $this->launchpadEssentials($maya);
        $this->launchpadProDraft($maya);

        // Member discount for the Travel Pro Community, and an RTA launch offer.
        Coupon::create(['code' => 'TRAVELPRO20', 'type' => 'percent', 'value' => 20, 'created_by' => $maya->id, 'max_uses' => 100]);
        Coupon::create(['code' => 'RTA50', 'type' => 'fixed', 'value' => 50, 'course_id' => $main->id, 'created_by' => $steven->id]);

        $progress = app(Progress::class);
        $engine = app(QuizEngine::class);

        // Aisha: paid with a coupon, finished everything, passed the final exam → certificate.
        $e = $this->buy($aisha, $main, 11, 'TRAVELPRO20');
        foreach ($main->orderedLessons() as $lesson) {
            if ($lesson->isAssessment()) {
                $this->passQuiz($engine, $lesson->quiz, $e, perfect: $lesson->type !== 'exam');
            } else {
                $progress->complete($e, $lesson);
            }
        }

        // Daniel: paid, through Module 1 of the RTA program, failed the Module 1 quiz once then passed.
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

    /**
     * The Recognized Travel Advisor (RTA) Certification Program, following the 13-module structure on
     * traveladvisorresourcecenter.com/recognized-travel-advisor. Lesson titles match TARC's curriculum;
     * lessons marked as placeholders are waiting for TARC's own video or text.
     */
    private function rtaCertification(User $instructor): Course
    {
        $course = Course::create([
            'instructor_id' => $instructor->id,
            'title' => 'Recognized Travel Advisor (RTA) Certification Program',
            'subtitle' => 'Learn the skills to build your travel agency: why clients should work with you, choosing a niche, matching clients with vacations, and marketing and selling.',
            'description' => "The Recognized Travel Advisor (RTA) Certification Program teaches you the skills to build a travel agency. It is for new advisors who don’t know where to begin, and for experienced advisors who want to advance their knowledge, build a brand and align their business.\n\nThe program was created by travel advisors. Where other programs cover general business topics, RTA focuses on the parts of the travel industry advisors sell every day, on niche markets, and on sales and marketing for advisors.\n\nTARC states that the program is recognized by IATA, CCRA and several host agencies.\n\n## What’s included\n\n- 13 modules of videos, presentations and knowledge checks\n- Self-paced learning you can access anywhere in the world\n- Instructor support to guide you and answer your questions\n- A personalized TARC certificate when you pass the final exam\n\n## You will learn to\n\n- Explain to clients why they should work with you\n- Identify and select a niche\n- Match clients with the right vacation: cruises, all-inclusive resorts, land vacations, groups and air\n- Offer travel protection and protect yourself from liability\n- Market and sell your travel business\n\nMentoring is available for an additional fee. All sales are final.",
            'price' => 499, 'level' => 'All levels', 'status' => 'published', 'certificate_enabled' => true,
        ]);

        $placeholder = 'Lesson content coming soon. Instructors: add this lesson’s video or text in the Studio.';
        $todo = function ($chapter, array $titles) use ($placeholder) {
            foreach ($titles as $title) $this->lesson($chapter, 'text', $title, ['content' => $placeholder]);
        };

        // Module 1: Welcome. Lessons 1.1 and 1.3 are free previews, as on TARC's site.
        $m1 = $course->chapters()->create(['title' => 'Module 1: Welcome', 'position' => 1]);
        $this->lesson($m1, 'text', 'Welcome to the RTA Program', ['is_preview' => true, 'duration_minutes' => 4, 'content' => "Welcome to the Recognized Travel Advisor (RTA) Certification Program from Travel Advisor Resource Center (TARC).\n\nOver 13 modules you will learn how the travel industry works, how to choose your niche, how to sell cruises, all-inclusive resorts, land vacations, groups and air, and how to market your business.\n\n> Your travel business deserves structure, growth and support. Let’s get started."]);
        $this->lesson($m1, 'text', 'How to use the learning platform', ['duration_minutes' => 3, 'content' => "- **The path on the left** shows every module and lesson. A filled circle means the lesson is complete.\n- **Mark complete** when you finish a text, video or download lesson. Quizzes complete themselves when you pass.\n- **Resume** from your dashboard at any time. Your progress saves automatically.\n- **The final exam** opens once every lesson is complete. Pass it to receive your RTA certificate.\n\nQuestions? Email your instructor at admin@thetarc.com."]);
        $this->lesson($m1, 'text', 'Why clients use a travel advisor', ['is_preview' => true, 'duration_minutes' => 6, 'content' => "Clients can book a flight on their phone in two minutes. They come to an advisor for everything the booking engines can’t do:\n\n- **Judgment:** which ship, which hotel wing, which month to avoid\n- **Access:** perks, upgrades and amenities through supplier relationships\n- **Advocacy:** someone on their side when a flight is cancelled at midnight\n\n> Your value is the problems your clients never see."]);
        $q1 = $this->lesson($m1, 'quiz', 'Module 1 quiz', ['duration_minutes' => 4, 'content' => 'Three quick questions. You need 70% to pass and you can retake it.']);
        $this->quiz($q1, ['passing_score' => 70, 'show_answers' => true], [
            ['Which is the strongest reason a client uses an advisor instead of booking online?', 'single', ['Expert judgment and someone to advocate for them', 'It is always cheaper', 'Online booking is not allowed for cruises', 'Advisors can skip airport security'], 0, 'Price is rarely the reason; expertise and support are.'],
            ['What can an advisor offer that booking engines can’t? Select all that apply.', 'multiple', ['Judgment about which option suits the client', 'Perks and amenities through supplier relationships', 'Help when travel goes wrong', 'Guaranteed lowest airfare'], [0, 1, 2], 'No one can guarantee the lowest fare; expertise and advocacy are the value.'],
            ['When does the RTA final exam open?', 'single', ['When every lesson in the program is complete', 'On your first day', 'After Module 1', 'Only after mentoring'], 0, 'Finish every lesson to unlock the final exam.'],
        ]);

        $m2 = $course->chapters()->create(['title' => 'Module 2: Niche markets and geography', 'position' => 2]);
        $this->lesson($m2, 'text', 'Discover a niche', ['duration_minutes' => 8, 'content' => "Generalists compete on price. Specialists compete on knowledge.\n\n## Good niches have\n\n1. Travelers who spend enough to make commission worthwhile\n2. Trips complex enough that people want help\n3. A community you can reach, such as a club, a church or an alumni network\n\nExamples: multigenerational family trips, accessible travel, destination weddings, river cruises, safaris."]);
        $todo($m2, ['Travel styles', 'Types of clientele', 'Geography of the world', 'Geography: Africa', 'Geography: Antarctica', 'Geography: Asia', 'Geography: Australia', 'Geography: Europe', 'Geography: North America', 'Geography: South America', 'TARC continents and rivers']);

        $m3 = $course->chapters()->create(['title' => 'Module 3: Cruises', 'position' => 3]);
        $todo($m3, ['Introduction to cruises', 'Cruise itineraries', 'The onboard experience', 'Selling cruises', 'Cruise add-ons', 'Cruise tips and tricks', 'Cruise terminology', 'Ship tour: Carnival Vista', 'Ship tour: Norwegian Bliss', 'Additional cruise training resources']);

        $m4 = $course->chapters()->create(['title' => 'Module 4: All-inclusive resorts', 'position' => 4]);
        $todo($m4, ['Introduction to all-inclusive resorts', 'Destinations and inclusions', 'How to book all-inclusive resorts', 'Selling add-ons', 'All-inclusive tips and tricks', 'All-inclusive terminology', 'Resort tour: Generations Riviera Maya (family)', 'Resort tour: El Dorado (adults only)']);

        $m5 = $course->chapters()->create(['title' => 'Module 5: Land vacations', 'position' => 5]);
        $todo($m5, ['Land vacations', 'Escorted tours', 'Disney vacations', 'Useful tips for land vacations', 'Land terminology']);

        $m6 = $course->chapters()->create(['title' => 'Module 6: Groups', 'position' => 6]);
        $todo($m6, ['Introduction to groups', 'Growing your agency through groups', 'Group tips and tricks', 'Group terminology']);

        $m7 = $course->chapters()->create(['title' => 'Module 7: Air travel', 'position' => 7]);
        $todo($m7, ['Introduction to air', 'How to book air', 'Types of airfare', 'Airfare liabilities', 'Air travel extras']);

        $m8 = $course->chapters()->create(['title' => 'Module 8: Travel protection', 'position' => 8]);
        $todo($m8, ['Introduction to travel protection', 'Why advisors should offer travel protection', 'What might be covered', 'Travel advisor liability']);
        $this->lesson($m8, 'text', 'Offering travel protection', ['duration_minutes' => 8, 'content' => "Document problems ruin more trips than weather does. Before you take a deposit:\n\n1. **Passport validity:** many countries require passports to be valid for several months beyond the travel dates. Check each destination’s current rule.\n2. **Visas and entry permits:** confirm with the official government source for the traveler’s nationality, not a blog post.\n3. **Travel protection:** offer it on every booking and record the client’s decision in writing.\n\n> Rules change often. Always check official sources close to the booking date."]);
        $todo($m8, ['The seven hidden secrets of travel protection']);
        $q8 = $this->lesson($m8, 'quiz', 'Module 8 quiz', ['duration_minutes' => 5]);
        $this->quiz($q8, ['passing_score' => 70, 'show_answers' => true, 'shuffle_options' => true], [
            ['Which is the best source for a destination’s current visa requirements?', 'single', ['The official government source for the traveler’s nationality', 'A travel forum', 'Last year’s brochure', 'The client’s friend who went there'], 0, 'Entry rules change, so rely on official sources.'],
            ['You should offer travel protection on every booking and record the client’s decision.', 'true_false', [], 0, 'A written record protects both you and the client.'],
            ['When should you check a client’s passport expiry date?', 'single', ['Before taking a deposit', 'At the airport', 'After the trip', 'Only for cruises'], 0, 'Catching it early leaves time to renew.'],
        ]);

        $m9 = $course->chapters()->create(['title' => 'Module 9: Customer service', 'position' => 9]);
        $todo($m9, ['Customer service', 'Becoming a service-focused advisor']);

        $m10 = $course->chapters()->create(['title' => 'Module 10: Sales', 'position' => 10]);
        $todo($m10, ['Sales 101', 'The sales cycle']);
        $this->lesson($m10, 'text', 'Qualifying questions', ['duration_minutes' => 5, 'content' => "Qualifying questions tell you who is travelling, what they love, and what they can spend, before you build a proposal. Proposals built on guesses get ignored.\n\nDownload the qualifying questions in the next lesson and use them on every first call."]);
        $dl = $this->lesson($m10, 'download', 'Downloadable qualifying questions', ['duration_minutes' => 3, 'content' => 'Use these questions on your first call with every new client.']);
        Storage::disk('local')->put('attachments/rta-qualifying-questions.csv', "Question,Why it matters\nWho is travelling and how old are they?,Shapes room types and activities\nWhat was your favourite trip and why?,Reveals travel style\nWhat dates are fixed and which are flexible?,Opens better pricing\nWhat budget range feels comfortable?,Avoids proposals they can't afford\nAny mobility dietary or medical needs?,Prevents problems on the trip\nDo your passports expire within the next year?,Catches document issues early\n");
        $dl->update(['file_path' => 'attachments/rta-qualifying-questions.csv', 'file_name' => 'rta-qualifying-questions.csv']);

        $m11 = $course->chapters()->create(['title' => 'Module 11: Marketing', 'position' => 11]);
        $todo($m11, ['Introduction to marketing', 'Setting up Facebook and Instagram', 'Using a call to action', 'Email marketing guide', 'Paid marketing tips', '10 ways to jump-start your travel agency', 'Creating an engaging biography']);

        $m12 = $course->chapters()->create(['title' => 'Module 12: Developing your travel business', 'position' => 12]);
        $todo($m12, ['Travel industry accreditations']);
        $this->lesson($m12, 'text', 'Industry affiliations', ['duration_minutes' => 9, 'content' => "A **host agency** lends you its accreditation, supplier contracts and back office in exchange for a share of your commission. Going **fully independent** means getting your own accreditation and negotiating every relationship yourself.\n\nMost new advisors start with a host and move on once their book of business is steady. Compare host agencies, consortia and franchises in TARC’s listings."]);
        $todo($m12, ['Relationships and resources']);
        $this->lesson($m12, 'text', 'How commissions work', ['duration_minutes' => 7, 'content' => "Income comes from three places:\n\n| Source | Paid by | When |\n|---|---|---|\n| Commission | The supplier (cruise line, tour operator, hotel) | Usually after the client travels |\n| Service or planning fees | The client | Up front, when you start planning |\n| Overrides and bonuses | Suppliers or your host | When you hit sales targets |\n\nCommission rates vary widely by supplier and product, so always confirm the rate in writing before you quote."]);
        $todo($m12, ['Professionalism and ethics', 'Time management', 'Cover your assets', 'Why travel agents are rebranding as travel advisors']);
        $q12 = $this->lesson($m12, 'quiz', 'Module 12 quiz', ['duration_minutes' => 5, 'content' => 'Four questions. You need 70% to pass and you can retake it.']);
        $this->quiz($q12, ['passing_score' => 70, 'show_answers' => true], [
            ['Who typically pays a travel advisor’s commission?', 'single', ['The supplier, such as the cruise line or tour operator', 'The client, at the end of the trip', 'The airport', 'The tourism board'], 0, 'Suppliers pay commission for the business you bring them.'],
            ['What does a host agency usually provide? Select all that apply.', 'multiple', ['Supplier contracts and accreditation', 'Back-office and booking tools', 'A guaranteed number of clients', 'Training and support'], [0, 1, 3], 'Hosts don’t hand you clients; finding them is still your job.'],
            ['Commission is usually paid when the client books, before they travel.', 'true_false', [], 1, 'Most suppliers pay after travel, which is why many advisors also charge planning fees.'],
            ['A fee the client pays you up front for planning work is called a… (two words)', 'short', ['service fee', 'planning fee', 'service fees', 'planning fees'], null, 'Service or planning fees cover your time whether or not the client books.'],
        ]);

        $m13 = $course->chapters()->create(['title' => 'Module 13: Becoming a Recognized Travel Advisor', 'position' => 13]);
        $this->lesson($m13, 'text', 'The final exam process', ['duration_minutes' => 3, 'content' => "The RTA final exam covers the whole program. You have **20 minutes** and **3 attempts**, and you need **75%** to pass.\n\nComplete the exercises and read the prep notes in this module first. When you pass, your personalized TARC certificate is issued automatically, with a code anyone can use to verify it."]);
        $todo($m13, ['Customer service exercise', 'Sphere of influence exercise', 'Marketing plan exercise', 'Additional resources', 'Final exam prep notes']);
        $ex = $this->lesson($m13, 'exam', 'RTA final exam', ['duration_minutes' => 20, 'content' => "This exam covers the whole program. You have **20 minutes** and **3 attempts**. Score 75% or more to become a Recognized Travel Advisor."]);
        $this->quiz($ex, ['is_final' => true, 'passing_score' => 75, 'max_attempts' => 3, 'time_limit_minutes' => 20, 'shuffle_questions' => true, 'require_all_lessons' => true, 'show_answers' => true], [
            ['A host agency earns money mainly by…', 'single', ['Keeping a share of the commission its advisors earn', 'Charging travelers at the airport', 'Selling advertising', 'Government grants'], 0, 'Hosts split commission in exchange for their services.'],
            ['Which income sources are common for travel advisors? Select all that apply.', 'multiple', ['Supplier commission', 'Service or planning fees', 'Supplier bonuses and overrides', 'Airport tax refunds'], [0, 1, 2], 'Airport taxes are passed through, not income.'],
            ['Commission rates are the same for every supplier.', 'true_false', [], 1, 'Rates vary by supplier and product, so confirm before quoting.'],
            ['What is the main purpose of qualifying questions?', 'single', ['Understanding who is travelling, their style, budget and constraints', 'Collecting payment as quickly as possible', 'Choosing your own vacation', 'Confirming flight numbers'], 0, 'Qualifying comes before any proposal.'],
            ['An advisor who focuses on one type of travel, such as river cruises, is called a… (one word)', 'short', ['specialist', 'niche specialist'], null, 'Specialists compete on knowledge, not price.'],
            ['You should confirm entry requirements with official sources close to the booking date.', 'true_false', [], 0, 'Requirements change frequently.'],
            ['A client declines travel protection. What should you do?', 'single', ['Record their decision in writing', 'Add it anyway', 'Cancel the booking', 'Nothing'], 0, 'Documenting the decision protects you.'],
            ['Why do many advisors charge planning fees?', 'single', ['Commission often arrives after travel, and not every plan becomes a booking', 'Fees are required by law', 'Suppliers forbid commission', 'To discourage clients'], 0, 'Fees pay for your time regardless of the outcome.'],
        ]);

        return $course;
    }

    private function clientDiscovery(User $instructor): Course
    {
        $course = Course::create([
            'instructor_id' => $instructor->id,
            'title' => 'Client Discovery Calls',
            'subtitle' => 'A free TARC mini course on the first conversation with a new client.',
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

    private function launchpadEssentials(User $instructor): void
    {
        $course = Course::create([
            'instructor_id' => $instructor->id,
            'title' => 'TA Launchpad: The Travel Agency Setup Program (Essentials Edition)',
            'subtitle' => 'Set up your travel business with structure: business basics, a host agency or consortium, your CRM and your client paperwork.',
            'description' => "TA Launchpad walks you through setting up a travel agency step by step, so you can start booking clients on a solid footing.\n\n## You will set up\n\n- Your business structure, name and bank account\n- Your path to supplier access: a host agency, consortium or franchise\n- A CRM with a simple client workflow\n- Client terms and conditions and email templates\n\nWant more done-for-you templates and workflows? Upgrade to the Pro Edition.",
            'price' => 99, 'level' => 'Beginner', 'status' => 'published', 'certificate_enabled' => true,
        ]);
        $c1 = $course->chapters()->create(['title' => 'Module 1: Business foundations', 'position' => 1]);
        $this->lesson($c1, 'text', 'Your agency setup checklist', ['is_preview' => true, 'duration_minutes' => 6, 'content' => "Before you take your first booking, have these in place:\n\n1. **Business registration** for your state or country\n2. **A separate business bank account** for client deposits and commission\n3. **Errors and omissions insurance**, often available through your host agency\n4. **Seller of travel registration**, where your state requires it\n\n> Requirements differ by location. Check with your local authorities and your host agency."]);
        $this->lesson($c1, 'text', 'Host agencies, consortia and franchises', ['duration_minutes' => 8, 'content' => "There are three common ways to get supplier access:\n\n| Option | What you get | What you give |\n|---|---|---|\n| Host agency | Accreditation, supplier contracts, back office and training | A share of your commission, sometimes a monthly fee |\n| Consortium | Group buying power, preferred supplier rates and marketing | Membership fees; usually needs your own accreditation |\n| Franchise | A recognised brand and a ready-made system | Up-front and ongoing franchise fees |\n\nUse TARC’s host agency, consortia and franchise listings to compare your options."]);
        $c2 = $course->chapters()->create(['title' => 'Module 2: Systems and paperwork', 'position' => 2]);
        $this->lesson($c2, 'text', 'Setting up your CRM', ['duration_minutes' => 7, 'content' => "A CRM keeps every client, trip and follow-up in one place. Set up at least these stages:\n\n1. New inquiry\n2. Discovery call booked\n3. Proposal sent\n4. Deposit paid\n5. Final payment and documents\n6. Welcome home follow-up\n\nTARC offers CRM setups if you would rather have it done for you."]);
        $this->lesson($c2, 'text', 'Client terms and email templates', ['duration_minutes' => 6, 'content' => "Every client should agree to your terms and conditions before you take a deposit. They should cover your service fees, cancellation and refund rules, and the client’s responsibility for travel documents.\n\nSave time with reusable emails for inquiry replies, proposals, payment reminders and pre-departure checklists.\n\n> Have a qualified lawyer review your terms for your location."]);
        $c3 = $course->chapters()->create(['title' => 'Launch check', 'position' => 3]);
        $ex = $this->lesson($c3, 'exam', 'TA Launchpad final exam', ['duration_minutes' => 10, 'content' => 'Four questions on setting up your agency. Score 70% or more to pass.']);
        $this->quiz($ex, ['is_final' => true, 'passing_score' => 70, 'max_attempts' => null, 'time_limit_minutes' => 10, 'require_all_lessons' => true, 'show_answers' => true], [
            ['Why keep a separate business bank account?', 'single', ['To keep client deposits and commission apart from personal money', 'Suppliers only pay into savings accounts', 'It is the only way to get a CRM', 'Host agencies require two personal accounts'], 0, 'Clean records protect you and your clients.'],
            ['What does a host agency usually provide? Select all that apply.', 'multiple', ['Accreditation', 'Supplier contracts', 'Back-office support', 'Guaranteed clients'], [0, 1, 2], 'Finding clients is still your job.'],
            ['Clients should accept your terms and conditions before you take a deposit.', 'true_false', [], 0, 'Agreed terms prevent disputes later.'],
            ['The software that tracks clients, trips and follow-ups is called a… (three letters)', 'short', ['crm'], null, 'CRM stands for customer relationship management.'],
        ]);
    }

    private function launchpadProDraft(User $instructor): void
    {
        $course = Course::create(['instructor_id' => $instructor->id, 'title' => 'TA Launchpad: Pro Edition', 'subtitle' => 'The Essentials Edition, plus done-for-you workflows, legal templates and a CRM setup.', 'price' => 249]);
        $c = $course->chapters()->create(['title' => 'Module 1: Workflows', 'position' => 1]);
        $this->lesson($c, 'text', 'Your booking workflow, step by step', ['content' => 'Draft lesson.']);
    }
}
