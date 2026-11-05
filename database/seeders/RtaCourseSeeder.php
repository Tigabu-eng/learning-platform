<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Loads the RTA Certification Program outline on its own, without demo accounts or orders.
 * Safe for production: it creates the course as a draft and does nothing if it already exists.
 *
 *   RTA_INSTRUCTOR_EMAIL=someone@example.com php artisan db:seed --class=RtaCourseSeeder --force
 *
 * Without RTA_INSTRUCTOR_EMAIL the course is assigned to the first admin. Reassign it later in
 * Course settings once the real instructor has an account.
 */
class RtaCourseSeeder extends Seeder
{
    use BuildsCourses;

    public const TITLE = 'Recognized Travel Advisor (RTA) Certification Program';

    public function run(): void
    {
        if (Course::where('title', self::TITLE)->exists()) {
            $this->command?->warn('The RTA course already exists. Nothing was changed.');
            return;
        }
        $email = env('RTA_INSTRUCTOR_EMAIL');
        $instructor = $email
            ? User::where('email', $email)->first()
            : User::where('role', 'admin')->orderBy('id')->first();
        if (! $instructor) {
            throw new RuntimeException($email ? "No user with the email $email." : 'Create an admin account first, or set RTA_INSTRUCTOR_EMAIL.');
        }

        $course = $this->create($instructor, 'draft');
        $this->command?->info("Created \"{$course->title}\" as a draft, taught by {$instructor->name}.");
    }

    /**
     * The Recognized Travel Advisor (RTA) Certification Program, following the 13-module structure on
     * traveladvisorresourcecenter.com/recognized-travel-advisor. Lesson titles match TARC's curriculum;
     * lessons marked as placeholders are waiting for TARC's own video or text.
     */
    public function create(User $instructor, string $status = 'published'): Course
    {
        $course = Course::create([
            'instructor_id' => $instructor->id,
            'title' => self::TITLE,
            'subtitle' => 'Learn the skills to build your travel agency: why clients should work with you, choosing a niche, matching clients with vacations, and marketing and selling.',
            'description' => "The Recognized Travel Advisor (RTA) Certification Program teaches you the skills to build a travel agency. It is for new advisors who don’t know where to begin, and for experienced advisors who want to advance their knowledge, build a brand and align their business.\n\nThe program was created by travel advisors. Where other programs cover general business topics, RTA focuses on the parts of the travel industry advisors sell every day, on niche markets, and on sales and marketing for advisors.\n\nTARC states that the program is recognized by IATA, CCRA and several host agencies.\n\n## What’s included\n\n- 13 modules of videos, presentations and knowledge checks\n- Self-paced learning you can access anywhere in the world\n- Instructor support to guide you and answer your questions\n- A personalized TARC certificate when you pass the final exam\n\n## You will learn to\n\n- Explain to clients why they should work with you\n- Identify and select a niche\n- Match clients with the right vacation: cruises, all-inclusive resorts, land vacations, groups and air\n- Offer travel protection and protect yourself from liability\n- Market and sell your travel business\n\nMentoring is available for an additional fee. All sales are final.",
            'price' => 499, 'level' => 'All levels', 'status' => $status, 'certificate_enabled' => true,
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
}
