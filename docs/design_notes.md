## CFP Design Notes

This document will capture the evolving design of the CopyForward Publishing (CFP) system.

### Scope

- ERD and relational schema rationale
- Normalization (3NF) justification
- Business rules (downloads, donations, plagiarism handling)
- Architectural and implementation decisions

Details will be added starting in **Phase 1** when the ERD and schema are finalized, and updated in later phases as workflows and features are implemented.

---

## Phase 1 — ERD and Relational Schema

### High-level ERD (textual description)

- **members** — core identity table for all users; each member has one **role** (via `roles`), one **status** (via `member_statuses`), optional **introducer** (self-FK), optional ORCID, and authentication matrix info.
- **authors** — 1:1 extension of `members` for those who publish items.
- **items** — scholarly items uploaded by authors; linked to `authors`, with a `status` (`item_statuses`) and optional self-reference for version lineage.
- **item_versions** — 1:N versions per item, each with its own file path and approval info.
- **charities** and **donations** — donations link members (donors), items, and charities with explicit percentage splits.
- **downloads** — records each download of an item by a member.
- **committees** and **committee_members** — committees are groups of members; membership is a composite key (committee, member).
- **discussions**, **votes**, **comments** — support committee discussion/voting and public comments, with discussion/vote status tables (`discussion_statuses`, `vote_options`).
- **internal_messages** — internal messaging between members.
- **moderation_logs** — audit trail for moderation actions involving items and members.
- **daily_item_stats** and **daily_author_stats** — pre-aggregated statistics for future reporting phases.

The image file `docs/ERD.png` should be generated from this schema using a diagram tool (e.g., MySQL Workbench, Draw.io) and kept in sync with `db/schema.sql`.

### Roles and Permissions

Roles are stored in `roles` and referenced from `members.role_id`:

- **admin**
  - Manage members (CRUD), roles/lookups, charities, committees.
  - Access global statistics and configuration.
- **moderator**
  - Approve/reject/blacklist items (`items`, `item_versions`, `moderation_logs`).
  - Participate in committees, discussions, and votes.
  - Trigger suspensions/blacklisting flows.
- **author**
  - Create and manage own items (`items`, `item_versions`).
  - View download/donation statistics for their content.
  - Participate in discussions/committees when invited.
- **member**
  - Browse, download items (subject to limits), donate, comment.
  - Send/receive internal messages.

Fine-grained enforcement is handled at the application level using `members.role_id` and (later) a simple authorization helper.

### Key Design Assumptions

- Each member has exactly one primary role at a time (no many-to-many role mapping); if multi-role support is needed later, a `member_roles` junction table can be added.
- Authentication matrix data is stored as a single TEXT column (`members.auth_matrix`), treated as an atomic value at the relational level (e.g., JSON payload).
- Download and donation business rules (limits, minimum charity percentage) are enforced by the application and/or database constraints/triggers in later phases, not hard-coded into this schema.
- Items are authored by a single primary author (one `author_id` per item); co-authorship could be added later via a junction table `item_authors`.
- Statistics tables (`daily_item_stats`, `daily_author_stats`) hold derived data only and can be safely recomputed from base tables if necessary.

### Normalization / 3NF Justification

- **1NF**: All tables use atomic column values (no repeating groups, multi-valued attributes, or comma-separated lists). Lookup entities (roles, statuses, vote options) are separated into their own tables.
- **2NF**: For tables with composite keys (`committee_members`, `daily_item_stats`, `daily_author_stats`), all non-key attributes depend on the full key (e.g., `role` in `committee_members` depends on both committee and member together).
- **3NF**: Non-key attributes depend only on the key and not on other non-key attributes. Examples:
  - In `members`, attributes such as `name`, `org`, `primary_email`, `status_id`, and `role_id` all describe the member identified by `id`; role/status descriptions are factored out into `roles` and `member_statuses`.
  - In `items`, `title`, `description`, `status_id`, `author_id`, and `upload_date` all depend solely on `id`.
  - In `donations`, the percentage splits and amount are properties of the donation row identified by `id`, not derivable from any other non-key attribute.

Reference/enum-like domains are factored into separate tables (`roles`, `member_statuses`, `item_statuses`, `discussion_statuses`, `vote_options`), avoiding transitive dependencies within main entities.

### Limitations / Tooling Notes

- The ERD diagram file `docs/ERD.png` is currently a placeholder and should be regenerated using a database diagram tool based on `db/schema.sql` before submission.
- Any triggers (e.g., to enforce donation percentage sums) are deferred to a later phase to keep Phase 1 focused on core relational structure.

---

## Phase 5 — Plagiarism Workflow & Author Suspension

### Plagiarism Reporting and Committees

- Moderators can open a plagiarism report for any item using `src/moderator/plagiarism_report.php`. This creates a `discussions` row for the **Plagiarism Review Committee** (committee_id = 1 from the seed data) tied to the item.
- The discussion row stores a free-text summary of the concern in `discussions.content`. Additional comments from committee members are appended to the same field (separated by markers) via `plagiarism_discussion.php`, forming a simple text-based discussion thread without a separate posts table.

### Voting and 2/3 Majority Logic

- Committee members cast votes on the plagiarism discussion via `src/moderator/plagiarism_vote.php`, which writes to the `votes` table using `vote_options` (`yes`, `no`, `abstain`).
- For each discussion:
  - Let \(Y\) be the count of `yes` votes, \(N\) be the count of `no` votes, and ignore `abstain` for the majority calculation.
  - If \(Y + N > 0\) and \(Y / (Y + N) \ge 2/3\), the decision is **blacklist**.
  - If \(Y + N > 0\) and \(Y / (Y + N) \le 1/3\), the decision is **no_blacklist**.
  - Otherwise, the system reports **no_decision_yet** and allows further voting.

### Automatic Actions on Blacklist

When a 2/3 majority is reached in favour of blacklisting:

- The item’s status is updated to `item_statuses.code = 'blacklisted'`, which automatically removes it from public listings (home/search only show `approved` items).
- A `moderation_logs` row is inserted with action `blacklist_item` and a short detail message.
- An internal message is sent to the author (`internal_messages`) informing them that the item has been blacklisted.
- The system counts the number of blacklisted items for the author. If the author has **3 or more**:
  - The corresponding `members` row is updated to `member_statuses.code = 'suspended'`.
  - A `moderation_logs` row is added with action `suspend_author`.
  - An internal message is sent to the author indicating that their author account has been suspended.

### Appeal Process (Conceptual)

- The schema includes an `Appeals Committee` (committee_id = 2 in the seed data) intended to handle appeals from authors whose items were blacklisted.
- A follow‑up plagiarism appeal can be represented as a new `discussions` row for the appeals committee pointing to the same item. The same voting and 2/3‑majority logic can be reused to decide whether to uphold or overturn the blacklist.
- For simplicity in this prototype, reinstatement logic (changing `blacklisted` back to `approved` and potentially unsuspending authors) can be added in a later refinement of `plagiarism_vote.php` or a dedicated appeal handler.

### Simplifications and Limitations

- Discussion threads are stored as concatenated text in `discussions.content` instead of a normalized `discussion_posts` table. This keeps Phase 5 focused on workflow and business rules rather than UI‑heavy threading.
- The system assumes a single primary author per item. If co‑authorship is introduced later, the blacklist/suspension logic would need to be extended to consider all authors attached to an item.
- Appeal flows and unsuspension rules are intentionally simple in this phase and can be refined to reflect more nuanced policy (e.g., automatic reinstatement after successful appeals, temporary suspensions, etc.).

