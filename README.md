## CopyForward Publishing (CFP)

CopyForward Publishing (CFP) is a LAMP-based web application (Linux/Apache/MariaDB/PHP) for managing open-access scholarly items, donations, committee workflows, and anti-plagiarism processes.

The project is implemented with modern, readable PHP (procedural or simple MVC), HTML, CSS, and vanilla JavaScript. Git is used for version control with feature branches per phase.

### Project Phases

- **Phase 0 — Kickoff & repo scaffold**
  - Git repo initialization, directory structure, base README/INSTALL, empty SQL files.
- **Phase 1 — Requirements → ERD → Relational schema**
  - ERD design, relational schema in `db/schema.sql`, 3NF notes in `docs/design_notes.md`.
- **Phase 2 — Database initialization + seed data**
  - `db/seed.sql`, reset instructions/script, DB connection helper `src/includes/db.php`.
- **Phase 3 — Basic backend & auth**
  - Authentication, role management, core CRUD endpoints and business rules.
- **Phase 4 — Frontend UI & UX**
  - Responsive UI, pages per role, AJAX interactions, screenshots.
- **Phase 5 — Business workflows & anti-plagiarism process**
  - Plagiarism reports, committee flows, voting, blacklist/suspension logic.
- **Phase 6 — Statistics, reporting, and simulated email**
  - Reporting pages, internal messaging, stats snapshots, CSV exports.
- **Phase 7 — Testing, documentation, demo script & packaging**
  - Tests, demo script, installation guide, final report, packaging script and tarball.

### Tech Stack

- **Backend**: PHP (LAMP stack, MariaDB/MySQL)
- **Frontend**: HTML, CSS, vanilla JavaScript
- **Database**: MariaDB/MySQL, normalized to at least 3NF
- **Version control**: Git, with per-phase branches and pull requests

### Repository Layout (high level)

- `db/` — schema and seed SQL scripts
- `src/` — PHP source (public webroot, includes, and role-specific modules)
- `assets/` — CSS, JS, and images
- `docs/` — ERD, design notes, demo script, reports, screenshots
- `scripts/` — helper scripts (e.g., packaging, stats) *(added in later phases)*

See `INSTALL.md` for setup instructions and `docs/design_notes.md` for database and workflow design details (to be expanded in later phases).


