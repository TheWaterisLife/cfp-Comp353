## CFP Changelog

### Phase 1 — ERD and Relational Schema

- Added full relational schema in `db/schema.sql` including:
  - Core entities: `members`, `authors`, `items`, `item_versions`, `downloads`, `donations`.
  - Supporting entities: `roles`, `member_statuses`, `item_statuses`, `discussion_statuses`, `vote_options`.
  - Workflow entities: `committees`, `committee_members`, `discussions`, `votes`, `comments`, `internal_messages`, `moderation_logs`.
  - Statistics entities: `daily_item_stats`, `daily_author_stats`.
- Documented assumptions, roles/permissions, and 3NF justification in `docs/design_notes.md`.
- Confirmed that `docs/ERD.png` should be generated from the schema using a diagram tool to keep the ERD in sync with `db/schema.sql`.


