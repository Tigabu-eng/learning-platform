# TARC Learning Platform: briefing

## 1. What the system is

The online learning platform for **Travel Advisor Resource Center (TARC)™** ([traveladvisorresourcecenter.com](https://traveladvisorresourcecenter.com)). TARC provides "comprehensive resources designed specifically for travel professionals at every stage of their journey": new and experienced travel advisors, people joining the industry, and supplier partners. Contact: admin@thetarc.com.

It trains travel advisors, from people booking their first clients to experienced agents adding a specialty. Each course is a path of short lessons and quizzes that ends in a timed final exam. Passing it earns a certificate that clients, suppliers and host agencies can verify online.

**TARC programs this platform delivers or connects to:**

| TARC offering | Role on the platform |
|---|---|
| **Recognized Travel Advisor (RTA) Certification Program** ([program page](https://traveladvisorresourcecenter.com/recognized-travel-advisor/)) | Flagship course: $499 with instant access, self-paced, taught by Steven Gould. It has 13 modules and about 79 lessons; lessons 1.1 and 1.3 are free previews. Each module ends with a quiz, and a final exam leads to a personalized TARC certificate. Mentoring costs extra, and all sales are final |
| **TA Launchpad: The Travel Agency Setup Program** | Course in two editions, Essentials and Pro (Pro adds done-for-you workflows, legal templates and CRM setup) |
| **Free mini courses** (for example Client Discovery Calls) | Entry point that brings new advisors in |
| **Travel Pro Community** | Membership (30-day free trial on the main site). Members get course discounts today (coupon `TRAVELPRO20` in the demo) and member pricing later (see section 4) |
| **TARC Expo, TARC Talk, webinars** | Source material for on-demand lessons |
| **Business Builder Tools** (Travelytics, CRM setups, legal templates, workflows and email templates) | Downloads and upsells inside lessons |
| **Travel Partner Directory, host agency, consortia and franchise listings** | Linked from lessons instead of copied in |

The platform links back to the main site in the header and footer. The links and support email are set in `config/school.php` (`brand`) and can be overridden with `TARC_SITE_URL`, `TARC_COMMUNITY_URL` and `TARC_SUPPORT_EMAIL`.

It is a self-hosted Laravel 13 application (PHP 8.3+, Blade, Tailwind 4, SQLite in development, MySQL or PostgreSQL in production). It has three kinds of users:

| Role | What they do |
|---|---|
| Student | Browses and buys courses, learns lesson by lesson, takes quizzes and the final exam, keeps certificates |
| Instructor | Builds courses in the Studio, sets pricing and coupons, follows students and quiz results |
| Admin | Sees the whole school, manages users and roles, all courses and all orders |

## 2. Inspiration

The reference product is **Thinkific**, where TARC's courses currently live. We take its proven structure:

- courses made of chapters and lessons, with free previews
- quizzes and a final exam inside the course flow
- checkout with coupons
- completion certificates
- an instructor back office for content, students and sales

We own the platform so that the learning experience, the data and the brand are TARC's, and so it can grow features built for the travel-advisor profession rather than for course creators in general.

## 3. What the agent must refer to every time

Before changing anything, read and respect these.

**Domain rules** (in `app/Services/Progress.php`, `app/Services/QuizEngine.php` and `app/Models/Question.php`):
- **Lesson locks** are checked in this order: not enrolled (only free previews are open), access expired, drip date not reached, sequential order, final exam gated until every lesson is done. Instructors and admins previewing a course bypass locks.
- **Completion:** text, video and download lessons are completed by the student. Quizzes and exams are completed only by **passing** them.
- **Certificates:** issued automatically when progress reaches 100%, the course has certificates turned on, and the final exam (if there is one) has been passed. Every certificate has a public verification code.
- **Grading:**
  - Multiple-select questions are **all or nothing**.
  - Short answers ignore capital letters and extra spaces.
  - The score is a percentage of points, and a student passes when the score is at least the pass mark.
- **One final exam per course.**
- **Question banks:** when a quiz sets "questions per attempt", each attempt draws that many questions at random. Every topic gets a share in proportion to its size (largest remainder), and questions the student saw in earlier attempts are used only once unseen ones run out. Scores count only the drawn questions. Topics are normalised, so "4" and "Module 4: Client discovery" both become "Module 4".
- **Question import** (`app/Services/QuestionImporter.php`): .xlsx, .csv, .docx, .txt or pasted text. Instructors always see a preview first, and questions already in the quiz are skipped.
- **Payments:** a student is enrolled only after the payment provider confirms payment. `Checkout::fulfil()` is the single place that marks an order paid, counts coupon use and enrolls the student.

**Permissions:**
- `Course::canBeManagedBy()` allows admins and the course's own instructor. Every Studio action on a specific course, or on its chapters, lessons, questions or students, must check it (through `CourseController::authorizeCourse()`).
- Studio list pages (dashboard, sales, coupons) don't check one course; they show only the instructor's own courses (`StudioController::courseIds()`).
- Coupons belong to the person who created them (`created_by`), not to a course, because a coupon can apply to all courses. Only that person or an admin can turn a coupon on or off or delete it, and an instructor can only tie a coupon to their own courses.

**Tests:** `php artisan test` must pass before and after every change. Add a test for every new rule.

**Design system** (`resources/css/app.css`):
- Use the existing tokens (`ink`, `mist`, `line`, `pine`, `saffron`, `brick`) and component classes (`btn-*`, `input`, `panel`, `badge-*`, `table`). Don't introduce new colours or one-off styles.
- Schibsted Grotesk is the only typeface.
- The **curriculum path** in the lesson player is the signature element. Keep it consistent anywhere progress is shown.

**Copy style:**
- Sentence case, plain verbs, no all-caps labels.
- Buttons say exactly what they do.
- Errors say what happened and how to fix it.
- Write for travel advisors, using their vocabulary: suppliers, supplier partners, commission, clients, bookings, host agencies, consortia, travel professionals.
- Use TARC's encouraging, supportive tone ("Your travel business deserves structure, growth, and support"), and avoid TARC's all-caps buttons such as "GET STARTED".
- Use TARC's program names exactly as written: Recognized Travel Advisor (RTA) Certification Program, TA Launchpad, Travel Pro Community. Call units of a course "modules".
- For recognition, use only TARC's own wording, attributed to TARC: "recognized by IATA, CCRA and several host agencies". Don't add other accreditation or endorsement claims.

**Security:**
- All Markdown (lesson content and course descriptions) is rendered with HTML escaped and unsafe links (`javascript:` and similar) removed: `['html_input' => 'escape', 'allow_unsafe_links' => false]`.
- Downloads are served only to students allowed to open the lesson.
- Webhooks always re-check payment with the provider.
- Never trust prices, scores or answers sent from the browser.

## 4. Where we go further than Thinkific

These are the goals that justify building our own platform. They are not all built yet.

1. **Credentials built for the travel trade.**
   - Certificates and digital badges that suppliers, host agencies and clients can verify.
   - Continuing-education hours recorded per course.
   - Certificates that expire and prompt renewal for designations that need refreshing.
2. **Exam integrity worth trusting.**
   - Question banks with random draws per attempt.
   - Deadlines enforced on the server.
   - Attempt review by instructors.
   - Optional proctoring rules (one attempt at a time, time windows).
3. **Learning analytics that improve the courses.**
   - Results per question to find weak lessons (started).
   - Charts showing where students drop out.
   - Time spent per lesson.
   - Comparisons between groups of students.
4. **Advisor career path.**
   - Learning tracks that chain courses together (for example: foundations, then a specialty, then advanced).
   - Prerequisites between courses.
   - A profile page that lists everything an advisor has earned.
5. **Business tools inside the learning.**
   - Downloadable templates such as client intake forms and proposal templates.
   - Supplier spotlight lessons.
   - Practice scenarios with realistic client cases.
6. **Payments without lock-in.**
   - Stripe for USD and other card currencies.
   - Local options such as Chapa where TARC has advisors.
   - No platform fee on sales.
   - Team or agency seats so one agency can buy for its advisors.
7. **Connected to the rest of TARC.**
   - Single sign-on and member pricing for Travel Pro Community members.
   - TARC Expo sessions and TARC Talk episodes as on-demand lessons.
   - Lessons that link to the Travel Partner Directory and Business Builder Tools.
8. **Fast and accessible.** Light pages that work on slow connections and on phones, keyboard and screen-reader friendly, and progress that saves automatically.

## 5. Expected features and status

**Legend:** ✅ done · 🔁 done, needs rechecking (see section 6) · ⬜ not started

### TARC content
- ✅ TARC branding, links to the main site and support email in the header, footer and certificate
- ✅ Demo catalog built on TARC programs: RTA Certification Program, TA Launchpad Essentials (published), TA Launchpad Pro (draft) and the free Client Discovery Calls course
- ✅ RTA demo course follows TARC's real 13-module curriculum: lesson titles, $499 price, lessons 1.1 and 1.3 as free previews
- ⬜ RTA lesson videos and text from TARC. Most RTA lessons are marked "Lesson content coming soon"; only Modules 1, 8, 10 and 12 have sample text
- ⬜ Quizzes for RTA Modules 2–7, 9–11 and 13 (TARC ends every module with a quiz; the demo has quizzes only for Modules 1, 8 and 12)
- ⬜ Real TA Launchpad lesson content and prices (the demo uses $99 Essentials and $249 Pro as placeholders)
- ⬜ Paid mentoring add-on for RTA
- ⬜ "All sales are final" notice at checkout, which ties in with the refunds item under Checkout and sales
- ⬜ Travel Pro Community membership: single sign-on and member pricing
- ⬜ TARC Expo and TARC Talk as on-demand lessons

### Courses and content
- ✅ Courses with title, summary, Markdown description, image, level, price, web address, and draft or published status
- ✅ Chapters and lessons with drag-and-drop ordering, including moving lessons between chapters
- ✅ Lesson types: text, video (YouTube, Vimeo, MP4 link or upload), download, quiz, final exam
- ✅ Free preview lessons; attachments on any lesson
- ✅ Learning rules: lessons in order, drip release, access length, certificates on or off
- 🔁 Video upload (server limits and storage)
- ⬜ Rich-text editor instead of Markdown
- ⬜ Assignments with instructor grading, surveys
- ⬜ Learning tracks, bundles and prerequisites between courses
- ⬜ Import courses from Thinkific

### Quizzes and exams
- ✅ Question types: one answer, several answers, true/false, short answer; points and explanations per question
- ✅ Pass mark, attempt limit, time limit, shuffled questions and choices, option to show correct answers
- ✅ Timer in the browser, autosaved answers, automatic submission at zero, question jump bar, review page
- ✅ Per-question results report for instructors
- 🔁 Server-side enforcement of the time limit
- ✅ Question banks with random draws per attempt, balanced by topic, with unseen questions first on retakes
- ✅ Bulk import from Excel, CSV, Word or pasted text, with a preview and a downloadable template
- ⬜ Manual grading and instructor overrides (for example, granting an extra attempt)

### Checkout and sales
- ✅ Free enrollment, paid checkout, coupons (percent or fixed, per course or all courses, use limits, expiry)
- ✅ Built-in test payment mode
- 🔁 Stripe and Chapa integrations
- ⬜ Stripe webhook with signature verification. The shared route `/webhooks/{gateway}` already exists and Chapa uses it; Stripe still needs event parsing (`checkout.session.completed`) and `Stripe-Signature` verification
- ⬜ Refunds, invoices and receipts by email
- ⬜ Team or agency seats, subscriptions and memberships

### Progress and certificates
- ✅ Lesson completion, course percentage, resume where you left off, last activity
- ✅ Automatic certificates with a unique code, a printable layout and a public verification page
- 🔁 Certificate behaviour when a course changes after it was issued
- ⬜ PDF download, digital badges, continuing-education hours, expiry and renewal

### People and communication
- ✅ Registration, login, roles; admins change roles
- ✅ Manual enrollment by instructors
- 🔁 Manually enrolled new users can't sign in yet
- ⬜ Password reset
- ⬜ Email notifications: welcome, receipt, certificate issued, new drip lesson, reminders for inactive students
- ⬜ Student profile page listing everything they've earned
- ⬜ Community or discussion area

### Dashboards
- ✅ Student: courses with progress, certificates, purchases
- ✅ Instructor: revenue, students, completions, enrollment chart, student list, per-student history, quiz results, coupons, sales
- ✅ Admin: school totals, revenue by month, top courses, users, all courses, all orders
- ⬜ Drop-off analytics, time spent per lesson, data exports (CSV)

## 6. Done, but needs rechecking

| Item | What to check |
|---|---|
| **Exam time limit** | `QuizEngine::submit()` accepts answers sent after `expires_at`. The browser auto-submits, but a student who blocks scripts could submit late. Reject or truncate submissions after the deadline plus a short grace period, and grade the last autosaved answers instead. |
| **Stripe and Chapa** | Both are written to the providers' public APIs but have not been run against real accounts. Test end to end with sandbox keys: success, decline, cancel, and a student closing the tab before returning. Stripe confirms only when the student comes back to the return page, so a closed tab leaves the order pending until the Stripe webhook (section 5, Checkout and sales) is built. |
| **Certificates after course changes** | If lessons are added after a certificate is issued, progress drops below 100% but the certificate stays. Decide the policy and make it consistent. Deleting lessons doesn't recalculate progress either. |
| **Manual enrollment of new people** | Creating an account from the Students page sets a random password and sends nothing. Pair it with an invite email or password reset. |
| **Video uploads** | The app allows 500 MB, but PHP's `upload_max_filesize` and `post_max_size` must allow it. Files are stored on the local disk with no transcoding; consider cloud storage or a video host. |
| **Drip dates and time zones** | Release dates are calculated in the server's time zone. Confirm the time zone matches TARC's audience. |
| **Shuffled answer order** | Shuffled choices rely on a seeded random order per attempt. Confirm the order is stable when reloading and identical between taking and reviewing. |
| **Quiz report pass rate** | "Students passed" counts students with any passing attempt, divided by students who attempted. Confirm that's the figure instructors want. |
| **Accessibility** | Check keyboard use and screen readers on the quiz page, drag-and-drop reordering (it needs a keyboard alternative) and the certificate. |
| **Mobile Studio** | The student side is mobile-friendly; the Studio's tables and editors need a pass on phones. |
| **Search** | Course and user search use simple text matching, which is fine now but needs proper indexing as the catalog grows. |
