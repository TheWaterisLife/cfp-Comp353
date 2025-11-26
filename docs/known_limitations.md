## CFP Known Limitations

Summary of constraints to mention during demos and in the final report.

1. **File uploads are symbolic** – Items reference placeholder file paths; binary storage and virus scanning are not implemented.
2. **Email is simulated** – All notifications stay within the `internal_messages` UI; no SMTP or external delivery.
3. **Auth matrix is informational** – The matrix is stored with members but only basic session auth is enforced in this phase.
4. **No pagination on large lists** – Admin/member grids render all rows; performance could degrade with thousands of records.
5. **Plain-text search** – Search uses `LIKE` queries over title/description; no full-text indexes or advanced filters yet.
6. **Statistics refresh is manual** – `scripts/run_stats.php` must be run by cron or a maintainer; no automatic scheduler in the app.
7. **Single-author items** – Co-authorship is not modeled; extending the schema to `item_authors` is future work.
8. **Limited testing automation** – Test notes are manual; there are no PHPUnit or Cypress suites.
9. **Accessibility pass pending** – UI is keyboard-friendly but lacks full WCAG/ARIA verification.
10. **Appeals workflow minimal** – Appeals committee can reinstate items/unsuspend authors, but there is no dedicated UI for multi-step appeals or notes beyond the existing discussion/vote pages.

Document additional limitations here so stakeholders have clear expectations.


