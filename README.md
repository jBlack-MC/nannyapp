# Nanny-App

A three-role childcare booking marketplace connecting **parents** with verified **nannies**, moderated by an **admin**. Built with plain PHP 8 and MySQL — no framework, no Composer, no build step. Runs on XAMPP out of the box.

**Module:** WEDE6021 — Web Development

---
# Nanny-App – Childcare Booking and Management System

![Nanny-App Demo](assets/ex.gif)

# YOUTUBE 
![Nanny-App full test](https://youtu.be/nO85AqjW2E4)


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

---

## Features

### Parents

- Register and create a family profile
- Browse and search verified nannies (filter by location, rate, skills, experience, gender)
- 5-step booking wizard with date, time, duration, and child details
- View, track, and cancel bookings
- Manage child profiles (allergies, medical notes, special needs)
- Save favourite nannies
- Leave star ratings and written reviews after completed sessions
- Payment history
- In-app messaging with nannies
- Notification centre

### Nannies

- Register and build a profile (bio, rate, location, skills, languages, qualifications)
- Upload profile photo, banner image, and certification documents
- Set weekly availability
- Accept, reject, or mark bookings as complete
- View earnings breakdown by period
- Read and respond to reviews
- In-app messaging with parents
- Profile completeness indicator and trust score

### Admin

- Platform statistics dashboard (users, bookings, revenue, ratings)
- Nanny verification queue — approve or reject with document review
- User management — suspend, unsuspend, delete accounts
- Full booking and payment overview
- Support ticket management with status tracking
- Broadcast in-app notifications to all users
- Contact message inbox
- Database migration runners

### Platform

- Role-based access control enforced server-side on every page
- CSRF protection on all forms
- bcrypt password hashing
- Password reset via email token (one-use, 1-hour expiry)
- Dark mode toggle (persisted to `localStorage`)
- Flash message system with animated toasts
- PWA manifest and service worker (installable from Chrome)
- Responsive design with mobile navigation

---

## Tech Stack

| Layer | Technology |
|---|---|
| Server | PHP 8.x |
| Database | MySQL / MariaDB via PDO |
| Local environment | XAMPP (Apache + MySQL) |
| CSS | Modular design system in `assets/css/` (`variables.css`, `layout.css`, `navbar.css`, `components.css`, `pages.css`, `dashboard.css`, `responsive.css`) |
| CSS (landing page) | Bootstrap 3.4.1 (CDN) |
| JavaScript | Vanilla JS (`assets/js/app.js`) |
| Fonts | Google Fonts — Poppins, Inter |
| Icons | Font Awesome 4.7 (landing page only) |
| Email | PHP `mail()` — requires a local mail server or SMTP relay |
| File uploads | Native PHP `move_uploaded_file()` with MIME and size validation |
| PWA | `manifest.webmanifest` + `service-worker.js` |

---

## Folder Structure

```
nannyapp/
├── index.php                   Landing page (hero, nannies, testimonials, pricing)
├── account.php                 Account settings and password change
├── messages.php                In-app chat
├── notifications.php           Notification centre
├── support.php                 Support ticket submission
├── migrate_v2.php              V2 schema migration runner (admin-only)
├── migrate_v3.php              V3 schema migration runner (admin-only)
├── manifest.webmanifest        PWA manifest
├── service-worker.js           PWA offline shell
├── .htaccess                   Blocks config/, includes/, database/ from direct access
│
├── config/
│   ├── config.php              App constants, session start, error config
│   └── database.php            PDO singleton — call db() anywhere
│
├── database/
│   ├── schema.sql              Base schema + seed data (run first)
│   ├── migrate_v2.sql          Adds: children, saved_nannies, portfolio, page_content, indexes
│   └── migrate_v3.sql          Adds: support_tickets, password_resets, availability_slots, email_verified
│
├── includes/
│   ├── functions.php           Auth guards, CSRF, flash messages, file upload, email, notify helpers
│   ├── header.php              Shared HTML shell open + design-system CSS imports
│   ├── navbar.php              Shared global navigation (all roles)
│   ├── footer.php              Shared global footer
│   ├── scripts.php             Shared JS include block
│   └── sidebar.php             Shared dashboard sidebar
│
├── auth/
│   ├── register.php            Registration for parent and nanny roles
│   ├── login.php               Login with role-based redirect
│   ├── logout.php              Session destroy
│   ├── forgot.php              Password reset request
│   └── reset.php               Password reset via email token
│
├── parent/
│   ├── dashboard.php           Overview stats and recent bookings
│   ├── nannies.php             Browse and search verified nannies
│   ├── book.php                5-step booking wizard
│   ├── bookings.php            My bookings with cancel action
│   ├── children.php            Child profile management
│   ├── payments.php            Payment history
│   ├── review.php              Post-session review form
│   ├── saved.php               Saved nannies list
│   └── save_nanny.php          AJAX save/unsave endpoint
│
├── nanny/
│   ├── dashboard.php           Overview stats and verification status
│   ├── profile.php             Edit profile, upload photo and documents
│   ├── bookings.php            Accept, reject, and complete booking requests
│   ├── availability.php        Set weekly availability schedule
│   ├── earnings.php            Earnings breakdown and chart
│   └── reviews.php             Reviews received
│
├── admin/
│   ├── dashboard.php           Platform statistics and charts
│   ├── verifications.php       Nanny verification queue
│   ├── users.php               User management (suspend, delete)
│   ├── bookings.php            All bookings overview
│   ├── payments.php            All payments overview
│   ├── reports.php             Revenue and activity reports
│   ├── messages.php            Contact message inbox
│   ├── notify.php              Broadcast notifications to all users
│   └── support.php             Support ticket management
│
├── pages/
│   ├── about.php               About page
│   ├── contact.php             Contact form
│   ├── faq.php                 FAQ accordion
│   ├── pricing.php             Pricing plans
│   ├── safety.php              Safety and trust information
│   ├── resources.php           Resource articles
│   └── community.php           Community page
│
└── assets/
  ├── css/
  │   ├── variables.css       Design tokens (colors, spacing, radius, shadows)
  │   ├── layout.css          Containers, section spacing, grid primitives
  │   ├── navbar.css          Navbar-specific shared styles
  │   ├── components.css      Buttons, cards, badges, reusable UI components
  │   ├── pages.css           Home/marketing page section styles
  │   ├── dashboard.css       Role dashboard patterns and panels
  │   ├── responsive.css      Breakpoint overrides
  │   ├── reset.css           Base reset and normalization
  │   ├── forms.css           Form controls and validation states
  │   ├── animations.css      Shared transitions and reveals
  │   └── style.css           Legacy compatibility + consolidated theme rules
  ├── js/app.js               Front-end behaviour (vanilla JS)
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

Log in as admin and visit these URLs in order:
```
http://localhost/nannyapp/migrate_v2.php
http://localhost/nannyapp/migrate_v3.php
```
Each page runs once and confirms success. All features require both migrations.

**4. Configure (optional)**

Edit `config/config.php` if your database credentials differ from the XAMPP defaults:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'nanny_app');
define('DB_USER', 'root');
define('DB_PASS', '');          // XAMPP default is no password
define('APP_URL',  'http://localhost/nannyapp');
```

**5. Open the app**
```
http://localhost/nannyapp/
```

---

## Database Migrations

The schema is split into three files applied in sequence:

| File | What it adds |
|---|---|
| `database/schema.sql` | Core tables: users, nanny_profiles, parent_profiles, bookings, payments, chat_messages, reviews, notifications, contact_messages |
| `database/migrate_v2.sql` | children, saved_nannies, nanny_portfolio, nanny_availability, page_content, unique constraints, indexes |
| `database/migrate_v3.sql` | support_tickets, password_resets, email_verifications, availability_slots, booking_ref column, email_verified column |

Running the migrations a second time is safe — all statements use `IF NOT EXISTS` or `IF EXISTS` guards.

---

## Demo Accounts

**Password for all accounts:** `Password123!`

| Role | Email |
|---|---|
| Admin | admin@nanny.app |
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
  Book nannies (5-step wizard)
  Manage bookings (view, cancel)
  Manage child profiles
  Save favourite nannies
  Pay and view payment history
  Leave reviews after completed sessions
  Message nannies
  View notifications
  Edit account

NANNY
  Edit profile and upload documents
  Set weekly availability
  Accept, reject, or complete booking requests
  View earnings
  Read reviews
  Message parents
  View notifications
  Edit account

ADMIN
  Full read access to all platform data
  Verify and reject nanny applications
  Suspend, unsuspend, delete any user
  View all bookings and payments
  Manage support tickets
  Broadcast notifications
  Read contact form submissions
  Run database migrations
```

---

## Architecture Notes

**Page controller pattern.** Each URL maps to a single PHP file. Business logic and view code share the same file. No MVC, no framework, no routing engine.

**Shared layer:**

- `config/config.php` — bootstraps the app (constants, session, requires functions.php)
- `config/database.php` — PDO singleton accessed via `db()`
- `includes/functions.php` — all shared helpers: `current_user()`, `require_role()`, `require_login()`, `csrf_token()`, `csrf_field()`, `verify_csrf()`, `flash()`, `get_flashes()`, `redirect()`, `url()`, `notify()`, `send_email()`, `save_uploaded_image()`

**Two CSS systems coexist:**

- `index.php` (landing page) uses Bootstrap 3.4.1 from CDN with inline styles
- All other pages use `assets/css/style.css` (bespoke, 74 KB)

**Roles stored as ENUM** in the `users` table: `parent`, `nanny`, `admin`. Enforced on every page with `require_role('nanny')` etc.

**CSRF:** Token stored in `$_SESSION['csrf_token']`, compared with `hash_equals()` on every POST.

**File uploads:** Validated by MIME type whitelist and `getimagesize()`. Stored with a random `bin2hex(random_bytes(8))` filename under `assets/uploads/`.

---

## Known Limitations

| Area | Detail |
|---|---|
| Payments | No live payment gateway. Booking payment status is set to "paid" when the nanny marks a session complete. |
| Email | Uses native PHP `mail()`. Password reset and notification emails will not send without a configured mail server or SMTP relay (e.g. Mailtrap, Mailhog). |
| Real-time chat | Messages require a page reload to appear. No WebSocket or polling. |
| Booking conflicts | The system does not prevent a nanny from being double-booked at the same time. |
| Subscription plans | The R199 and R399 pricing plans shown on the pricing page are not enforced in the back-end. |
| Currency | Some pages still display `$` instead of `R`. |

---

## Roadmap

- [ ] Paystack payment gateway integration
- [ ] Email verification enforced at login
- [ ] Login rate limiting (brute-force protection)
- [ ] Booking conflict detection (no double-booking)
- [ ] Pagination on all admin list pages
- [ ] Real-time message polling
- [ ] Admin content management UI (`page_content` table)
- [ ] PDF support for document uploads
- [ ] Admin booking override and refund actions
- [ ] Privacy Policy and Terms of Service pages
- [ ] Unified CSS between landing page and inner app
- [ ] Replace seed data with South African locations

---

## Security Notes

- All SQL uses PDO prepared statements (`ATTR_EMULATE_PREPARES = false`)
- All output is escaped with `htmlspecialchars()`
- All POST forms include a CSRF token verified with `hash_equals()`
- Passwords are hashed with `password_hash(PASSWORD_DEFAULT)` (bcrypt)
- File uploads are validated for MIME type and stored with unpredictable filenames
- Protected directories (`config/`, `includes/`, `database/`) are blocked from direct access via `.htaccess`
- `display_errors` should be set to `0` in `config/config.php` before any public deployment
