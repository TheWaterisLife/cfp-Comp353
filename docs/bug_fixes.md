## CFP Bug & Fix Log

Lightweight record of notable defects discovered during integration and deployment.  
Use this to brief TAs during demos and to avoid regressions.

| Date (YYYY-MM-DD) | Area | Symptom / Root Cause | Fix | Verification |
| --- | --- | --- | --- | --- |
| 2025-11-26 | Deployment (public pages) | `require_once ../includes/...` failed after moving `src/public` files into `/www/groups/...` root. | Updated entrypoints (index/login/logout/register/search/item/item_comment) to include files via `__DIR__ . '/includes/...';`. | Loaded `https://lvc353.encs.concordia.ca/` without fatals; exercised login + search. |
| 2025-11-26 | Permissions | Apache could not read `includes/*.php` → `Permission denied`. | Applied `chmod 755 includes` and `chmod 644 includes/*.php` on ENCS webroot. | Refreshed site; helper includes loaded successfully. |
| 2025-11-26 | Database config | Placeholder credentials (`cfp_user` / `CHANGE_ME`) caused connection failure. | Overwrote `includes/db.php` with ENCS values (`lvc353.encs.concordia.ca`, `lvc353_2`, `itchywhale23`). | Home page established PDO connection; subsequent queries ran. |
| 2025-11-26 | Seed script | `seed.sql` tried to `USE cfp;`, which is not accessible. | Changed database qualifier to `lvc353_2` (or removed `USE` entirely). | Re-ran `mysql ... < seed.sql`; verified `SELECT COUNT(*) FROM items;` > 0. |
| 2025-11-26 | Schema state | Tables missing (`items` not found) after deploy. | Executed `schema.sql` + `seed.sql` on ENCS DB in correct order. | `SHOW TABLES;` listed all CFP tables; home page rendered featured items. |

Add further entries whenever a bug fix is completed (include date, scope, and validation).


