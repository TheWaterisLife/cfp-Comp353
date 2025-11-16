## Phase 5 Test Notes (Plagiarism & Suspension)

These notes describe how to exercise the plagiarism workflow and automatic suspension logic.

### 1. Open a plagiarism report

1. Log in as a moderator (e.g., `mark.moderator@example.org` / `changeme`).
2. Visit `/moderator/plagiarism_report.php?item_id=4` to open a report for the seeded plagiarism training item.
3. Enter a summary and submit.
   - Expected: a new `discussions` row with `committee_id = 1` and `status_id` corresponding to `discussion_statuses.code = 'open'`.

### 2. Committee discussion

1. Visit `/moderator/plagiarism_discussion.php?id=<DISCUSSION_ID>` for the newly created discussion.
2. Add one or more notes via the inline form.
   - Expected: each new note is appended to `discussions.content` with a marker containing the moderator name and timestamp.

### 3. Voting and blacklist decision

1. From the discussion page, follow the link to `/moderator/plagiarism_vote.php?discussion_id=<DISCUSSION_ID>`.
2. As different moderator accounts, cast votes:
   - Example: two `yes` votes and one `no` vote → \( Y = 2, N = 1 \) → \( 2/3 \) yes.
3. After votes:
   - `votes` table contains one row per `(discussion_id, voter_id)`.
   - Tally shows the counts and indicates **Decision: Item blacklisted**.
4. In the database:
   - The item’s `status_id` now refers to `item_statuses.code = 'blacklisted'`.
   - A row is added to `moderation_logs` with `action = 'blacklist_item'`.
   - An `internal_messages` row is created to notify the author.

### 4. Automatic author suspension

1. Ensure the author has at least three items blacklisted (seed data already contains one; additional blacklisting can be triggered via the same flow).
2. When the third item is blacklisted for the same author:
   - `members.status_id` for that author is updated to `member_statuses.code = 'suspended'`.
   - A `moderation_logs` row is created with `action = 'suspend_author'`.
   - An internal message is sent informing the author of the suspension.


