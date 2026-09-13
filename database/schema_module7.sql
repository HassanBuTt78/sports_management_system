-- ================================================================
--  SPORTS MANAGEMENT SYSTEM — MODULE 7 ADDITIVE MIGRATION
--  Team Management
--  ----------------------------------------------------------------
--  Two gaps between the brief and the Module 2 schema:
--    1. `teams` has no logo/description/status — needed for
--       "Create Team" and "Team Details Page".
--    2. `players` has no position — needed for the Team Members
--       table ("Photo, Player ID, Name, Position, Rating, Status").
--  Both are added as nullable/defaulted columns only. No existing
--  column, row, or table is modified — every Module 2–6 feature
--  keeps working exactly as before.
--
--  Import AFTER schema.sql, schema_module3.sql, schema_module6.sql:
--    mysql -u root -p sports_management_system < schema_module7.sql
-- ================================================================

USE sports_management_system;

ALTER TABLE teams
    ADD COLUMN team_logo  VARCHAR(255) DEFAULT NULL AFTER vice_captain_id,
    ADD COLUMN description TEXT DEFAULT NULL AFTER team_logo,
    ADD COLUMN status ENUM('active','inactive') NOT NULL DEFAULT 'active' AFTER description;

ALTER TABLE players
    ADD COLUMN position VARCHAR(50) DEFAULT NULL AFTER team_id;
