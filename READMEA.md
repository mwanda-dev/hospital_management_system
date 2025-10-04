READMEA - Hospital Management System (HMS)

Purpose
-------
This document provides comprehensive software engineering and software quality assurance (SQA) documentation for the Hospital Management System (HMS) repository. It is intended for developers, QA engineers, DevOps, and project stakeholders who need to understand architecture, setup, testing, quality gates, release criteria, and operational runbook.

Repository layout
-----------------
Root files:
- `appointments.php`, `billing.php`, `index.php`, `inventory.php`, `lab_tests.php`, `medical_records.php`, `patients.php`, `profile.php`, `reports.php`, `settings.php`, `users.php`, `view-all.php`, `wards.php` — top-level app pages.
- `ajax/` — server endpoints used by UI via AJAX (e.g., `get_invoice.php`, `process_insurance_payment.php`, `search_patient_location.php`).
- `database/hospital_management.sql` — database schema and seed data.
- `functions/` — PHP helper modules (e.g., `appointments_functions.php`, `patients_functions.php`, `ward_functions.php`).
- `includes/` — shared include files (e.g., `auth.php`, `config.php`, `header.php`, `footer.php`, `login.php`, `logout.php`, `sidebar.php`).
- `patients/` — patient-facing pages (e.g., `patientappointments.php`, `patientbilling.php`, etc.).

High-level architecture
-----------------------
- Monolithic PHP application that renders server-side pages and serves AJAX endpoints.
- Data persistence: MySQL (schema provided in `database/hospital_management.sql`).
- Session/authentication: Likely PHP session-based (see `includes/auth.php`).
- UI: Classic server-rendered HTML with some JavaScript making AJAX calls to `ajax/` endpoints.

Assumptions
-----------
1. Application runs on a LAMP/WAMP stack (PHP 7.x/8.x, Apache, MySQL/MariaDB).
2. `config.php` contains DB connection and application configuration; it must be protected and not checked into public VCS.
3. No modern framework is used (plain PHP files and includes); tests and CI must be adapted to this style.

Environment setup
-----------------
Prerequisites
- Windows: WAMP/XAMPP or Linux: LAMP
- PHP 7.4+ or 8.x
- MySQL/MariaDB
- Git

Local setup steps
1. Place the repository in your web root, e.g., `c:\wamp64\www\hms`.
2. Create a MySQL database (e.g., `hms_db`) and import `database/hospital_management.sql`.
3. Copy `includes/config.sample.php` to `includes/config.php` (if sample exists) and set DB credentials and app settings.
4. Start WAMP/XAMPP and visit `http://localhost/hms/index.php`.

Security notes for setup
- Ensure `includes/config.php` is not accessible from the web (move it above webroot if possible).
- Use strong DB user passwords and restrict DB user to the HMS database.

Database
--------
- Primary database file: `database/hospital_management.sql`.
- Expected tables: patients, appointments, billing/payments, users/roles, wards, inventory, lab_tests, medical_records.
- Migration strategy: currently SQL dump. For production, adopt a migration tool (Phinx, Flyway, or Laravel migrations).

Coding standards & conventions
-----------------------------
- Follow PSR-12 for PHP where practical (naming, spacing, visibility).
- Use `functions/` directory for reusable code and avoid repeated inline SQL queries in page controllers.
- Sanitize all user inputs using prepared statements (PDO or mysqli with prepared queries).
- Prefer a single DB connection file in `includes/config.php` and reuse it.

Testing strategy (SQA)
----------------------
Testing goals
- Ensure critical flows work: user login/logout, patient create/read/update, appointment scheduling, billing/invoice generation, lab test recording.
- Validate data integrity, authentication, and authorization.

Types of testing
1. Unit tests: For pure PHP functions in `functions/` using PHPUnit. Add tests for input validation, date handling, currency calculations.
2. Integration tests: Test DB interactions and controllers. Use a test database and reset state between runs.
3. End-to-end tests: Use Selenium / Playwright to exercise UI flows (login, create patient, book appointment, process payment).
4. Security tests: Basic OWASP Top 10 checks (SQLi, XSS, CSRF). Automated scanners (OWASP ZAP) recommended.
5. Manual acceptance tests: Documented in the Acceptance Criteria section.

Test environment
- Use a CI job to spin up a test DB (MySQL) and run tests. For Windows developers, use GitHub Actions for cross-platform CI.

Sample test cases (high priority)
- Login with valid credentials -> success and session established.
- Login with invalid credentials -> error message, no session.
- Create patient with complete data -> record inserted, accessible via `patients.php`.
- Book appointment for a patient -> appointment appears in `appointments.php` and patient view.
- Generate invoice for visit -> `ajax/get_invoice.php` returns correct totals.
- Process insurance payment -> `ajax/process_insurance_payment.php` updates billing status.

Acceptance criteria
- All high-priority test cases pass in CI.
- No PHP fatal errors or uncaught exceptions during critical flows.
- No SQL injection or XSS vulnerabilities in tested pages.
- Performance: Page load time for primary flows under 1s on staging hardware similar to production.

Quality gates & CI
------------------
Suggested CI pipeline (GitHub Actions / GitLab CI):
1. Checkout code
2. Setup PHP, composer
3. Setup MySQL service and import `hospital_management.sql`
4. Run PHP lint (`php -l`) and static analysis (PHPStan)
5. Run unit tests (`phpunit`)
6. Run integration tests
7. Run basic security scan (OWASP ZAP)

Release & deployment
--------------------
- Deployment via rsync/FTP or automated CI/CD pipeline.
- Maintain migrations for DB changes; back up DB before production deploys.
- Tag releases semantically and note database migrations in the release notes.

Monitoring & logging
--------------------
- Log PHP errors to file (php.ini error_log) and rotate logs.
- Use application-level logging for critical billing/audit events.
- Monitor server metrics (CPU, RAM, disk, DB connections), and configure alerts.

Backup & recovery
-----------------
- Daily DB backups; weekly full file backups.
- Test restore procedure quarterly.
- Keep backups for at least 30 days; encrypt and secure offsite.

Security and privacy
--------------------
- Enforce least privilege for DB users.
- Use HTTPS in production.
- Protect PHI according to local regulations (HIPAA if in US) — data encryption at rest and in transit, strict access controls, audit logs.

Known limitations & technical debt
---------------------------------
- No modern framework; harder to scale/maintain.
- No automated migrations (SQL dump only).
- Likely missing unit tests and CI configuration.

Next steps & recommendations
---------------------------
1. Add `composer.json` and introduce autoloading and minimal dependencies.
2. Add PHPUnit and create `tests/` directory with unit tests for `functions/`.
3. Add GitHub Actions workflow to run lint/tests on PRs.
4. Implement prepared statements and central DB connection wrapper.

Documentation & artifacts
-------------------------
- This file: `READMEA.md` (project-level SQA and engineering documentation).
- Add `CONTRIBUTING.md`, `SECURITY.md`, and `CHANGELOG.md`.
- Add `docs/` folder for sequence diagrams, ER diagrams, and runbooks.

Contact and maintainers
-----------------------
- Primary repo owner: `mwanda-dev` (see repository settings for contact).

Appendix: checklist for QA engineers
-----------------------------------
- [ ] Setup local environment and import DB
- [ ] Verify login/logout flows
- [ ] Run and document manual test cases
- [ ] Run automated unit tests
- [ ] Run security scan and document findings

