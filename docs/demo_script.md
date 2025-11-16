## CFP Demo Script (Approx. 25 minutes)

This script assumes the database has been reset via `db/reset.sql` and that seeded accounts exist.

### Accounts used

- Admin: `alice.admin@example.org` / `changeme`
- Moderators: `mark.moderator@example.org`, `mia.moderator@example.org` / `changeme`
- Authors: `adam.author@example.org`, `bea.author@example.org`, `carlos.author@example.org` / `changeme`
- Members: `mira.member@example.org`, `noah.member@example.org` / `changeme`

### 1. Admin overview (3 min)

1. Log in as admin (`alice.admin@example.org`).
2. Show `Admin > Members`: change a member’s role/status.
3. Show `Admin > Charities`: add a new charity.
4. Show `Admin > Committees`: list existing committees.

### 2. Author upload & moderator approval (5 min)

1. Log out, log in as `adam.author@example.org`.
2. Go to `Author > Upload item`, submit a new item (title + description + file path placeholder).
3. Log out, log in as `mark.moderator@example.org`.
4. Go to `Moderator > Pending approvals`, approve the new item.
5. Note that the item now appears as approved in search/home.

### 3. Member downloads and limits (4 min)

1. Log out, log in as `mira.member@example.org` (recent donor).
2. Search for the approved item and click `Download`; show confirmation.
3. Immediately try downloading another item; explain donors can download 1/day (logic enforced in `member/download.php`).
4. Optionally repeat as `noah.member@example.org` (non-recent donor) to highlight 1 download per 7 days.

### 4. Donations and splits (4 min)

1. Still as `mira.member@example.org`, go to an item detail page and click `Donate`.
2. Enter an amount (e.g., 10.00), choose a charity, and set charity percentage (≥60%).
3. Submit and show success message; explain the split (charity / CFP / author).
4. As admin, open `Admin > Statistics` and show donations by charity.

### 5. Committee discussion, plagiarism, and suspension (5–6 min)

1. Log in as `mark.moderator@example.org`.
2. Open `/moderator/plagiarism_report.php?item_id=4` to file a plagiarism report for the seeded training item.
3. Visit the corresponding discussion page; add a note.
4. Visit the voting page and cast votes as moderators to reach a ≥2/3 yes majority.
5. Show that the item is now blacklisted and no longer appears in public listings.
6. If the author has 3+ blacklisted items, demonstrate that their account status shows as suspended in `Admin > Members`.

### 6. Messaging and reports (3–4 min)

1. As moderator, send an internal message to the affected author explaining the decision.
2. Log in as that author; open `Messages` to show inbox and message content.
3. As admin, open `Admin > Statistics`:
   - Show top downloads and top authors.
   - Export a CSV (items/authors/donations) and briefly show the downloaded file.

### 7. Wrap-up (1–2 min)

- Summarize roles: admin, moderator, author, member.
- Recap key workflows: upload → approval → download/donate → committee review → blacklist/suspension → reporting.
- Note that additional tests and environment details are in `INSTALL.md`, `docs/tests_phase3.md`, and `docs/tests_phase5.md`.

