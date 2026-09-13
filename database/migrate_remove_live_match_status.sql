-- ================================================================
--  MIGRATION — remove the "Running" (Live) match status
--  ------------------------------------------------------------
--  Matches now only ever have 3 states: Upcoming (Scheduled),
--  Completed, or Cancelled — there is no more in-progress/live
--  state. A match moves straight from Upcoming to Completed once
--  a coach or admin enters the result via the Results page.
--
--  This migration:
--    1. Converts any existing 'Running' matches back to 'Upcoming'
--       (they haven't been completed yet, so this is the correct
--       equivalent state — nothing about their scores/data is lost).
--    2. Tightens the matches.status ENUM to drop 'Running' entirely.
--
--  Run with:
--    mysql -u root -p sports_management_system < database/migrate_remove_live_match_status.sql
--
--  SAFE TO RE-RUN.
-- ================================================================

USE sports_management_system;

UPDATE matches SET status = 'Upcoming' WHERE status = 'Running';

ALTER TABLE matches
    MODIFY COLUMN status ENUM('Upcoming','Completed','Cancelled') NOT NULL DEFAULT 'Upcoming';

-- Verify — should show no 'Running' rows and a 3-value ENUM
SELECT status, COUNT(*) AS count FROM matches GROUP BY status;
SHOW COLUMNS FROM matches LIKE 'status';
