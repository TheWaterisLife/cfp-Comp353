-- Author: Adam Mohammed Dahmane (40251506)

-- CFP Database Schema
-- Phase 1: ERD → Relational schema
-- Target: MariaDB / MySQL

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- ---------------------------------------------------------------------------
-- Core lookup / enum-like tables
-- ---------------------------------------------------------------------------

CREATE TABLE roles (
    id           TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(32) NOT NULL UNIQUE,   -- admin, moderator, author, member
    description  VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE member_statuses (
    id           TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code         VARCHAR(32) NOT NULL UNIQUE,   -- active, suspended, pending, disabled
    description  VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE item_statuses (
    id           TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code         VARCHAR(32) NOT NULL UNIQUE,   -- draft, pending_review, approved, rejected, blacklisted, removed
    description  VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE discussion_statuses (
    id           TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code         VARCHAR(32) NOT NULL UNIQUE,   -- open, closed, archived
    description  VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vote_options (
    id           TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code         VARCHAR(32) NOT NULL UNIQUE,   -- yes, no, abstain
    description  VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Members / authorship
-- ---------------------------------------------------------------------------

CREATE TABLE members (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    introducer_id    INT UNSIGNED NULL,
    name             VARCHAR(255) NOT NULL,
    org              VARCHAR(255) NULL,
    address          VARCHAR(255) NULL,
    primary_email    VARCHAR(255) NOT NULL UNIQUE,
    recovery_email   VARCHAR(255) NULL,
    password_hash    VARCHAR(255) NOT NULL,
    auth_matrix      TEXT NULL,                -- authentication matrix payload
    matrix_expiry    DATETIME NULL,
    orcid            VARCHAR(32) NULL,
    role_id          TINYINT UNSIGNED NOT NULL,
    status_id        TINYINT UNSIGNED NOT NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_members_role
        FOREIGN KEY (role_id) REFERENCES roles(id),
    CONSTRAINT fk_members_status
        FOREIGN KEY (status_id) REFERENCES member_statuses(id),
    CONSTRAINT fk_members_introducer
        FOREIGN KEY (introducer_id) REFERENCES members(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_members_role ON members(role_id);
CREATE INDEX idx_members_status ON members(status_id);

CREATE TABLE authors (
    member_id   INT UNSIGNED PRIMARY KEY,
    orcid       VARCHAR(32) NULL,
    bio         TEXT NULL,

    CONSTRAINT fk_authors_member
        FOREIGN KEY (member_id) REFERENCES members(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Items and versions
-- ---------------------------------------------------------------------------

CREATE TABLE items (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    author_id          INT UNSIGNED NOT NULL,   -- references authors.member_id
    title              VARCHAR(255) NOT NULL,
    description        TEXT NULL,
    topic              VARCHAR(255) NULL,
    keywords           VARCHAR(512) NULL,       -- comma-separated keywords for simple search
    file_path          VARCHAR(255) NOT NULL,
    upload_date        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status_id          TINYINT UNSIGNED NOT NULL,
    version_parent_id  INT UNSIGNED NULL,       -- for logical version grouping if needed

    CONSTRAINT fk_items_author
        FOREIGN KEY (author_id) REFERENCES authors(member_id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_items_status
        FOREIGN KEY (status_id) REFERENCES item_statuses(id),
    CONSTRAINT fk_items_version_parent
        FOREIGN KEY (version_parent_id) REFERENCES items(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_items_author ON items(author_id);
CREATE INDEX idx_items_status ON items(status_id);
CREATE INDEX idx_items_upload_date ON items(upload_date);

CREATE TABLE item_versions (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    item_id         INT UNSIGNED NOT NULL,
    version_number  INT UNSIGNED NOT NULL,
    file_path       VARCHAR(255) NOT NULL,
    approved_by     INT UNSIGNED NULL,         -- moderator member id
    approved_on     DATETIME NULL,

    CONSTRAINT fk_item_versions_item
        FOREIGN KEY (item_id) REFERENCES items(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_item_versions_approved_by
        FOREIGN KEY (approved_by) REFERENCES members(id)
        ON DELETE SET NULL,
    CONSTRAINT uq_item_versions UNIQUE (item_id, version_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Charities and donations
-- ---------------------------------------------------------------------------

CREATE TABLE charities (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(255) NOT NULL,
    details     TEXT NULL,
    website     VARCHAR(255) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE donations (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    member_id        INT UNSIGNED NOT NULL,
    item_id          INT UNSIGNED NOT NULL,
    amount           DECIMAL(10,2) NOT NULL,
    percent_charity  DECIMAL(5,2) NOT NULL,
    percent_cfp      DECIMAL(5,2) NOT NULL,
    percent_author   DECIMAL(5,2) NOT NULL,
    charity_id       INT UNSIGNED NOT NULL,
    date             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_donations_member
        FOREIGN KEY (member_id) REFERENCES members(id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_donations_item
        FOREIGN KEY (item_id) REFERENCES items(id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_donations_charity
        FOREIGN KEY (charity_id) REFERENCES charities(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_donations_member ON donations(member_id);
CREATE INDEX idx_donations_item ON donations(item_id);
CREATE INDEX idx_donations_charity ON donations(charity_id);
CREATE INDEX idx_donations_date ON donations(date);

-- ---------------------------------------------------------------------------
-- Downloads
-- ---------------------------------------------------------------------------

CREATE TABLE downloads (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    member_id      INT UNSIGNED NOT NULL,
    item_id        INT UNSIGNED NOT NULL,
    download_date  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip_address     VARCHAR(45) NULL,
    country_code   CHAR(2) NULL,

    CONSTRAINT fk_downloads_member
        FOREIGN KEY (member_id) REFERENCES members(id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_downloads_item
        FOREIGN KEY (item_id) REFERENCES items(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_downloads_member_date ON downloads(member_id, download_date);
CREATE INDEX idx_downloads_item_date ON downloads(item_id, download_date);
CREATE INDEX idx_downloads_country_year ON downloads(country_code, download_date);

-- ---------------------------------------------------------------------------
-- Committees, discussions, votes, comments
-- ---------------------------------------------------------------------------

CREATE TABLE committees (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(255) NOT NULL,
    description  TEXT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE committee_members (
    committee_id  INT UNSIGNED NOT NULL,
    member_id     INT UNSIGNED NOT NULL,
    role          VARCHAR(64) NOT NULL,        -- chair, reviewer, member
    joined_on     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (committee_id, member_id),
    CONSTRAINT fk_committee_members_committee
        FOREIGN KEY (committee_id) REFERENCES committees(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_committee_members_member
        FOREIGN KEY (member_id) REFERENCES members(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE committee_requests (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    committee_id   INT UNSIGNED NOT NULL,
    member_id      INT UNSIGNED NOT NULL,
    status         VARCHAR(16) NOT NULL DEFAULT 'pending', -- pending, approved, denied
    requested_on   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    decided_on     DATETIME NULL,
    decided_by     INT UNSIGNED NULL,
    note           VARCHAR(255) NULL,

    CONSTRAINT fk_committee_requests_committee
        FOREIGN KEY (committee_id) REFERENCES committees(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_committee_requests_member
        FOREIGN KEY (member_id) REFERENCES members(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_committee_requests_decided_by
        FOREIGN KEY (decided_by) REFERENCES members(id)
        ON DELETE SET NULL,
    CONSTRAINT uq_committee_requests UNIQUE (committee_id, member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE discussions (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    committee_id    INT UNSIGNED NOT NULL,
    item_id         INT UNSIGNED NOT NULL,
    status_id       TINYINT UNSIGNED NOT NULL,
    subject         VARCHAR(255) NOT NULL,
    content         TEXT NOT NULL,
    created_by      INT UNSIGNED NOT NULL,
    created_on      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_discussions_committee
        FOREIGN KEY (committee_id) REFERENCES committees(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_discussions_item
        FOREIGN KEY (item_id) REFERENCES items(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_discussions_status
        FOREIGN KEY (status_id) REFERENCES discussion_statuses(id),
    CONSTRAINT fk_discussions_created_by
        FOREIGN KEY (created_by) REFERENCES members(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_discussions_committee ON discussions(committee_id);
CREATE INDEX idx_discussions_item ON discussions(item_id);

CREATE TABLE votes (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    discussion_id  INT UNSIGNED NOT NULL,
    voter_id       INT UNSIGNED NOT NULL,
    vote_option_id TINYINT UNSIGNED NOT NULL,
    vote_date      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_votes_discussion
        FOREIGN KEY (discussion_id) REFERENCES discussions(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_votes_voter
        FOREIGN KEY (voter_id) REFERENCES members(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_votes_option
        FOREIGN KEY (vote_option_id) REFERENCES vote_options(id),
    CONSTRAINT uq_votes_discussion_voter UNIQUE (discussion_id, voter_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_votes_discussion ON votes(discussion_id);

CREATE TABLE comments (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    item_id           INT UNSIGNED NOT NULL,
    author_id         INT UNSIGNED NOT NULL,       -- member id
    parent_comment_id INT UNSIGNED NULL,           -- for public replies / threading
    content           TEXT NOT NULL,
    created_on        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_comments_item
        FOREIGN KEY (item_id) REFERENCES items(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_comments_author
        FOREIGN KEY (author_id) REFERENCES members(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_comments_parent
        FOREIGN KEY (parent_comment_id) REFERENCES comments(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_comments_item ON comments(item_id);
CREATE INDEX idx_comments_parent ON comments(parent_comment_id);

-- ---------------------------------------------------------------------------
-- Internal messaging and moderation logs
-- ---------------------------------------------------------------------------

CREATE TABLE internal_messages (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    from_member  INT UNSIGNED NOT NULL,
    to_member    INT UNSIGNED NOT NULL,
    subject      VARCHAR(255) NOT NULL,
    body         TEXT NOT NULL,
    is_private   TINYINT(1) NOT NULL DEFAULT 1,
    is_read      TINYINT(1) NOT NULL DEFAULT 0,
    sent_on      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_internal_messages_from
        FOREIGN KEY (from_member) REFERENCES members(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_internal_messages_to
        FOREIGN KEY (to_member) REFERENCES members(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_internal_messages_to ON internal_messages(to_member, is_read);

CREATE TABLE moderation_logs (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    moderator_id INT UNSIGNED NOT NULL,
    item_id      INT UNSIGNED NULL,
    member_id    INT UNSIGNED NULL,
    action       VARCHAR(64) NOT NULL,      -- approve_item, reject_item, blacklist_item, suspend_author, etc.
    details      TEXT NULL,
    created_on   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_moderation_logs_moderator
        FOREIGN KEY (moderator_id) REFERENCES members(id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_moderation_logs_item
        FOREIGN KEY (item_id) REFERENCES items(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_moderation_logs_member
        FOREIGN KEY (member_id) REFERENCES members(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_moderation_logs_item ON moderation_logs(item_id);
CREATE INDEX idx_moderation_logs_member ON moderation_logs(member_id);

-- ---------------------------------------------------------------------------
-- Simple statistics tables (for future phases)
-- ---------------------------------------------------------------------------

CREATE TABLE daily_item_stats (
    stat_date       DATE NOT NULL,
    item_id         INT UNSIGNED NOT NULL,
    downloads_count INT UNSIGNED NOT NULL DEFAULT 0,
    donations_sum   DECIMAL(10,2) NOT NULL DEFAULT 0.00,

    PRIMARY KEY (stat_date, item_id),
    CONSTRAINT fk_daily_item_stats_item
        FOREIGN KEY (item_id) REFERENCES items(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE daily_author_stats (
    stat_date        DATE NOT NULL,
    author_id        INT UNSIGNED NOT NULL,   -- authors.member_id
    downloads_count  INT UNSIGNED NOT NULL DEFAULT 0,
    donations_sum    DECIMAL(10,2) NOT NULL DEFAULT 0.00,

    PRIMARY KEY (stat_date, author_id),
    CONSTRAINT fk_daily_author_stats_author
        FOREIGN KEY (author_id) REFERENCES authors(member_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- End of schema


