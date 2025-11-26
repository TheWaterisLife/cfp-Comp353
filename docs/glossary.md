## CFP Glossary

Key concepts referenced throughout the CopyForward Publishing (CFP) project.  
This list is derived from the COMP 353 specification and the implemented codebase.

### Actors and Roles
- **Member** – Core user account; owns contact info, login credentials, role, and status.
- **Author** – A member who publishes items; one-to-one extension with ORCID metadata.
- **Admin** – Member role that manages users, charities, committees, and system configuration.
- **Moderator** – Member role that reviews uploads, handles plagiarism cases, and casts votes.
- **Committee Member** – Member assigned to a specific committee (e.g., Plagiarism Review).

### Content and Workflow
- **Item** – Scholarly work submitted by an author; contains metadata, status, and versions.
- **Item Version** – Immutable snapshot of an item upload or revision awaiting approval.
- **Item Status** – Lookup code describing whether an item is draft, pending, approved, or blacklisted.
- **Discussion** – Thread that tracks plagiarism investigations and committee deliberations.
- **Vote** – Individual committee decision stored with a vote option (`yes`, `no`, `abstain`).
- **Moderation Log** – Audit entry capturing actions such as approvals, blacklists, and suspensions.

### Community Interactions
- **Download** – Record of a member retrieving an item, subject to quota rules.
- **Donation** – Monetary contribution tied to an item, enforcing ≥60% allocation to charities.
- **Charity** – Organization that receives part of each donation; curated by admins.
- **Internal Message** – Simulated email stored in the database for in-app notifications.
- **Comment** – Public feedback left by members on approved items they have downloaded.

### Governance
- **Committee** – Group of members assigned to review cases (e.g., plagiarism, appeals).
- **Discussion Status** – Lookup describing whether a committee discussion is open, closed, etc.
- **Vote Option** – Enumerated choice (`yes`, `no`, `abstain`) referenced by votes.
- **Member Status** – Lookup describing whether a member is active, suspended, or disabled.

### Operations
- **Auth Matrix** – Verification matrix stored with members for extra authentication challenges.
- **Daily Item Stats** – Pre-aggregated metrics for downloads/donations per day per item.
- **Daily Author Stats** – Aggregated metrics for author-level totals.
- **Reset Script** – SQL script (`db/reset.sql`) that rebuilds schema and seed data in sequence.

Future deliverables should extend this glossary when new domain concepts are introduced.


