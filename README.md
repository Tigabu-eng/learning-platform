# Travel Advisor Resource Center — Learning Platform

The course platform for [traveladvisorresourcecenter.com](https://traveladvisorresourcecenter.com), built with Laravel 13. It covers the core of what you use on Thinkific: a course builder, a lesson player, quizzes, a timed final exam, checkout, certificates, progress tracking, and instructor and admin dashboards.

## Run it locally

Requirements: PHP 8.3+, Composer, Node 20+.

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite        # Windows: type nul > database\database.sqlite
php artisan migrate --seed
php artisan storage:link
npm install && npm run build
php artisan serve
```

Open http://localhost:8000. Every demo account uses the password `password`:

| Email | Role | What you'll see |
|---|---|---|
| admin@tarc.test | Admin | Whole-school stats, users and roles, all courses and orders |
| steven@tarc.test | Instructor | Steven Gould's Studio: the RTA Certification Program with its students, quiz results, the RTA50 coupon and sales |
| maya@tarc.test | Instructor | Fictional instructor's Studio: TA Launchpad Essentials, TA Launchpad Pro (draft), the free Client Discovery Calls course and the TRAVELPRO20 coupon |
| aisha@tarc.test | Student | Finished the *RTA Certification Program*, passed the exam, has a certificate |
| daniel@tarc.test | Student | Halfway through the *RTA Certification Program*, final exam still locked |
| liam@tarc.test | Student | Enrolled in the free *Client Discovery Calls* course |

Change or remove these accounts before going live (see "Going live" below).

Run the tests with `php artisan test`.

## Features

### Course builder (Studio → Courses)
- Courses with title, summary, Markdown description, image, level, price, and web address
- Chapters and lessons, reordered by dragging; lessons can move between chapters
- Lesson types: **Text** (Markdown), **Video** (YouTube/Vimeo link, direct MP4 link, or upload), **Download** (file only enrolled students can get), **Quiz**, **Final exam**
- Free preview lessons, lesson length, attachments on any lesson
- Learning rules: complete lessons in order, drip release (open N days after enrollment), access length (lifetime or N days), certificates on/off
- Draft and published states, plus "Preview as a student"

### Quizzes and the final exam
- Question types: multiple choice (one answer), multiple choice (several answers), true/false, short answer (several accepted answers, ignores case and spacing)
- Points per question, explanations, drag-to-reorder
- Settings: pass mark, attempt limit, time limit, shuffle questions, shuffle choices, show correct answers after submitting
- Final exam only: one per course, can stay locked until every lesson is done, and passing it is required for the certificate
- Students get a countdown timer and answers that save as they go. The exam submits itself at zero, and a timer that runs out while they're away is graded on the server. They also get a question jump bar and a review page with correct answers and explanations.

### Checkout
- Free courses enroll in one click
- Coupons: percent or fixed amount, for one course or all of an instructor's courses, with a use limit and expiry date
- Payment gateways, set by `PAYMENT_GATEWAY` in `.env`:
  - `test`: built-in sandbox with approve/decline buttons; no money moves (the default)
  - `stripe`: Stripe Checkout, the recommended choice for USD; set `STRIPE_SECRET_KEY`
  - `chapa`: Chapa, for payments in Ethiopian Birr; set `CHAPA_SECRET_KEY`
- Each payment is checked with the provider before the student is enrolled. The provider callback is `/webhooks/{gateway}` (GET or POST).
- Currency comes from `SCHOOL_CURRENCY` (default `USD`)

### Progress and certificates
- Lesson completion, a course percentage, resume where you left off, last-active time
- The curriculum path in the player shows done, current and locked lessons
- A certificate is issued automatically when every lesson is complete and the final exam is passed. It has a unique ID and a print/PDF layout, and anyone can check it on the public verification page (`/verify`).

### Dashboards
- **Student:** my courses with progress, certificates, purchases
- **Instructor:** revenue, students, completions, 14-day enrollment chart, per-course student list (progress, final exam score, certificate), per-student lesson and attempt history, manual enrollment, quiz analytics (average, pass rate, % correct per question), coupons, sales
- **Admin:** school totals, revenue by month, top courses, user role management, all courses and orders

## Where things live

| Area | Files |
|---|---|
| Access rules, completion, certificates | `app/Services/Progress.php` |
| Quiz attempts and grading | `app/Services/QuizEngine.php`, `app/Models/Question.php` |
| Checkout and coupons | `app/Services/Checkout.php`, `app/Services/Payments/*` |
| Routes | `routes/web.php` |
| Database schema | `database/migrations/2026_11_04_000100_create_lms_tables.php` |
| Demo data | `database/seeders/DatabaseSeeder.php` |
| Look and feel | `resources/css/app.css` (colours and components), `resources/views` |

## Going live

1. Set `APP_ENV=production`, `APP_DEBUG=false` and `APP_URL=https://traveladvisorresourcecenter.com` (or the subdomain you host it on, such as `learn.traveladvisorresourcecenter.com`). Certificate verification links use `APP_URL`.
2. Use MySQL or PostgreSQL instead of SQLite, and run `php artisan migrate --force` without `--seed`.
3. Create your admin account with `php artisan tinker`:
   `App\Models\User::create(['name' => 'Your Name', 'email' => 'you@traveladvisorresourcecenter.com', 'password' => 'a-strong-password', 'role' => 'admin']);`
4. Set `PAYMENT_GATEWAY=stripe` and `STRIPE_SECRET_KEY`. Point the Stripe redirect at your domain and test with sandbox keys first.
5. Run `php artisan storage:link`, `php artisan config:cache` and `php artisan route:cache`.

## Not built yet

These Thinkific features are not in the prototype: password reset and email notifications, assignments and surveys, live lessons, communities, course bundles and memberships, affiliates, a page/theme builder, SCORM, and importing courses from Thinkific.

The Chapa and Stripe integrations follow those providers' public APIs but haven't been run against live accounts. Test them with sandbox keys before taking real payments.
