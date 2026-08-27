# Nanny-App

A three-role childcare booking marketplace connecting **parents** with verified **nannies**, moderated by an **admin**. Built with plain PHP 8 and MySQL — no framework, no Composer, no build step. Runs on XAMPP out of the box. Also ships as an installable PWA and a Cordova-wrapped Android APK.

**Module:**XISD6329 — Work Integrated learning 3B

---
# Nanny-App – Childcare Booking and Management System

![Nanny-App Demo](assets/ex.gif)

# WATCH YOUTUBE 
(https://youtu.be/nO85AqjW2E4)


## Table of Contents

- [Features](#features)
- [Tech Stack](#tech-stack)
- [Folder Structure](#folder-structure)
- [Setup](#setup)
- [Database Migrations](#database-migrations)
- [Demo Accounts](#demo-accounts)
- [Role Capabilities](#role-capabilities)
- [Architecture Notes](#architecture-notes)
- [Known Limitations](#known-limitations)
- [Roadmap](#roadmap)
- [Security Notes](#security-notes)

---

## Features

### Parents

- Register and create a family profile
- Browse and search verified nannies (filter by location, rate, skills, experience, gender)
- 5-step booking wizard with date, time, duration, and child details — the same nanny can't be double-booked into an overlapping slot
- View and track bookings through every stage: pending → confirmed → nanny checked in → session done → confirmed & paid
- Get a one-time **check-in PIN** on acceptance — hand it to the nanny only once they've actually arrived; that's what unlocks their check-in
- **Confirm a completed session to release payment** to the nanny (or it auto-releases after 48 hours if you don't respond)
- **Report a problem** (e.g. a nanny never showed) to freeze the held payment for admin review instead of losing your money
- Cancel a booking any time before the nanny checks in — held payments are refunded automatically
- Manage child profiles (allergies, medical notes, special needs)
- Save favourite nannies
- Leave star ratings and written reviews after completed sessions
- Payment history showing charge status *and* escrow payout status
- In-app messaging with nannies — new messages appear live, no page reload
- Notification centre
- Edit account

### Nannies

- Register and build a profile (bio, rate, location, skills, languages, qualifications)
- Upload profile photo, banner image, and certification documents
- Set weekly availability
- Accept or reject booking requests
- **Check in with the PIN the parent gives you in person**, then **check out** when the session ends — this is what proves you were actually there before any money moves
- View earnings split into **held** (escrow, job not yet confirmed) and **released** (paid out), with a monthly chart
- Read and respond to reviews
- In-app messaging with parents
- Profile completeness indicator and trust score

### Admin

- Platform statistics dashboard (users, bookings, revenue, ratings)
- Own **admin profile** — access level, department, phone extension, internal notes
- Nanny verification queue — approve or reject with document review
- User management — suspend, unsuspend, delete accounts
- Full booking and payment overview, with **release/refund actions on held or disputed payments**
- Support ticket management with status tracking
- Broadcast in-app notifications to all users
- Contact message inbox
- Database migration runners
- **Web-only by design** — admin login and every `admin/*` page are blocked when the site is opened inside the packaged Android app or an installed PWA; a normal browser is required (see [Architecture Notes](#architecture-notes))

### Platform

- Role-based access control enforced server-side on every page
- **Escrow-style payments** — a nanny's payout is held from the moment a booking is accepted and only released once the parent confirms the session happened (or after a 48-hour safety-net window), never the instant the booking is accepted
- CSRF protection on all forms
- bcrypt password hashing
- Login rate limiting (IP + time window) and email verification enforced at login
- Password reset via email token (one-use, 1-hour expiry)
- Chat messages are rate-limited per sender to prevent spam/harassment
- Dark mode toggle that actually **persists across every page** — the choice is read from `localStorage` and applied before first paint, so it no longer resets to light mode on navigation
- Flash message system with animated toasts
- PWA manifest and service worker (installable from Chrome), **and** a Cordova-wrapped Android APK — see `apk/BUILD_APK.md`
- Responsive design with mobile navigation

---

## Tech Stack

| Layer | Technology |
|---|---|
| Server | PHP 8.x |
| Database | MySQL / MariaDB via PDO |
| Local environment | XAMPP (Apache + MySQL) |
| CSS | Single modular design system in `assets/css/` (`variables.css`, `reset.css`, `layout.css`, `navbar.css`, `components.css`, `forms.css`, `animations.css`, `responsive.css`, `pages.css`, `dashboard.css`, `style.css`) — shared by every page, including the landing page |
| JavaScript | Vanilla JS (`assets/js/app.js`), plus small inline scripts per page for AJAX (messages, save-nanny) |
| Fonts | Google Fonts — Manrope, Sora |
| Email | PHP `mail()` in production; on `localhost`/`127.0.0.1` it logs to `storage/email_logs/` instead of sending |
| File uploads | Native PHP `move_uploaded_file()` with MIME and size validation |
| PWA | `manifest.webmanifest` + `service-worker.js` (network-first, never caches `/admin/`) |
| Mobile app | Android APK via an Apache Cordova (or Capacitor) WebView wrapper around the live site — see `apk/BUILD_APK.md` |

---

## Folder Structure

```
nannyapp/
├── index.php                   Landing page (hero, nannies, testimonials, pricing)
├── account.php                 Account settings and password change
├── profile.php                 View another user's basic profile (redirects nannies to their full profile)
├── messages.php                In-app chat — conversation list + thread, live polling
├── messages_send.php           AJAX endpoint — send a chat message
├── messages_poll.php           AJAX endpoint — poll a thread for new messages, marks them read
├── notifications.php           Notification centre
├── support.php                 Support ticket submission
├── admin-unavailable.php       Shown when admin is opened inside the app/installed PWA
├── migrate_v2.php               V2 schema migration runner (admin-only)
├── migrate_v3.php               V3 schema migration runner (admin-only)
├── migrate_v4.php               V4 schema migration runner (admin-only) — admin profiles + payment escrow
├── migrate.php                  Generic catch-up migration runner (CLI, or admin token in a browser)
├── seed_demo.php                Optional South African demo data seeder (admin-only)
├── manifest.webmanifest        PWA manifest
├── service-worker.js           PWA offline shell
├── 404.php / 500.php           Error pages
├── .htaccess                   Blocks config/, includes/, database/ from direct access
│
├── apk/
│   ├── BUILD_APK.md            How to build the Android APK (Cordova or Capacitor)
│   └── config.xml              Cordova config — WebView target URL, permissions, app UA marker
│
├── config/
│   ├── config.php              App constants, session start, error config, kills stray admin sessions in the app
│   └── database.php            PDO singleton — call db() anywhere
│
├── database/
│   ├── schema.sql              Base schema + seed data (run first)
│   ├── migrate_v2.sql          Adds: children, saved_nannies, portfolio, page_content, indexes
│   ├── migrate_v3.sql          Adds: support_tickets, password_resets, availability_slots, email_verified
│   ├── migrate_v4.sql          Adds: admin_profiles table, booking check-in/dispute columns, payment payout_status
│   ├── phase1_constraints.sql  Optional: extra unique constraints + indexes
│   ├── phase2_authentication.sql  Optional: email verification / password reset columns (superseded by migrate_v3)
│   └── seed_reviews.sql        Optional: demo profile images, bookings and reviews
│
├── includes/
│   ├── functions.php           Auth guards, CSRF, flash messages, rate limiting, chat + notification + email helpers
│   ├── header.php               Shared HTML shell open, CSS imports, early dark-mode + PWA-detection scripts
│   ├── navbar.php               Shared global navigation (all roles)
│   ├── footer.php               Role-based footer loader
│   ├── footer-{admin,parent,nanny,guest}.php  Per-role footer content
│   ├── scripts.php              Shared JS include block
│   └── sidebar.php              Shared dashboard sidebar
│
├── auth/
│   ├── register.php            Registration for parent and nanny roles
│   ├── login.php               Login with role-based redirect; blocks admin credentials inside the app
│   ├── logout.php              Session destroy
│   ├── forgot.php              Password reset request
│   ├── reset.php               Password reset via email token
│   ├── verify-email.php        Email verification link handler
│   └── resend-verification.php Resend verification email
│
├── parent/
│   ├── dashboard.php           Overview stats and recent bookings
│   ├── nannies.php             Browse and search verified nannies
│   ├── book.php                5-step booking wizard
│   ├── bookings.php            My bookings — cancel, view check-in PIN, confirm & release payment, report a problem
│   ├── children.php            Child profile management
│   ├── payments.php            Payment history with escrow payout status
│   ├── review.php              Post-session review form
│   ├── saved.php               Saved nannies list
│   └── save_nanny.php          AJAX save/unsave endpoint
│
├── nanny/
│   ├── dashboard.php           Overview stats and verification status
│   ├── profile.php             Edit profile, upload photo and documents
│   ├── bookings.php            Accept/reject requests, check in with PIN, check out
│   ├── availability.php        Set weekly availability schedule
│   ├── earnings.php            Earnings breakdown (held vs released) and chart
│   └── reviews.php             Reviews received
│
├── admin/
│   ├── dashboard.php           Platform statistics and charts
│   ├── profile.php             Admin's own profile (access level, department, notes)
│   ├── verifications.php       Nanny verification queue
│   ├── users.php               User management (suspend, delete)
│   ├── user_profile.php        Detailed view of any user (role-aware: nanny/parent/admin panels)
│   ├── bookings.php            All bookings overview, filterable by status
│   ├── payments.php            All payments — release or refund held/disputed payments
│   ├── reports.php             Revenue and activity reports
│   ├── contacts.php            Public "Contact us" form submissions
│   ├── messages.php            Contact message inbox
│   ├── notify.php              Broadcast notifications to all users
│   └── support.php             Support ticket management
│
├── pages/
│   ├── about.php               About page
│   ├── contact.php             Contact form
│   ├── faq.php                 FAQ accordion
│   ├── pricing.php             Pricing plans
│   ├── safety.php               Safety and trust information
│   ├── resources.php            Resource articles
│   ├── community.php            Community page
│   ├── privacy.php               Privacy policy
│   └── terms.php                 Terms of service
│
└── assets/
  ├── css/
  │   ├── variables.css       Design tokens (colors, spacing, radius, shadows)
  │   ├── reset.css           Base reset and normalization
  │   ├── layout.css          Containers, section spacing, grid primitives
  │   ├── navbar.css          Navbar-specific shared styles
  │   ├── components.css      Buttons, cards, badges, reusable UI components
  │   ├── forms.css           Form controls and validation states
  │   ├── animations.css      Shared transitions and reveals
  │   ├── responsive.css      Breakpoint overrides
  │   ├── pages.css           Home/marketing page section styles
  │   ├── dashboard.css       Role dashboard patterns and panels
  │   └── style.css           Consolidated theme rules + dark mode (`[data-theme="dark"]`)
  ├── js/app.js               Front-end behaviour (vanilla JS) — nav, toasts, dark mode, PWA install
  ├── img/                    Logo, hero image, SVG avatars, icons
  └── uploads/                User-uploaded profile images and documents
```

---

## Setup

### Requirements

- XAMPP (or any Apache + PHP 8 + MySQL stack)
- PHP 8.0 or higher
- MySQL 5.7+ / MariaDB 10.4+

### Steps

**1. Copy files**
```
Place the nannyapp/ folder inside C:\xampp\htdocs\
```

**2. Create the database**

Option A — Command line:
```bash
mysql -u root < database/schema.sql
```

Option B — phpMyAdmin:
```
Open http://localhost/phpmyadmin
Import → select database/schema.sql → Go
```

**3. Run migrations**

Log in as admin and visit these URLs in order (or use the buttons on the admin dashboard):
```
http://localhost/nannyapp/migrate_v2.php
http://localhost/nannyapp/migrate_v3.php
http://localhost/nannyapp/migrate_v4.php
```
Each page runs once and confirms success. **All current features (admin profiles, check-in PINs, escrow payouts) require all three migrations.**

**4. Configure (optional)**

Edit `config/config.php` if your database credentials differ from the XAMPP defaults:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'nanny_app');
define('DB_USER', 'root');
define('DB_PASS', '');          // XAMPP default is no password
define('BASE_URL', '/nannyapp');
```

**5. Open the app**
```
http://localhost/nannyapp/
```

**6. (Optional) Build the mobile app**

The site is already an installable PWA — open it in Chrome on Android and choose *Install app*. To build a native Android APK instead, see `apk/BUILD_APK.md`. Note that the admin dashboard is intentionally unreachable from either the installed PWA or the packaged APK — that's enforced server-side, not just hidden in the UI.

---

## Database Migrations

The core schema is split into four files applied in sequence:

| File | What it adds |
|---|---|
| `database/schema.sql` | Core tables: users, nanny_profiles, parent_profiles, bookings, payments, chat_messages, reviews, notifications, contact_messages |
| `database/migrate_v2.sql` | children, saved_nannies, nanny_portfolio, nanny_availability, page_content, unique constraints, indexes |
| `database/migrate_v3.sql` | support_tickets, password_resets, email_verifications, availability_slots, booking_ref column, email_verified column |
| `database/migrate_v4.sql` | admin_profiles table; booking columns for check-in PIN, check-in/out timestamps, parent confirmation, dispute tracking; payment `payout_status` (held/released/refunded) |

Running the migrations a second time is safe — all statements use `IF NOT EXISTS` / `ADD COLUMN IF NOT EXISTS` guards, or `INSERT IGNORE`.

**Supplementary scripts** (optional, not required for core features): `database/phase1_constraints.sql` and `database/phase2_authentication.sql` add a subset of constraints/columns already covered by `migrate_v2`/`migrate_v3` — kept for reference. `database/seed_reviews.sql` and `seed_demo.php` add extra demo data (profile photos, sample bookings/reviews, South African seed data) purely for a fuller demo. `migrate.php` is a generic one-shot catch-up runner for older databases.

---

## Demo Accounts

**Password for all accounts:** `Password123!`

| Role | Email |
|---|---|
| Admin (super admin) | admin@nanny.app |
| Parent | parent@nanny.app |
| Nanny (verified) | amelia@nanny.app |
| Nanny (verified) | margaret@nanny.app |
| Nanny (pending) | jasmine@nanny.app |

> Remove or disable these accounts before any public deployment.

---

## Role Capabilities

```
GUEST
  Browse nannies (read-only)
  View landing page, pricing, FAQ, safety, contact

PARENT
  Book nannies (5-step wizard, conflict-checked)
  Manage bookings (view, cancel, confirm completion, report a problem)
  Share the check-in PIN with a nanny once they've arrived
  Manage child profiles
  Save favourite nannies
  Pay and view payment history (charge + payout status)
  Leave reviews after completed sessions
  Message nannies (live)
  View notifications
  Edit account

NANNY
  Edit profile and upload documents
  Set weekly availability
  Accept or reject booking requests
  Check in with the parent's PIN, then check out
  View earnings (held vs released)
  Read reviews
  Message parents (live)
  View notifications
  Edit account

ADMIN  (web browser only — blocked inside the app/installed PWA)
  Full read access to all platform data
  Edit own admin profile (access level, department, notes)
  Verify and reject nanny applications
  Suspend, unsuspend, delete any user
  View all bookings and payments; release or refund held/disputed payments
  Manage support tickets
  Broadcast notifications
  Read contact form submissions
  Run database migrations
```

---

## Architecture Notes

**Page controller pattern.** Each URL maps to a single PHP file. Business logic and view code share the same file. No MVC, no framework, no routing engine.

**Shared layer:**

- `config/config.php` — bootstraps the app (constants, session, requires functions.php); also kills any admin session detected inside the app shell
- `config/database.php` — PDO singleton accessed via `db()`
- `includes/functions.php` — shared helpers: `current_user()`, `require_role()`, `require_login()`, `csrf_token()`/`csrf_field()`/`verify_csrf()`, `flash()`/`get_flashes()`, `redirect()`, `url()`, `notify()`, `send_email()`, `save_uploaded_image()`, `is_rate_limited()`/`increment_rate_limit()`, `send_chat_message()`, `is_native_app_request()`, `auto_release_stale_payments()`

**Roles stored as ENUM** in the `users` table: `parent`, `nanny`, `admin`. Enforced on every page with `require_role('nanny')` etc.

**Escrow payment model.** A booking's `payments` row has both a charge `status` (pending/paid/failed/refunded) and a separate `payout_status` (held/released/refunded). Accepting a booking charges the parent and sets `payout_status='held'` — it does **not** pay the nanny. A 6-digit `check_in_code` is generated and shown only to the parent; the nanny must enter it (handed over in person) to flip the booking to `in_progress` and record `checked_in_at`. After the nanny checks out (`checked_out_at`), the parent confirms the session in `parent/bookings.php`, which sets `payout_status='released'`. If the parent never responds, `auto_release_stale_payments()` (called at the top of the booking/earnings/payments pages) releases it automatically 48 hours after checkout. A parent can instead flag a `disputed` booking, which freezes the payment for an admin to release or refund from `admin/payments.php`. Wrong check-in PINs are capped at 5 attempts (`check_in_attempts`); the parent can issue a fresh PIN at any time.

**Admin is web-only.** `is_native_app_request()` in `includes/functions.php` detects the packaged app two ways: a `NannyAppCordova` user-agent marker set via `apk/config.xml`'s `AppendUserAgent`, or a `na_app_shell` cookie set client-side (in `includes/header.php`) when `matchMedia('(display-mode: standalone)')` matches an installed PWA. `require_role()` refuses any page that requires the `admin` role when this is true (redirecting to `admin-unavailable.php`), `auth/login.php` refuses admin credentials outright, and `config.php` force-logs-out a stray admin session on any page load inside the app. `service-worker.js` also refuses to cache anything under `/admin/`.

**Live chat.** `messages.php` renders the initial thread server-side (works with JS disabled) and progressively enhances it: `messages_send.php` posts a message over AJAX and appends it instantly, while `messages_poll.php` is polled every 4 seconds (paused when the tab is hidden) to pick up incoming messages and mark them read. Both endpoints — and the plain form POST — funnel through the shared `send_chat_message()` helper so validation and the per-sender rate limit (30 messages / 5 minutes) can't drift between the two paths.

**Dark mode persistence.** The active theme is applied by an inline script at the very top of `includes/header.php`'s `<head>` — before any stylesheet loads — which reads `localStorage.na_theme` (falling back to the OS `prefers-color-scheme`) and sets `data-theme` on `<html>` immediately. This runs on every page load, so the theme no longer resets to light on navigation, and there's no flash of the wrong theme. `assets/js/app.js` only wires up the toggle button and persists the choice; it no longer overwrites the theme on load.

**CSRF:** Token stored in `$_SESSION['csrf']`, compared with `hash_equals()` on every POST.

**File uploads:** Validated by MIME type whitelist and `getimagesize()`. Stored with a random `bin2hex(random_bytes(8))` filename under `assets/uploads/`.

---

## Known Limitations

| Area | Detail |
|---|---|
| Payments | No live payment gateway is integrated — charges are simulated. What *is* real is the escrow/hold logic: money is only marked payable to the nanny after check-in, check-out and parent confirmation (or the 48-hour auto-release). |
| Email | Uses native PHP `mail()`. On `localhost`/`127.0.0.1` it logs to `storage/email_logs/` instead — password reset and notification emails won't actually send without a configured mail server or SMTP relay in production. |
| Real-time chat | Uses polling (every 4s while the tab is visible), not WebSockets — there's a small delay, not instant delivery. |
| Subscription plans | The pricing plans shown on the pricing page are not enforced in the back-end. |
| Legacy files | `includes/footer_admin.php` (underscore) is an unused duplicate of `footer-admin.php` (hyphen); `nanny-home.html` is an orphaned static prototype not linked from the app. Harmless but due for cleanup. |

---

## Roadmap

- [ ] Real payment gateway integration (e.g. Paystack) behind the existing escrow/hold logic
- [ ] Upgrade chat from polling to WebSockets for true real-time delivery
- [ ] Native push notifications for the packaged Android app
- [ ] Pagination on all admin list pages
- [ ] Admin content management UI (`page_content` table)
- [ ] PDF support for document uploads
- [ ] Live-updating unread badge in the navbar (currently updates on navigation)
- [ ] Unified CSS cleanup — remove the legacy duplicate footer file and orphaned prototype HTML
- [ ] Replace remaining demo data with South African locations throughout

---

## Security Notes

- All SQL uses PDO prepared statements (`ATTR_EMULATE_PREPARES = false`)
- All output is escaped with `htmlspecialchars()`
- All POST forms include a CSRF token verified with `hash_equals()`
- Passwords are hashed with `password_hash(PASSWORD_DEFAULT)` (bcrypt)
- Login attempts are rate-limited per IP; chat messages are rate-limited per sender
- File uploads are validated for MIME type and stored with unpredictable filenames
- Payments are held in escrow and released only after a check-in PIN (known only to the parent) and an explicit parent confirmation — a nanny can't get paid just by accepting a job
- The admin dashboard cannot be reached, and admin sessions cannot survive, inside the packaged app or an installed PWA — enforced in `require_role()`, `auth/login.php` and `config.php`, not just hidden in navigation
- Protected directories (`config/`, `includes/`, `database/`) are blocked from direct access via `.htaccess`
- `display_errors` should be set to `0` in `config/config.php` before any public deployment
