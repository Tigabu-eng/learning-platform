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
 *   admin@tarc.test      — super admin
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
    use BuildsCourses;

    public function run(): void
    {
        $pw = 'password';
        User::create(['name' => 'TARC Admin', 'email' => 'admin@tarc.test', 'password' => $pw, 'role' => 'super_admin']);
        $steven = User::create(['name' => 'Steven Gould', 'email' => 'steven@tarc.test', 'password' => $pw, 'role' => 'instructor',
            'bio' => 'Founder and Chief Innovation Officer of Travel Advisor Resource Center (TARC), and instructor of the Recognized Travel Advisor (RTA) Certification Program.']);
        $maya = User::create(['name' => 'Maya Thompson', 'email' => 'maya@tarc.test', 'password' => $pw, 'role' => 'instructor',
            'bio' => 'Independent travel advisor for 12 years, specializing in group tours and river cruises.']);
        $daniel = User::create(['name' => 'Daniel Reyes', 'email' => 'daniel@tarc.test', 'password' => $pw]);
        $aisha = User::create(['name' => 'Aisha Bello', 'email' => 'aisha@tarc.test', 'password' => $pw]);
        $liam = User::create(['name' => 'Liam Carter', 'email' => 'liam@tarc.test', 'password' => $pw]);

        $main = (new RtaCourseSeeder)->create($steven);
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
