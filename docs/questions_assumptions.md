## CFP Questions & Assumptions

This document captures clarifications requested from the COMP 353 specification and the assumptions our team adopted while implementing CFP.

### Clarified Questions
1. **Are authors a separate table or a specialization of members?**  
   → Authors are a one-to-one extension of members; each author row references the member primary key and stores ORCID metadata.

2. **How are plagiarism investigations initiated?**  
   → Moderators open a discussion tied to the Plagiarism Review Committee using `plagiarism_report.php`. The discussion ID then drives voting and logging.

3. **What qualifies a member for the “recent donor” download bonus?**  
   → Donations within the past 365 days unlock the 1-download-per-day quota; otherwise members are limited to one download per seven days.

4. **How are internal emails simulated?**  
   → The `internal_messages` table and UI act as the email inbox/outbox. No SMTP integration is required for the demo.

5. **Who can manage charities and committees?**  
   → Only admins can CRUD charities and committees; moderators consume the data but cannot mutate it.

### Implementation Assumptions
1. **Single primary author per item** – Co-author support can be modeled later via a junction table if needed.
2. **File storage is symbolic** – Uploads reference a file path or placeholder string; binary storage is out of scope.
3. **Donation currency** – All monetary amounts are stored as CAD in `DECIMAL(10,2)` columns.
4. **Matrix verification** – Auth matrix values are stored as TEXT and validated at the application layer when used.
5. **Statistics refresh** – `scripts/run_stats.php` can be scheduled nightly; on-demand generation is adequate for the prototype.
6. **Appeals workflow** – Documented conceptually in design notes; implementation defers to future work.

Add new questions/assumptions here whenever the specification leaves room for interpretation.


