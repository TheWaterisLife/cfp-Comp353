## CFP Installation Guide (Stub)

This is a stub for the CopyForward Publishing (CFP) installation guide. It will be expanded in later phases.

### Prerequisites

- Linux server (or compatible environment)
- Apache HTTP Server
- MariaDB/MySQL
- PHP (with PDO MySQL extension)
- Git

### Basic Setup (to be refined)

1. **Clone the repository** (after you have pushed it to your remote):

   ```bash
   git clone <YOUR_REMOTE_URL> cfp
   cd cfp
   ```

2. **Create the database and user** in MariaDB/MySQL (exact commands will be added in Phase 1/2).

3. **Load schema and seed data** (scripts will be implemented in Phases 1–2):

   ```bash
   mysql -u root -p < db/schema.sql
   mysql -u root -p < db/seed.sql
   ```

4. **Configure Apache** to point the webroot to `src/public/` and ensure PHP is enabled.

5. **Configure database credentials** in `src/includes/db.php` (to be added in Phase 2).

6. **Run a PHP development server for quick testing (optional)**:

   ```bash
   cd src/public
   php -S 0.0.0.0:8000
   ```

Further details (virtual host configuration, environment variables, cron/stat scripts) will be documented as the project progresses through later phases.


