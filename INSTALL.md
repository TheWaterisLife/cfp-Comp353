## CFP Installation Guide

### Prerequisites

- Linux server (or compatible environment)
- Apache HTTP Server
- MariaDB/MySQL
- PHP (with PDO MySQL extension)
- Git

### Basic Setup

1. **Clone the repository** (after you have pushed it to your remote):

   ```bash
   git clone <YOUR_REMOTE_URL> cfp
   cd cfp
   ```

2. **Create the database and user** in MariaDB/MySQL (example):

   ```sql
   CREATE DATABASE cfp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'cfp_user'@'localhost' IDENTIFIED BY 'CHANGE_ME';
   GRANT ALL PRIVILEGES ON cfp.* TO 'cfp_user'@'localhost';
   FLUSH PRIVILEGES;
   ```

3. **Load schema and demo data** (choose the approach that fits your environment):

   ```bash
   # a) Full drop/recreate:
   mysql -u root -p < db/reset.sql

   # b) Manual sequence (fresh database):
   mysql -u root -p cfp < db/schema.sql
   mysql -u root -p cfp < db/sample_data.sql

   # c) Reset existing tables without dropping the DB:
   mysql -u root -p cfp < db/seed.sql
   ```

   See `docs/db_initialization.md` for more context and ENCS-specific examples.

4. **Configure Apache** to point the webroot to `src/public/` and ensure PHP is enabled. Requests such as `/admin/members.php` or `/member/download.php` are now served through lightweight wrappers under `src/public/`, so no extra aliases/symlinks are required—just keep the document root on `src/public`.

5. **Configure database credentials** in `src/includes/db.php`:

   - Set `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` to match the database you created.
   - Verify that a simple test page can connect using the provided PDO helper.

6. **Run a PHP development server for quick testing (optional)**:

   ```bash
   cd src/public
   php -S 0.0.0.0:8000
   ```

Further details (virtual host configuration, environment variables, cron/stat scripts) will be documented as the project progresses through later phases.

7. **Aggregate statistics (optional but recommended)**

   To populate `daily_item_stats` and `daily_author_stats`, run:

   ```bash
   php scripts/run_stats.php           # uses today
   php scripts/run_stats.php 2025-01-01
   ```

   In production, schedule this via `cron` (e.g., once per night).

