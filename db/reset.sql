-- CFP Database Reset Script
-- Drops and recreates the `cfp` database, then loads schema and seed data.
--
-- Usage (from project root):
--   mysql -u root -p < db/reset.sql
--
-- This will:
--   1. DROP DATABASE IF EXISTS cfp;
--   2. CREATE DATABASE cfp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
--   3. USE cfp;
--   4. SOURCE db/schema.sql;
--   5. SOURCE db/seed.sql;

DROP DATABASE IF EXISTS cfp;
CREATE DATABASE cfp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cfp;

SOURCE db/schema.sql;
SOURCE db/seed.sql;


