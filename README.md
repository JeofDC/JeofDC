# Aptitude Examination Web Application

A production-ready Online Aptitude Examination Web Application built in PHP 8.2+ and PostgreSQL 15+, styled using the **Mega UI Library** design language (Green primary `#28a745`, rounded cards, clean spacing, stat cards, responsive tables).

## Features

- **Role-Based Access Control (RBAC):** Permissions and role enforcement for Administrators, Examiners, and Candidates.
- **Three Core Aptitude Categories:**
  1. Abstract Reasoning (Image/pattern/sequence questions)
  2. Verbal Reasoning (Comprehension, vocabulary, analogies)
  3. Numerical Reasoning (Arithmetic, number series, ratios, word problems)
- **Question Bank Repository:** Search, filter by category/difficulty, dynamic choices, diagram uploader, and explanations.
- **Exam Builder & Configuration:** Define passing percentage, time limits, question randomization, choice shuffling, and max attempt limits.
- **Single & Bulk Exam Assignment:** Easily assign examinations to candidate accounts.
- **Candidate Portal & Distraction-Free Exam Interface:**
  - Instructions screen with required rule acknowledgment.
  - Live countdown timer with persistent expiration state.
  - Interactive question navigator grid (unanswered, answered, flagged).
  - Background AJAX auto-save on every answer click.
  - Anti-cheating browser focus/tab switch event logging.
  - Confirmation modals before exam start and submission.
- **Automatic Server-Side Scoring:** Score calculation, percentage, category performance breakdown, and pass/fail status computed inside PostgreSQL transactions.
- **Analytics & Reporting:** Category-level metrics, summary analytics, and CSV report export.
- **Audit Logging & Security:**
  - PDO prepared statements exclusively.
  - PHP session security and password hashing (`password_hash`).
  - CSRF protection for state-changing requests.
  - Strict input validation and HTML escaping (`htmlspecialchars`).

## Default Development Credentials

| Role | Username | Password | Email |
|------|----------|----------|-------|
| Administrator | `admin` | `Admin123!` | admin@aptitude.test |
| Examiner | `examiner` | `Examiner123!` | examiner@aptitude.test |
| Candidate | `candidate` | `Candidate123!` | candidate@aptitude.test |

*Note: Please change default credentials before deploying to a production environment.*

## Environment & Prerequisites

- PHP 8.2 or higher (with `pdo_pgsql` and `fileinfo` extensions enabled)
- PostgreSQL 15 or higher
- Web Server (Apache, Nginx, or PHP Built-in CLI Server)

## Quick Start / Local Installation

1. **Database Setup**
   Create a PostgreSQL database and user:
   ```bash
   sudo -u postgres psql -c "CREATE USER aptitude WITH PASSWORD 'aptitude_pass';"
   sudo -u postgres psql -c "CREATE DATABASE aptitude_db OWNER aptitude;"
   ```

2. **Initialize Database Schema & Seed Data**
   Run the seed script to create all tables and populate roles, sample questions, and default users:
   ```bash
   php database/seed.php
   ```

3. **Start Local Development Server**
   ```bash
   php -S 127.0.0.1:8000 -t public
   ```
   Access the application at `http://127.0.0.1:8000/login.php` or `http://127.0.0.1:8000/`.

## Application Architecture

```text
/
├── app/
│   ├── Controllers/
│   ├── Helpers/
│   │   ├── Auth.php           # RBAC permission checks & session management
│   │   ├── CSRF.php           # Token generation & verification
│   │   ├── Sanitize.php       # Output escaping & file upload validation
│   │   └── Audit.php          # Security audit trail logging
│   ├── Services/
│   │   └── ScoringService.php # Server-side score & percentage calculation
│   └── Views/
│       ├── header.php         # Mega UI header & navigation
│       └── footer.php         # Scripts & footer partials
├── config/
│   ├── app.php                # System constants & session settings
│   └── database.php           # PDO database connection manager
├── database/
│   ├── schema.sql             # PostgreSQL DDL schema definition
│   └── seed.php               # Data seeder for initial setup
├── public/
│   ├── api/                   # REST-style PHP API Endpoints
│   │   ├── auth/              # Login, logout, CSRF token
│   │   ├── users/             # User list and save endpoints
│   │   ├── questions/         # Question bank CRUD & upload
│   │   ├── categories/        # Aptitude categories CRUD
│   │   ├── exams/             # Exam builder, publish, assign
│   │   ├── attempts/          # Exam engine (start, current, save-answer, submit)
│   │   ├── results/           # Exam results and category scores
│   │   └── reports/           # Summary analytics
│   ├── assets/
│   │   ├── css/ui-library.css # Mega UI CSS Library
│   │   └── uploads/           # Diagram image uploads
│   ├── login.php
│   ├── dashboard.php
│   ├── candidate-dashboard.php
│   ├── exam-instructions.php
│   ├── exam-take.php
│   ├── result.php
│   ├── results.php
│   ├── questions.php
│   ├── categories.php
│   ├── exams.php
│   ├── assignments.php
│   ├── reports.php
│   └── audit.php
└── tests/
    └── test_aptitude_app.py   # Automated pytest integration test suite
```
