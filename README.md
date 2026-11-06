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
| admin@tarc.test | Super admin | Whole-school stats, users and roles (including making admins), all courses and orders, proctor requests |
| steven@tarc.test | Instructor | Steven Gould's Studio: the RTA Certification Program with its students, quiz results, the RTA50 coupon and sales |
| maya@tarc.test | Instructor | Fictional instructor's Studio: TA Launchpad Essentials, TA Launchpad Pro (draft), the free Client Discovery Calls course and the TRAVELPRO20 coupon |
| aisha@tarc.test | Student | Finished the *RTA Certification Program*, passed the exam, has a certificate |
| daniel@tarc.test | Student | Halfway through the *RTA Certification Program*, final exam still locked |
| liam@tarc.test | Student | Enrolled in the free *Client Discovery Calls* course |

Change or remove these accounts before going live (see "Going live" below).

Run the tests with `php artisan test`.

## Features

### Course builder (Studio → Courses)
- Courses with title, summary, formatted description, image, level, price, and web address
- A formatting toolbar (bold, italic, underline, headings, lists, links) on every long text box: course descriptions, lesson text, question text and explanations
- Chapters and lessons, reordered by dragging; lessons can move between chapters
- Lesson types: **Text**, **Video** (YouTube/Vimeo link, direct MP4 link, or upload), **Download** (file only enrolled students can get), **Quiz**, **Final exam**
- Free preview lessons, lesson length, attachments on any lesson
- Learning rules: complete lessons in order, drip release (open N days after enrollment), access length (lifetime or N days), certificates on/off
- Draft and published states, plus "Preview as a student"

### Quizzes and the final exam
- Question types: multiple choice (one answer), multiple choice (several answers), true/false, short answer (several accepted answers, ignores case and spacing)
- Points per question, explanations, drag-to-reorder
- Settings: pass mark, attempt limit, time limit, shuffle questions, shuffle choices, show correct answers after submitting
- Final exam only: one per course, can stay locked until every lesson is done, and passing it is required for the certificate
- **Question bank:** set "Questions per attempt" and each attempt draws that many at random, balanced across topics (modules), with unseen questions first on retakes. Every student gets a different exam.
- **Import questions** from Excel, CSV, Word or pasted text, with a preview before anything is saved. Duplicates are skipped, and a template is available to download.
- **Exam lock:** open to every enrolled student, one shared password, or a one-time code per student
- **Proctoring:** the student nominates a proctor on the exam page. Staff approve or decline in Studio → Proctoring. Approving emails the proctor a one-time code and a private page link (no account needed), and the proctor confirms the session afterwards. The student never sees the code.
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

### Accounts and email
- Sign up, log in, **Forgot password?** (a one-time link valid for 60 minutes), and a show/hide button on password boxes
- Roles: student, instructor, admin, super admin. Only the super admin can make admins, and nobody can change their own role.
- Emails: welcome, payment receipt, "you're enrolled" (staff enrollment; new accounts get a 7-day set-password link), password reset, and the proctoring emails (new request, code to the proctor, decision to the student)
- Mail goes out over SMTP (`MAIL_*` in `.env`). Replies go to `MAIL_REPLY_TO_ADDRESS`. Locally, `MAIL_MAILER=log` writes emails to `storage/logs/laravel.log`.

### Dashboards
- **Student:** my courses with progress, certificates, purchases
- **Instructor:** revenue, students, completions, 14-day enrollment chart, per-course student list (progress, final exam score, certificate), per-student lesson and attempt history, manual enrollment, quiz analytics (average, pass rate, % correct per question), coupons, sales
- **Admin:** school totals, revenue by month, top courses, user role management, all courses and orders

## Where things live

| Area | Files |
|---|---|
| Access rules, completion, certificates | `app/Services/Progress.php` |
| Quiz attempts and grading | `app/Services/QuizEngine.php`, `app/Models/Question.php` |
| Question bank draw | `app/Services/QuizEngine.php` (`drawQuestions`) |
| Question import | `app/Services/QuestionImporter.php`, `app/Http/Controllers/Studio/QuestionImportController.php` |
| Exam codes and proctoring | `app/Models/ExamAccessCode.php`, `app/Services/Proctoring.php`, `app/Http/Controllers/ProctorPortalController.php` |
| Rich text (editor and sanitizing) | `resources/js/rich-editor.js`, `app/Support/RichText.php` |
| Passwords and email | `app/Http/Controllers/PasswordController.php`, `app/Mail/*`, `resources/views/mail/*`, `app/Support/SafeMail.php` |
| Checkout and coupons | `app/Services/Checkout.php`, `app/Services/Payments/*` |
| Routes | `routes/web.php` |
| Database schema | `database/migrations/2026_11_04_000100_create_lms_tables.php` |
| Demo data | `database/seeders/DatabaseSeeder.php`; the RTA course alone (safe for production) is `RtaCourseSeeder.php` |
| Rules, status and backlog | [briefing.md](briefing.md) |
| Server and deploys | [DEPLOY.md](DEPLOY.md) |
| Look and feel | `resources/css/app.css` (colours and components), `resources/views` |

## Going live

Production runs at **learn.tarctpc.com** on TARC's existing host. GitHub Actions tests every push and deploys `main` automatically; see [DEPLOY.md](DEPLOY.md) for the one-time server setup. The manual steps below are the same steps, for reference.

1. Set `APP_ENV=production`, `APP_DEBUG=false` and `APP_URL=https://learn.tarctpc.com`. Certificate verification links and emailed links use `APP_URL`.
2. Use MySQL or MariaDB instead of SQLite, and run `php artisan migrate --force` without `--seed`.
3. Sign up on the site, then make that account super admin on the server: `php artisan user:super-admin you@example.com`.
4. Set the `MAIL_*` settings (see DEPLOY.md → Email).
5. Set `PAYMENT_GATEWAY=stripe` and `STRIPE_SECRET_KEY`. Point the Stripe redirect at your domain and test with sandbox keys first.
6. Run `php artisan storage:link`, `php artisan config:cache` and `php artisan route:cache`.

## Not built yet

These Thinkific features are not built yet: certificate and reminder emails, refunds, assignments and surveys, live lessons, communities, course bundles and memberships, affiliates, a page/theme builder, SCORM, and importing courses from Thinkific.

The Chapa and Stripe integrations follow those providers' public APIs but haven't been run against live accounts. Test them with sandbox keys before taking real payments.
