## CFP Database Initialization Guide

Use this document whenever you need to create, reset, or reseed a CFP database on MySQL/MariaDB (local dev or ENCS).

### 1. Fresh database (after `db/schema.sql`)

```bash
# Create database and user (example)
mysql -u root -p -e "CREATE DATABASE cfp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Load schema
mysql -u root -p cfp < db/schema.sql

# Load canonical sample content WITHOUT truncation logic
mysql -u root -p cfp < db/sample_data.sql
```

`db/sample_data.sql` assumes the tables are empty (typically right after running `schema.sql`). It inserts lookup rows, members, items, committees, and demo statistics so that the UI works out of the box.

### 2. Resetting an existing CFP database

When you already have tables/data and want to reset to the canonical sample state:

```bash
mysql -u <user> -p <database_name> < db/seed.sql
```

`db/seed.sql` performs these steps:

1. Temporarily disables foreign-key checks.
2. Truncates every CFP table.
3. Re-enables foreign-key checks.
4. `SOURCE`s `db/sample_data.sql` to repopulate the same demo dataset described above.

This is the script referenced by `db/reset.sql` and by the ENCS deployment instructions.

### 3. Full drop / recreate (used by `db/reset.sql`)

```bash
mysql -u root -p < db/reset.sql
```

`db/reset.sql` drops the `cfp` database (if present), re-creates it, runs `schema.sql`, and finally runs `seed.sql`. Use this when you control the server and want the cleanest possible slate.

### 4. ENCS example (group `lv_comp353_2`)

```bash
ssh s_belmih@login.encs.concordia.ca
cd /groups/l/lv_comp353_2   # contains db/*.sql

mysql -h lvc353.encs.concordia.ca -u lvc353_2 -p lvc353_2 < schema.sql
mysql -h lvc353.encs.concordia.ca -u lvc353_2 -p lvc353_2 < sample_data.sql
# or reset:
mysql -h lvc353.encs.concordia.ca -u lvc353_2 -p lvc353_2 < seed.sql
```

Replace the username/database/password with the credentials assigned to your group. The same instructions work on any Linux host as long as the paths match your deployment layout.

### 5. When to use which script?

| Scenario | Script |
| --- | --- |
| Creating schema on a brand-new database | `db/schema.sql` |
| Populating tables without truncating existing data | `db/sample_data.sql` (only if tables are empty) |
| Wiping tables and loading standard demo data | `db/seed.sql` |
| Dropping/recreating the entire database | `db/reset.sql` |

When you add new tables or seed rows, keep both `db/sample_data.sql` and (by extension) `db/seed.sql` in sync so every environment shares the same baseline records.


