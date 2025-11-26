-- CFP Sample Data
-- Load this after running db/schema.sql and selecting the target database:
--   mysql -u root -p cfp < db/sample_data.sql
-- or
--   mysql -u user -p your_db < db/sample_data.sql
--
-- This script does not truncate tables. Use db/seed.sql if you need a full reset.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- ---------------------------------------------------------------------------
-- Lookup data
-- ---------------------------------------------------------------------------

INSERT INTO roles (id, name, description) VALUES
  (1, 'admin', 'System administrator'),
  (2, 'moderator', 'Content moderator / committee member'),
  (3, 'author', 'Content author'),
  (4, 'member', 'General member / reader');

INSERT INTO member_statuses (id, code, description) VALUES
  (1, 'active', 'Active member'),
  (2, 'suspended', 'Suspended due to policy violations'),
  (3, 'pending', 'Awaiting approval/verification'),
  (4, 'disabled', 'Manually disabled account');

INSERT INTO item_statuses (id, code, description) VALUES
  (1, 'draft', 'Draft, not yet submitted'),
  (2, 'pending_review', 'Awaiting moderator review'),
  (3, 'approved', 'Approved and visible to members'),
  (4, 'rejected', 'Rejected by moderators'),
  (5, 'blacklisted', 'Blacklisted due to plagiarism or policy');

INSERT INTO discussion_statuses (id, code, description) VALUES
  (1, 'open', 'Open for discussion and voting'),
  (2, 'closed', 'Closed after decision'),
  (3, 'archived', 'Archived for reference');

INSERT INTO vote_options (id, code, description) VALUES
  (1, 'yes', 'Yes / in favour'),
  (2, 'no', 'No / against'),
  (3, 'abstain', 'Abstain from voting');

-- ---------------------------------------------------------------------------
-- Members and authors
-- ---------------------------------------------------------------------------

INSERT INTO members (
    id, introducer_id, name, org, address,
    primary_email, recovery_email, password_hash,
    auth_matrix, matrix_expiry, orcid,
    role_id, status_id, created_at, updated_at
) VALUES
  (1, NULL, 'Alice Admin', 'CFP HQ', '123 Admin St, City',
   'alice.admin@example.org', 'alice.admin+recovery@example.org', 'changeme',
   'matrix-admin', DATE_ADD(NOW(), INTERVAL 1 YEAR), NULL,
   1, 1, NOW(), NOW()),

  (2, 1, 'Mark Moderator', 'CFP HQ', '200 Review Ave, City',
   'mark.moderator@example.org', 'mark.moderator+recovery@example.org', 'changeme',
   'matrix-mod-1', DATE_ADD(NOW(), INTERVAL 1 YEAR), NULL,
   2, 1, NOW(), NOW()),

  (3, 1, 'Mia Moderator', 'CFP HQ', '201 Review Ave, City',
   'mia.moderator@example.org', 'mia.moderator+recovery@example.org', 'changeme',
   'matrix-mod-2', DATE_ADD(NOW(), INTERVAL 1 YEAR), NULL,
   2, 1, NOW(), NOW()),

  (4, 1, 'Adam Author', 'Open Uni', '10 Scholar Way, City',
   'adam.author@example.org', 'adam.author+recovery@example.org', 'changeme',
   'matrix-author-1', DATE_ADD(NOW(), INTERVAL 1 YEAR), '0000-0001-0000-0004',
   3, 1, NOW(), NOW()),

  (5, 1, 'Beatrice Author', 'Research Lab A', '11 Scholar Way, City',
   'bea.author@example.org', 'bea.author+recovery@example.org', 'changeme',
   'matrix-author-2', DATE_ADD(NOW(), INTERVAL 1 YEAR), '0000-0001-0000-0005',
   3, 1, NOW(), NOW()),

  (6, 2, 'Carlos Author', 'Research Lab B', '12 Scholar Way, City',
   'carlos.author@example.org', 'carlos.author+recovery@example.org', 'changeme',
   'matrix-author-3', DATE_ADD(NOW(), INTERVAL 1 YEAR), '0000-0001-0000-0006',
   3, 1, NOW(), NOW()),

  (7, 4, 'Mira Member', 'City Library', '50 Reader Rd, City',
   'mira.member@example.org', 'mira.member+recovery@example.org', 'changeme',
   'matrix-member-1', DATE_ADD(NOW(), INTERVAL 1 YEAR), NULL,
   4, 1, NOW(), NOW()),

  (8, 5, 'Noah Member', 'City Library', '51 Reader Rd, City',
   'noah.member@example.org', 'noah.member+recovery@example.org', 'changeme',
   'matrix-member-2', DATE_ADD(NOW(), INTERVAL 1 YEAR), NULL,
   4, 1, NOW(), NOW());

INSERT INTO authors (member_id, orcid, bio) VALUES
  (4, '0000-0001-0000-0004', 'Adam writes about open educational resources.'),
  (5, '0000-0001-0000-0005', 'Beatrice focuses on digital preservation.'),
  (6, '0000-0001-0000-0006', 'Carlos researches plagiarism detection.');

-- ---------------------------------------------------------------------------
-- Charities
-- ---------------------------------------------------------------------------

INSERT INTO charities (id, name, details, website, created_at, updated_at) VALUES
  (1, 'Global Literacy Fund', 'Supports literacy programs worldwide.', 'https://example.org/literacy', NOW(), NOW()),
  (2, 'Open Science Initiative', 'Promotes open access to scientific research.', 'https://example.org/open-science', NOW(), NOW()),
  (3, 'Local Education Trust', 'Funds educational initiatives in local communities.', 'https://example.org/education-trust', NOW(), NOW());

-- ---------------------------------------------------------------------------
-- Items and versions
-- ---------------------------------------------------------------------------

INSERT INTO items (
    id, author_id, title, description, file_path,
    upload_date, status_id, version_parent_id
) VALUES
  (1, 4, 'Open Textbook on Algorithms',
   'An introductory textbook on algorithms under a copy-forward license.',
   '/files/items/algorithms-v1.pdf',
   DATE_SUB(NOW(), INTERVAL 90 DAY), 3, NULL),

  (2, 4, 'Open Textbook on Algorithms (Revised)',
   'Revised edition with additional examples.',
   '/files/items/algorithms-v2.pdf',
   DATE_SUB(NOW(), INTERVAL 30 DAY), 3, 1),

  (3, 5, 'Digital Preservation Handbook',
   'Guidelines for long-term digital preservation.',
   '/files/items/preservation-v1.pdf',
   DATE_SUB(NOW(), INTERVAL 40 DAY), 2, NULL),

  (4, 6, 'Case Studies in Plagiarism',
   'Anonymized case studies used for committee training.',
   '/files/items/plagiarism-cases-v1.pdf',
   DATE_SUB(NOW(), INTERVAL 10 DAY), 5, NULL);

INSERT INTO item_versions (
    id, item_id, version_number, file_path, approved_by, approved_on
) VALUES
  (1, 1, 1, '/files/items/algorithms-v1.pdf', 2, DATE_SUB(NOW(), INTERVAL 85 DAY)),
  (2, 2, 2, '/files/items/algorithms-v2.pdf', 3, DATE_SUB(NOW(), INTERVAL 25 DAY)),
  (3, 3, 1, '/files/items/preservation-v1.pdf', NULL, NULL),
  (4, 4, 1, '/files/items/plagiarism-cases-v1.pdf', 2, DATE_SUB(NOW(), INTERVAL 7 DAY));

-- ---------------------------------------------------------------------------
-- Downloads (to demonstrate limits: donor vs non-donor)
-- ---------------------------------------------------------------------------

INSERT INTO downloads (id, member_id, item_id, download_date, ip_address) VALUES
  -- Mira Member: several downloads over the past 60 days
  (1, 7, 1, DATE_SUB(NOW(), INTERVAL 60 DAY), '203.0.113.10'),
  (2, 7, 2, DATE_SUB(NOW(), INTERVAL 15 DAY), '203.0.113.10'),
  (3, 7, 2, DATE_SUB(NOW(), INTERVAL 6 DAY),  '203.0.113.10'),

  -- Noah Member: frequent downloader, some within last 7 days
  (4, 8, 1, DATE_SUB(NOW(), INTERVAL 3 DAY),  '198.51.100.20'),
  (5, 8, 1, DATE_SUB(NOW(), INTERVAL 2 DAY),  '198.51.100.20'),
  (6, 8, 2, DATE_SUB(NOW(), INTERVAL 1 DAY),  '198.51.100.20'),

  -- Authors downloading their own/other content
  (7, 4, 1, DATE_SUB(NOW(), INTERVAL 20 DAY), '192.0.2.5'),
  (8, 5, 3, DATE_SUB(NOW(), INTERVAL 5 DAY),  '192.0.2.6');

-- ---------------------------------------------------------------------------
-- Donations (used later for donor-based download rules)
-- ---------------------------------------------------------------------------

INSERT INTO donations (
    id, member_id, item_id, amount,
    percent_charity, percent_cfp, percent_author,
    charity_id, date
) VALUES
  -- Mira donated within the last year
  (1, 7, 1, 50.00, 70.00, 20.00, 10.00, 1, DATE_SUB(NOW(), INTERVAL 3 MONTH)),
  (2, 7, 2, 25.00, 60.00, 25.00, 15.00, 2, DATE_SUB(NOW(), INTERVAL 1 MONTH)),

  -- Noah donated over a year ago
  (3, 8, 1, 15.00, 60.00, 25.00, 15.00, 3, DATE_SUB(NOW(), INTERVAL 400 DAY)),

  -- Small donations from other members
  (4, 4, 3, 10.00, 65.00, 20.00, 15.00, 2, DATE_SUB(NOW(), INTERVAL 10 DAY)),
  (5, 5, 1, 5.00,  60.00, 20.00, 20.00, 1, DATE_SUB(NOW(), INTERVAL 5 DAY));

-- ---------------------------------------------------------------------------
-- Committees and memberships
-- ---------------------------------------------------------------------------

INSERT INTO committees (id, name, description, created_at, updated_at) VALUES
  (1, 'Plagiarism Review Committee', 'Handles plagiarism reports and blacklisting decisions.', NOW(), NOW()),
  (2, 'Appeals Committee', 'Reviews appeals from authors whose items were blacklisted.', NOW(), NOW());

INSERT INTO committee_members (committee_id, member_id, role, joined_on) VALUES
  (1, 2, 'chair',    DATE_SUB(NOW(), INTERVAL 60 DAY)),
  (1, 3, 'reviewer', DATE_SUB(NOW(), INTERVAL 60 DAY)),
  (1, 4, 'member',   DATE_SUB(NOW(), INTERVAL 30 DAY)),
  (2, 3, 'chair',    DATE_SUB(NOW(), INTERVAL 30 DAY)),
  (2, 5, 'reviewer', DATE_SUB(NOW(), INTERVAL 30 DAY));

-- ---------------------------------------------------------------------------
-- Discussions, votes, and comments
-- ---------------------------------------------------------------------------

INSERT INTO discussions (
    id, committee_id, item_id, status_id,
    subject, content, created_by, created_on
) VALUES
  (1, 1, 4, 1,
   'Potential plagiarism in "Case Studies in Plagiarism"',
   'Initial review of plagiarism concerns raised about this training item.',
   2, DATE_SUB(NOW(), INTERVAL 5 DAY)),

  (2, 2, 4, 2,
   'Appeal on blacklisting decision for "Case Studies in Plagiarism"',
   'Author has submitted an appeal arguing educational fair use.',
   3, DATE_SUB(NOW(), INTERVAL 2 DAY));

INSERT INTO votes (
    id, discussion_id, voter_id, vote_option_id, vote_date
) VALUES
  -- Discussion 1 (open)
  (1, 1, 2, 1, DATE_SUB(NOW(), INTERVAL 4 DAY)),  -- Mark: yes (in favour of blacklisting)
  (2, 1, 3, 1, DATE_SUB(NOW(), INTERVAL 4 DAY)),  -- Mia: yes
  (3, 1, 4, 2, DATE_SUB(NOW(), INTERVAL 3 DAY)),  -- Adam: no

  -- Discussion 2 (closed appeal)
  (4, 2, 3, 3, DATE_SUB(NOW(), INTERVAL 1 DAY)),  -- Mia: abstain
  (5, 2, 5, 2, DATE_SUB(NOW(), INTERVAL 1 DAY));  -- Beatrice: no

INSERT INTO comments (
    id, item_id, author_id, content, created_on
) VALUES
  (1, 1, 7, 'Great introductory material, very accessible.', DATE_SUB(NOW(), INTERVAL 20 DAY)),
  (2, 1, 8, 'Would love more examples on graph algorithms.', DATE_SUB(NOW(), INTERVAL 10 DAY)),
  (3, 3, 7, 'Helpful overview for our library''s preservation project.', DATE_SUB(NOW(), INTERVAL 7 DAY)),
  (4, 4, 8, 'Concerned about examples that look very similar to a paid course.', DATE_SUB(NOW(), INTERVAL 6 DAY));

-- ---------------------------------------------------------------------------
-- Internal messages and moderation logs
-- ---------------------------------------------------------------------------

INSERT INTO internal_messages (
    id, from_member, to_member, subject, body, is_private, is_read, sent_on
) VALUES
  (1, 2, 6,
   'Plagiarism review initiated',
   'We have opened a plagiarism review regarding your item "Case Studies in Plagiarism".',
   1, 0, DATE_SUB(NOW(), INTERVAL 5 DAY)),
  (2, 6, 2,
   'Re: Plagiarism review initiated',
   'Thank you for letting me know. I am happy to provide additional context and sources.',
   1, 0, DATE_SUB(NOW(), INTERVAL 4 DAY)),
  (3, 1, 2,
   'Reminder: pending approvals',
   'Please review pending items before the committee meeting next week.',
   1, 1, DATE_SUB(NOW(), INTERVAL 2 DAY));

INSERT INTO moderation_logs (
    id, moderator_id, item_id, member_id, action, details, created_on
) VALUES
  (1, 2, 1, 4, 'approve_item', 'Initial approval of algorithms textbook v1.', DATE_SUB(NOW(), INTERVAL 85 DAY)),
  (2, 3, 2, 4, 'approve_item', 'Approval of revised algorithms textbook v2.', DATE_SUB(NOW(), INTERVAL 25 DAY)),
  (3, 2, 4, 6, 'blacklist_item', 'Item blacklisted after plagiarism committee vote.', DATE_SUB(NOW(), INTERVAL 5 DAY));

-- ---------------------------------------------------------------------------
-- Example daily statistics (derived data for reports)
-- ---------------------------------------------------------------------------

INSERT INTO daily_item_stats (stat_date, item_id, downloads_count, donations_sum) VALUES
  (DATE_SUB(CURDATE(), INTERVAL 1 DAY), 1, 5, 75.00),
  (DATE_SUB(CURDATE(), INTERVAL 1 DAY), 2, 3, 25.00),
  (DATE_SUB(CURDATE(), INTERVAL 1 DAY), 3, 2, 10.00);

INSERT INTO daily_author_stats (stat_date, author_id, downloads_count, donations_sum) VALUES
  (DATE_SUB(CURDATE(), INTERVAL 1 DAY), 4, 8, 100.00),
  (DATE_SUB(CURDATE(), INTERVAL 1 DAY), 5, 2, 10.00);

-- End of sample data


