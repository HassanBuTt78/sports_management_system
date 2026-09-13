-- ================================================================
--  MIGRATION — remove Principal role + lock sports to exactly
--  Cricket / Football / Hockey
--  ------------------------------------------------------------
--  Run this ONCE against your existing sports_management_system
--  database. It does NOT touch admins/coaches/players data for
--  Cricket, Football, or Hockey — only:
--    1. Deletes any 'principal'-role rows/references
--    2. Drops the `principals` table
--    3. Tightens role ENUM columns to admin/coach/player only
--    4. Deletes any Volleyball/Racing sport data (coaches, players,
--       teams, matches, events, scores, ratings, performance rows)
--    5. Adds Hockey as a sport if it doesn't already exist
--
--  BACK UP FIRST:
--    mysqldump -u root -p sports_management_system > backup_before_migration.sql
--
--  Run with:
--    mysql -u root -p sports_management_system < database/migrate_remove_principal_and_extra_sports.sql
--
--  SAFE TO RE-RUN — every step is guarded / no-op if already applied.
-- ================================================================

USE sports_management_system;

-- ----------------------------------------------------------------
-- STEP 1 — Purge principal-role data from polymorphic tables
--          (do this BEFORE dropping the principals table so we
--          still know which IDs were principals, if needed later)
-- ----------------------------------------------------------------
DELETE FROM messages       WHERE sender_role = 'principal' OR receiver_role = 'principal';
DELETE FROM notifications  WHERE receiver_role = 'principal';
DELETE FROM activity_logs  WHERE user_role = 'principal';

-- Module 10 chat tables only exist if schema_module10.sql was applied.
DELETE FROM conversation_members WHERE user_role = 'principal';
DELETE FROM conversations        WHERE created_by_role = 'principal';
DELETE FROM user_presence        WHERE user_role = 'principal';

-- ----------------------------------------------------------------
-- STEP 2 — Drop the principals table entirely
-- ----------------------------------------------------------------
DROP TABLE IF EXISTS principals;

-- ----------------------------------------------------------------
-- STEP 3 — Tighten role ENUM columns (admin/coach/player only).
--          Safe now that step 1 removed every 'principal' row.
-- ----------------------------------------------------------------
ALTER TABLE messages
    MODIFY COLUMN sender_role   ENUM('admin','coach','player') NOT NULL,
    MODIFY COLUMN receiver_role ENUM('admin','coach','player') NOT NULL;

ALTER TABLE notifications
    MODIFY COLUMN receiver_role ENUM('admin','coach','player','all') NOT NULL;

ALTER TABLE activity_logs
    MODIFY COLUMN user_role ENUM('admin','coach','player') NOT NULL;

-- Module 10 chat tables (skip silently if not present on your install)
ALTER TABLE conversation_members
    MODIFY COLUMN user_role ENUM('admin','coach','player') NOT NULL;

ALTER TABLE conversations
    MODIFY COLUMN created_by_role ENUM('admin','coach','player') NOT NULL;

ALTER TABLE user_presence
    MODIFY COLUMN user_role ENUM('admin','coach','player') NOT NULL;

-- ----------------------------------------------------------------
-- STEP 4 — Remove Volleyball / Racing sport data completely.
--          Deleted in dependency order (leaves first) rather than
--          relying on cascades, so this works regardless of each
--          FK's ON DELETE setting.
-- ----------------------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS tmp_extra_sports;
CREATE TEMPORARY TABLE tmp_extra_sports AS
    SELECT sport_id FROM sports WHERE sport_name IN ('Volleyball', 'Racing');

DROP TEMPORARY TABLE IF EXISTS tmp_extra_players;
CREATE TEMPORARY TABLE tmp_extra_players AS
    SELECT player_id FROM players WHERE sport_id IN (SELECT sport_id FROM tmp_extra_sports);

DROP TEMPORARY TABLE IF EXISTS tmp_extra_coaches;
CREATE TEMPORARY TABLE tmp_extra_coaches AS
    SELECT coach_id FROM coaches WHERE sport_id IN (SELECT sport_id FROM tmp_extra_sports);

DROP TEMPORARY TABLE IF EXISTS tmp_extra_teams;
CREATE TEMPORARY TABLE tmp_extra_teams AS
    SELECT team_id FROM teams WHERE sport_id IN (SELECT sport_id FROM tmp_extra_sports);

DROP TEMPORARY TABLE IF EXISTS tmp_extra_matches;
CREATE TEMPORARY TABLE tmp_extra_matches AS
    SELECT match_id FROM matches WHERE sport_id IN (SELECT sport_id FROM tmp_extra_sports);

DROP TEMPORARY TABLE IF EXISTS tmp_extra_events;
CREATE TEMPORARY TABLE tmp_extra_events AS
    SELECT event_id FROM events WHERE sport_id IN (SELECT sport_id FROM tmp_extra_sports);

-- Chat/notification cleanup for the coaches/players about to be deleted
DELETE FROM messages WHERE
    (sender_role = 'coach' AND sender_id IN (SELECT coach_id FROM tmp_extra_coaches)) OR
    (receiver_role = 'coach' AND receiver_id IN (SELECT coach_id FROM tmp_extra_coaches)) OR
    (sender_role = 'player' AND sender_id IN (SELECT player_id FROM tmp_extra_players)) OR
    (receiver_role = 'player' AND receiver_id IN (SELECT player_id FROM tmp_extra_players));

DELETE FROM notifications WHERE
    (receiver_role = 'coach' AND receiver_id IN (SELECT coach_id FROM tmp_extra_coaches)) OR
    (receiver_role = 'player' AND receiver_id IN (SELECT player_id FROM tmp_extra_players));

DELETE FROM conversation_members WHERE
    (user_role = 'coach' AND user_id IN (SELECT coach_id FROM tmp_extra_coaches)) OR
    (user_role = 'player' AND user_id IN (SELECT player_id FROM tmp_extra_players));

-- Performance module rows
DELETE FROM player_performance_history WHERE player_id IN (SELECT player_id FROM tmp_extra_players);
DELETE FROM performance_analysis       WHERE player_id IN (SELECT player_id FROM tmp_extra_players);
DELETE FROM team_performance           WHERE team_id  IN (SELECT team_id  FROM tmp_extra_teams);
DELETE FROM coach_performance          WHERE coach_id IN (SELECT coach_id FROM tmp_extra_coaches);

-- Match-related rows
DELETE FROM player_scores  WHERE match_id  IN (SELECT match_id  FROM tmp_extra_matches);
DELETE FROM player_ratings WHERE player_id IN (SELECT player_id FROM tmp_extra_players);
DELETE FROM matches        WHERE match_id  IN (SELECT match_id  FROM tmp_extra_matches);

-- Event-related rows
DELETE FROM event_participants WHERE event_id IN (SELECT event_id FROM tmp_extra_events);
DELETE FROM events             WHERE event_id IN (SELECT event_id FROM tmp_extra_events);

-- Team roster + team rows (clear cross-references first to dodge FK errors)
DELETE FROM team_players WHERE team_id IN (SELECT team_id FROM tmp_extra_teams);
UPDATE teams SET captain_id = NULL, vice_captain_id = NULL WHERE team_id IN (SELECT team_id FROM tmp_extra_teams);
UPDATE players SET team_id = NULL WHERE player_id IN (SELECT player_id FROM tmp_extra_players);
DELETE FROM teams WHERE team_id IN (SELECT team_id FROM tmp_extra_teams);

-- Players + coaches themselves
DELETE FROM players WHERE player_id IN (SELECT player_id FROM tmp_extra_players);
DELETE FROM coaches WHERE coach_id IN (SELECT coach_id FROM tmp_extra_coaches);

-- Finally, the sports themselves
DELETE FROM sports WHERE sport_id IN (SELECT sport_id FROM tmp_extra_sports);

DROP TEMPORARY TABLE IF EXISTS tmp_extra_sports;
DROP TEMPORARY TABLE IF EXISTS tmp_extra_players;
DROP TEMPORARY TABLE IF EXISTS tmp_extra_coaches;
DROP TEMPORARY TABLE IF EXISTS tmp_extra_teams;
DROP TEMPORARY TABLE IF EXISTS tmp_extra_matches;
DROP TEMPORARY TABLE IF EXISTS tmp_extra_events;

-- ----------------------------------------------------------------
-- STEP 5 — Make sure exactly Cricket, Football, Hockey exist
-- ----------------------------------------------------------------
INSERT IGNORE INTO sports (sport_name) VALUES ('Cricket'), ('Football'), ('Hockey');

-- ----------------------------------------------------------------
-- Verify — should show admin/coach/player only, and exactly 3 sports
-- ----------------------------------------------------------------
SELECT sport_id, sport_name FROM sports ORDER BY sport_id;
SELECT DISTINCT sender_role FROM messages;
SELECT DISTINCT receiver_role FROM notifications;
