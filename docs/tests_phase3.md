## Phase 3 Test Notes

These tests are intended for quick manual verification of Phase 3 features.

### 1. Login and roles

- **Admin login**
  - Go to `/login.php`
  - Use: `alice.admin@example.org` / `changeme`
  - Expected: redirect to home, banner shows logged-in name and role.

- **Moderator login**
  - Use: `mark.moderator@example.org` / `changeme`

- **Author login**
  - Use: `adam.author@example.org` / `changeme`

### 2. Author upload and moderator approval

1. Log in as `adam.author@example.org`.
2. Visit `/author/upload_item.php`.
3. Submit a new item with:
   - Title: "Test Phase 3 Item"
   - File path: `/files/items/test-phase3.pdf`
4. Log out and log in as `mark.moderator@example.org`.
5. Visit `/moderator/approve_item.php`.
6. Approve the "Test Phase 3 Item".
7. Verify in DB:
   - `items.status_id` for the item maps to `item_statuses.code = 'approved'`.
   - Latest `item_versions` row has `approved_by` set to the moderator ID.

### 3. Download limit enforcement

Assuming the DB has been reset via `db/reset.sql`.

1. Log in as `mira.member@example.org` (has donations within the last year).
2. Call `/member/download.php?item_id=1` twice on the same day:
   - First request: Allowed (message indicates download permitted).
   - Second request: Denied with "Daily download limit reached".
3. Log in as `noah.member@example.org` (donation older than a year).
4. Call `/member/download.php?item_id=1` twice:
   - First request: Allowed.
   - Second request: Denied with "Weekly download limit reached".

### 4. Donation splits

1. Log in as `mira.member@example.org`.
2. Visit `/member/donate.php?item_id=1`.
3. Submit:
   - Amount: `10.00`
   - Charity: "Global Literacy Fund"
   - Percent to charity: `70`
4. Expected:
   - Success message indicating charity/CFP/author split (at least 60% to charity).
   - In `donations` table, a new row with:
     - `amount = 10.00`
     - `percent_charity >= 60`
     - `percent_charity + percent_cfp + percent_author = 100`.


