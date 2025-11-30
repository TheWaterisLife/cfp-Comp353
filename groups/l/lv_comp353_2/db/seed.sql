-- Author: Adam Mohammed Dahmane (40251506)

-- CFP Seed Data (reset + sample content)
-- Phase 2: initialization with realistic test data
--
-- This script truncates all CFP tables and then re-loads the canonical sample
-- dataset via `db/sample_data.sql`. Use it when you want to reset the database
-- to a known state without dropping/recreating the schema.
--
-- Usage (from project root):
--   mysql -u <user> -p <database_name> < db/seed.sql

SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE daily_author_stats;
TRUNCATE TABLE daily_item_stats;
TRUNCATE TABLE moderation_logs;
TRUNCATE TABLE internal_messages;
TRUNCATE TABLE comments;
TRUNCATE TABLE votes;
TRUNCATE TABLE discussions;
TRUNCATE TABLE committee_members;
TRUNCATE TABLE committees;
TRUNCATE TABLE downloads;
TRUNCATE TABLE donations;
TRUNCATE TABLE charities;
TRUNCATE TABLE item_versions;
TRUNCATE TABLE items;
TRUNCATE TABLE authors;
TRUNCATE TABLE members;
TRUNCATE TABLE vote_options;
TRUNCATE TABLE discussion_statuses;
TRUNCATE TABLE item_statuses;
TRUNCATE TABLE member_statuses;
TRUNCATE TABLE roles;

SET FOREIGN_KEY_CHECKS = 1;

SOURCE db/sample_data.sql;


