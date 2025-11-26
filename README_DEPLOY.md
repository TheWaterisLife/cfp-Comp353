## CFP Deployment Guide (COMP 353 – Group 2)

This document explains how the CFP project is deployed on the COMP 353 lab server using the two official directories:

- **Working / SQL directory**: `/groups/l/lv_comp353_2`
- **Web root directory**: `/www/groups/l/lv_comp353_2`

All paths and scripts below are tailored to the account **`lvc353_2`** and database **`lvc353_2`** on host **`lvc353.encs.concordia.ca`**.

---

### 1. Project Structure on the Server

After running `deploy.sh`, the layout on the server is:

- **Working directory**: `/groups/l/lv_comp353_2`
  - `db/` – SQL schema and data (synced from repo `db/`)
  - `scripts/` – utility scripts (synced from repo `scripts/`)
  - `docs/` – documentation (synced from repo `docs/`)
  - `*.md` – top-level markdown files (README, INSTALL, README_DEPLOY, etc.)

- **Web root**: `/www/groups/l/lv_comp353_2`
  - `src/` – PHP backend (synced from repo `src/`)
    - `public/` – public entry points (e.g., `index.php`, `login.php`, `search.php`)
    - `includes/` – shared helpers, including DB and auth (`db.php`, `auth.php`, etc.)
    - role-specific modules under `admin/`, `member/`, `moderator/`, `author/`
  - `assets/` – static files (synced from repo `assets/`)
    - `css/` – e.g., `main.css`
    - `js/` – e.g., `main.js`

Web paths are assumed to be rooted under:

- **Base URL**: `https://lvc353.encs.concordia.ca/lv_comp353_2`
  - Example URL to the main page:  
    `https://lvc353.encs.concordia.ca/lv_comp353_2/src/public/index.php`
  - Example asset URL:  
    `https://lvc353.encs.concordia.ca/lv_comp353_2/assets/css/main.css`

The `deploy.sh` script can be adjusted if the base URL is different (via `WEB_BASE_PATH` and `WEB_ROOT_URL`).

---

### 2. One-Time Setup on the Server

1. **Upload or clone the repo** into a private location in your account (e.g., `~/cfp` or `/groups/l/lv_comp353_2/cfp`).
2. Copy the deployment scripts into the repo root if they are not already there:
   - `deploy.sh`
   - `import_db.sh`
   - `test_web.sh`
   - `README_DEPLOY.md`
3. Make the scripts executable:

   ```bash
   chmod +x deploy.sh import_db.sh test_web.sh
   ```

No Composer or Node build steps are required for the PHP backend; the app is plain PHP + HTML + CSS + vanilla JS.

---

### 3. Deploying / Redeploying the Application

From the **repo root** on the server (where `deploy.sh` lives):

```bash
./deploy.sh
```

What `deploy.sh` does:

- **Creates directories** if missing:
  - `/www/groups/l/lv_comp353_2/src`
  - `/www/groups/l/lv_comp353_2/assets`
  - `/groups/l/lv_comp353_2/db`
  - `/groups/l/lv_comp353_2/scripts`
  - `/groups/l/lv_comp353_2/docs`
- **Syncs files** from the repo into those locations:
  - `src/` → `/www/groups/l/lv_comp353_2/src`
  - `assets/` → `/www/groups/l/lv_comp353_2/assets`
  - `db/` → `/groups/l/lv_comp353_2/db`
  - `scripts/` → `/groups/l/lv_comp353_2/scripts`
  - `docs/` → `/groups/l/lv_comp353_2/docs`
  - any top-level `*.md` → `/groups/l/lv_comp353_2`
- **Updates DB configuration** in `/www/groups/l/lv_comp353_2/src/includes/db.php`:
  - `DB_HOST = 'lvc353.encs.concordia.ca'`
  - `DB_NAME = 'lvc353_2'`
  - `DB_USER = 'lvc353_2'`
  - `DB_PASS = 'itchywhale23'`
- **Adjusts asset and src paths** inside PHP/HTML files under `src/`:
  - Rewrites references like `"/assets/...` to  
    `"/lv_comp353_2/assets/...` (via `WEB_BASE_PATH`, default `/lv_comp353_2`).
- **Fixes permissions** for an Apache/PHP environment:
  - Directories under `/www/groups/l/lv_comp353_2`: `755`
  - Files under `/www/groups/l/lv_comp353_2`: `644`
  - Removes group/other write from the web root.

You can override defaults when running:

```bash
WEB_BASE_PATH="/lv_comp353_2" \
WORK_DIR="/groups/l/lv_comp353_2" \
WEB_ROOT="/www/groups/l/lv_comp353_2" \
DB_HOST="lvc353.encs.concordia.ca" \
DB_NAME="lvc353_2" \
DB_USER="lvc353_2" \
DB_PASS="itchywhale23" \
./deploy.sh
```

---

### 4. Importing / Updating the Database

After deployment (so that `/groups/l/lv_comp353_2/db` has the SQL files), run:

```bash
./import_db.sh
```

What `import_db.sh` does:

- Looks for SQL files in: `/groups/l/lv_comp353_2/db`
- Connects using the `mysql` CLI with:
  - Host: `lvc353.encs.concordia.ca`
  - DB: `lvc353_2`
  - User: `lvc353_2`
  - Password: `itchywhale23`
- Imports SQL files in the following order (if present):
  1. `reset.sql`
  2. `schema.sql`
  3. `seed.sql`
  4. Any other `*.sql` in alphabetical order

You can override credentials if needed:

```bash
DB_NAME="lvc353_2" DB_USER="lvc353_2" DB_PASS="itchywhale23" ./import_db.sh
```

To **update** the database after editing schema or seed scripts:

1. Update the SQL files in your repo (`db/schema.sql`, `db/seed.sql`, etc.).
2. Redeploy:

   ```bash
   ./deploy.sh
   ```

3. Re-import:

   ```bash
   ./import_db.sh
   ```

---

### 5. Testing the Deployment

Run:

```bash
./test_web.sh
```

What `test_web.sh` checks:

1. **Required directories exist**:
   - `/www/groups/l/lv_comp353_2`
   - `/www/groups/l/lv_comp353_2/src`
   - `/www/groups/l/lv_comp353_2/src/public`
   - `/www/groups/l/lv_comp353_2/assets`
   - `/groups/l/lv_comp353_2`
   - `/groups/l/lv_comp353_2/db`
2. **HTTP checks** (using `curl`):
   - `https://lvc353.encs.concordia.ca/lv_comp353_2/assets/css/main.css`
   - `https://lvc353.encs.concordia.ca/lv_comp353_2/src/public/index.php`
3. **PHP + DB connectivity**:
   - Creates a small probe script:  
     `/www/groups/l/lv_comp353_2/src/public/__cfp_test_db.php`
   - Calls it via HTTP (or via `php` directly if `curl` is missing).
   - The script uses `src/includes/db.php` and `cfp_get_pdo()` to ensure:
     - PHP runs correctly.
     - The database connection works (`SELECT 1 AS ok` returns successfully).

You can override the public URL:

```bash
WEB_ROOT_URL="https://lvc353.encs.concordia.ca/lv_comp353_2" ./test_web.sh
```

---

### 6. PHP Dependencies / Extensions

The project is plain PHP (no Composer-based dependencies). The required PHP features/extensions are:

- **PDO + MySQL driver**:
  - `pdo`
  - `pdo_mysql`
  - Used for all DB access via `src/includes/db.php` (PDO).
- **Sessions**:
  - Standard PHP session support (used in `src/includes/auth.php` and login flows).
- **JSON**:
  - `json` extension (used in some request/response helpers and potential future APIs).
- **mbstring** (recommended):
  - For multibyte-safe string operations where applicable.

Typical Apache/PHP setups on the lab servers should already have these enabled, but if anything fails, first check:

```bash
php -m
```

and ensure `pdo_mysql` is present.

There is **no `composer.json`** in the project, so **no Composer install steps are required**.

---

### 7. Common URLs

Assuming the base URL:

- `https://lvc353.encs.concordia.ca/lv_comp353_2`

Key endpoints:

- Main landing page:  
  `https://lvc353.encs.concordia.ca/lv_comp353_2/src/public/index.php`
- Login:  
  `https://lvc353.encs.concordia.ca/lv_comp353_2/src/public/login.php`
- Search:  
  `https://lvc353.encs.concordia.ca/lv_comp353_2/src/public/search.php`
- Example asset:  
  `https://lvc353.encs.concordia.ca/lv_comp353_2/assets/css/main.css`

If your instructor provides a different URL prefix, update:

- `WEB_BASE_PATH` in `deploy.sh`
- `WEB_ROOT_URL` in `test_web.sh`

and redeploy.

---

### 8. Quick Redeploy Checklist

1. Pull latest changes or update your local repo.
2. Upload/sync the repo to the server (if working from local).
3. On the server, from the repo root:
   - `./deploy.sh`
   - `./import_db.sh`
   - `./test_web.sh`
4. Paste the main app URL and test in a browser.


