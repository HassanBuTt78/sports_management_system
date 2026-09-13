-- ================================================================
--  SPORTS MANAGEMENT SYSTEM — DEMO DATA SEED
--  ------------------------------------------------------------
--  Adds a second coach + roster, two rival teams, two events,
--  and two COMPLETED matches (with per-player scores + coach
--  ratings) so the dashboards, Performance module, and Team
--  pages have real data to show instead of empty states.
--
--  SAFE TO RE-RUN: every INSERT is guarded (INSERT IGNORE /
--  ON DUPLICATE KEY UPDATE / WHERE NOT EXISTS) so running this
--  twice will not create duplicate rows or error out.
--
--  Demo login password for every account created by this script:
--      Demo@1234
--  (bcrypt hash below was generated with the same algorithm PHP's
--  password_hash() uses, so it verifies correctly with password_verify().)
--
--  Run with:
--    mysql -u root -p sports_management_system < database/seed_demo_data.sql
--  or import via phpMyAdmin > Import.
--
--  AFTER importing: log in as admin -> Performance -> Recalculate
--  (performance/recalculate.php) so the app's own scoring engine
--  computes performance_analysis / player_performance_history from
--  the scores + ratings this script inserts below. That keeps the
--  numbers consistent with your real weighting logic instead of
--  this script guessing at them.
-- ================================================================

USE sports_management_system;
SET @demo_password = '$2b$10$nZBuncC3UO1M.OG10ablmuWZvlH0oeDoFzVzYGcmUjcd01Bm8wQVK'; -- Demo@1234

-- ----------------------------------------------------------------
-- 1. Make sure both sports we use exist
-- ----------------------------------------------------------------
INSERT IGNORE INTO sports (sport_name) VALUES ('Cricket'), ('Football');

SET @cricket_id = (SELECT sport_id FROM sports WHERE sport_name = 'Cricket' LIMIT 1);
SET @football_id = (SELECT sport_id FROM sports WHERE sport_name = 'Football' LIMIT 1);

-- ----------------------------------------------------------------
-- 2. A second coach (Football) — leaves your existing coach1 alone
-- ----------------------------------------------------------------
INSERT INTO coaches (sport_id, full_name, email, password, phone, experience, qualification, status)
VALUES (@football_id, 'Coach Ahmed Raza', 'coach2@gmail.com', @demo_password, '03011122234', '4 years', 'BS Sports Science', 'active')
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name);

SET @coach1_id = (SELECT coach_id FROM coaches WHERE email = 'coach1@gmail.com' LIMIT 1);
SET @coach2_id = (SELECT coach_id FROM coaches WHERE email = 'coach2@gmail.com' LIMIT 1);

-- ----------------------------------------------------------------
-- 3. Players — 2 more for Cricket (join the existing LION roster),
--    4 for a brand-new Football squad
-- ----------------------------------------------------------------
INSERT INTO players (coach_id, sport_id, full_name, roll_no, email, password, phone, age, gender, blood_group, height, weight, status)
VALUES
  (@coach1_id, @cricket_id, 'Bilal Hassan',  'CRK-2026-101', 'bilal.hassan@student.edu.pk',  @demo_password, '03012340001', 21, 'male', 'B+',  175.00, 68.00, 'active'),
  (@coach1_id, @cricket_id, 'Zain Malik',    'CRK-2026-102', 'zain.malik@student.edu.pk',    @demo_password, '03012340002', 22, 'male', 'O+',  178.00, 72.00, 'active'),
  (@coach2_id, @football_id, 'Usman Tariq',  'FTB-2026-101', 'usman.tariq@student.edu.pk',   @demo_password, '03012340003', 20, 'male', 'A+',  177.00, 70.00, 'active'),
  (@coach2_id, @football_id, 'Hamza Iqbal',  'FTB-2026-102', 'hamza.iqbal@student.edu.pk',   @demo_password, '03012340004', 21, 'male', 'AB+', 180.00, 74.00, 'active'),
  (@coach2_id, @football_id, 'Danish Aziz',  'FTB-2026-103', 'danish.aziz@student.edu.pk',   @demo_password, '03012340005', 22, 'male', 'O-',  173.00, 66.00, 'active'),
  (@coach2_id, @football_id, 'Saad Yousaf',  'FTB-2026-104', 'saad.yousaf@student.edu.pk',   @demo_password, '03012340006', 23, 'male', 'B-',  182.00, 76.00, 'active')
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name);

SET @p_bilal  = (SELECT player_id FROM players WHERE email = 'bilal.hassan@student.edu.pk' LIMIT 1);
SET @p_zain   = (SELECT player_id FROM players WHERE email = 'zain.malik@student.edu.pk' LIMIT 1);
SET @p_usman  = (SELECT player_id FROM players WHERE email = 'usman.tariq@student.edu.pk' LIMIT 1);
SET @p_hamzai = (SELECT player_id FROM players WHERE email = 'hamza.iqbal@student.edu.pk' LIMIT 1);
SET @p_danish = (SELECT player_id FROM players WHERE email = 'danish.aziz@student.edu.pk' LIMIT 1);
SET @p_saad   = (SELECT player_id FROM players WHERE email = 'saad.yousaf@student.edu.pk' LIMIT 1);

-- ----------------------------------------------------------------
-- 4. Teams — your existing "LION" (Cricket) stays as-is; add a
--    Football squad for coach2, plus one rival team per sport so
--    there's someone to actually play a match against.
-- ----------------------------------------------------------------
INSERT INTO teams (sport_id, coach_id, team_name)
SELECT @football_id, @coach2_id, 'EAGLES'
WHERE NOT EXISTS (SELECT 1 FROM teams WHERE team_name = 'EAGLES');

INSERT INTO teams (sport_id, coach_id, team_name)
SELECT @cricket_id, NULL, 'TIGERS'
WHERE NOT EXISTS (SELECT 1 FROM teams WHERE team_name = 'TIGERS');

INSERT INTO teams (sport_id, coach_id, team_name)
SELECT @football_id, NULL, 'FALCONS'
WHERE NOT EXISTS (SELECT 1 FROM teams WHERE team_name = 'FALCONS');

SET @team_lion    = (SELECT team_id FROM teams WHERE team_name = 'LION' LIMIT 1);
SET @team_eagles  = (SELECT team_id FROM teams WHERE team_name = 'EAGLES' LIMIT 1);
SET @team_tigers  = (SELECT team_id FROM teams WHERE team_name = 'TIGERS' LIMIT 1);
SET @team_falcons = (SELECT team_id FROM teams WHERE team_name = 'FALCONS' LIMIT 1);

UPDATE teams SET captain_id = @p_usman, vice_captain_id = @p_hamzai WHERE team_id = @team_eagles;

-- Assign the two new Cricket players onto LION's roster and the
-- Football roster onto EAGLES.
UPDATE players SET team_id = @team_lion   WHERE player_id IN (@p_bilal, @p_zain);
UPDATE players SET team_id = @team_eagles WHERE player_id IN (@p_usman, @p_hamzai, @p_danish, @p_saad);

INSERT IGNORE INTO team_players (team_id, player_id) VALUES
  (@team_lion, @p_bilal), (@team_lion, @p_zain),
  (@team_eagles, @p_usman), (@team_eagles, @p_hamzai), (@team_eagles, @p_danish), (@team_eagles, @p_saad);

-- ----------------------------------------------------------------
-- 5. Events — one per sport, Upcoming, with a couple of players
--    registered so coach/player Events pages aren't empty either.
-- ----------------------------------------------------------------
INSERT INTO events (sport_id, coach_id, title, description, venue, event_date, start_time, status)
SELECT @cricket_id, @coach1_id, 'Inter-College Cricket Cup',
       'Annual inter-college T20 cricket tournament.', 'College Cricket Ground',
       DATE_ADD(CURDATE(), INTERVAL 14 DAY), '09:00:00', 'Upcoming'
WHERE NOT EXISTS (SELECT 1 FROM events WHERE title = 'Inter-College Cricket Cup');

INSERT INTO events (sport_id, coach_id, title, description, venue, event_date, start_time, status)
SELECT @football_id, @coach2_id, 'Football Friendly League',
       'Round-robin football friendlies between college squads.', 'Main Football Ground',
       DATE_ADD(CURDATE(), INTERVAL 21 DAY), '16:00:00', 'Upcoming'
WHERE NOT EXISTS (SELECT 1 FROM events WHERE title = 'Football Friendly League');

SET @event_cricket  = (SELECT event_id FROM events WHERE title = 'Inter-College Cricket Cup' LIMIT 1);
SET @event_football = (SELECT event_id FROM events WHERE title = 'Football Friendly League' LIMIT 1);

INSERT IGNORE INTO event_participants (event_id, player_id, participation_status) VALUES
  (@event_cricket, @p_bilal, 'Approved'),
  (@event_cricket, @p_zain, 'Approved'),
  (@event_football, @p_usman, 'Approved'),
  (@event_football, @p_hamzai, 'Approved'),
  (@event_football, @p_danish, 'Approved'),
  (@event_football, @p_saad, 'Approved');

-- ----------------------------------------------------------------
-- 6. Two COMPLETED matches — one Cricket, one Football — so the
--    Performance engine (Recalculate) has real match results,
--    scores, and coach ratings to compute from.
-- ----------------------------------------------------------------
INSERT INTO matches (sport_id, team_one, team_two, venue, match_date, winner_team, status)
SELECT @cricket_id, @team_lion, @team_tigers, 'College Cricket Ground',
       DATE_SUB(CURDATE(), INTERVAL 7 DAY), @team_lion, 'Completed'
WHERE NOT EXISTS (
  SELECT 1 FROM matches WHERE team_one = @team_lion AND team_two = @team_tigers AND sport_id = @cricket_id
);

INSERT INTO matches (sport_id, team_one, team_two, venue, match_date, winner_team, status)
SELECT @football_id, @team_eagles, @team_falcons, 'Main Football Ground',
       DATE_SUB(CURDATE(), INTERVAL 5 DAY), @team_eagles, 'Completed'
WHERE NOT EXISTS (
  SELECT 1 FROM matches WHERE team_one = @team_eagles AND team_two = @team_falcons AND sport_id = @football_id
);

SET @match_cricket  = (SELECT match_id FROM matches WHERE team_one = @team_lion AND team_two = @team_tigers LIMIT 1);
SET @match_football = (SELECT match_id FROM matches WHERE team_one = @team_eagles AND team_two = @team_falcons LIMIT 1);

-- Cricket scorecard (runs / wickets / catches)
INSERT INTO player_scores (player_id, coach_id, match_id, runs, wickets, catches, custom_score)
VALUES
  (@p_bilal, @coach1_id, @match_cricket, 54, 1, 2, 78.50),
  (@p_zain,  @coach1_id, @match_cricket, 31, 3, 1, 65.00)
ON DUPLICATE KEY UPDATE runs = VALUES(runs), wickets = VALUES(wickets), catches = VALUES(catches), custom_score = VALUES(custom_score);

-- Football scorecard (goals / assists)
INSERT INTO player_scores (player_id, coach_id, match_id, goals, assists, custom_score)
VALUES
  (@p_usman,  @coach2_id, @match_football, 2, 1, 82.00),
  (@p_hamzai, @coach2_id, @match_football, 1, 2, 75.50),
  (@p_danish, @coach2_id, @match_football, 0, 1, 58.00),
  (@p_saad,   @coach2_id, @match_football, 1, 0, 66.00)
ON DUPLICATE KEY UPDATE goals = VALUES(goals), assists = VALUES(assists), custom_score = VALUES(custom_score);

-- Coach star ratings (1-5) — used by the Performance engine's
-- "coach rating" component. One rating per player from their coach.
INSERT INTO player_ratings (player_id, coach_id, rating, review)
SELECT @p_bilal, @coach1_id, 4, 'Strong batting display, good temperament under pressure.'
WHERE NOT EXISTS (SELECT 1 FROM player_ratings WHERE player_id = @p_bilal AND coach_id = @coach1_id);

INSERT INTO player_ratings (player_id, coach_id, rating, review)
SELECT @p_zain, @coach1_id, 4, 'Solid bowling figures, needs to work on economy rate.'
WHERE NOT EXISTS (SELECT 1 FROM player_ratings WHERE player_id = @p_zain AND coach_id = @coach1_id);

INSERT INTO player_ratings (player_id, coach_id, rating, review)
SELECT @p_usman, @coach2_id, 5, 'Excellent finishing, two well-taken goals.'
WHERE NOT EXISTS (SELECT 1 FROM player_ratings WHERE player_id = @p_usman AND coach_id = @coach2_id);

INSERT INTO player_ratings (player_id, coach_id, rating, review)
SELECT @p_hamzai, @coach2_id, 4, 'Great vision, created multiple chances.'
WHERE NOT EXISTS (SELECT 1 FROM player_ratings WHERE player_id = @p_hamzai AND coach_id = @coach2_id);

INSERT INTO player_ratings (player_id, coach_id, rating, review)
SELECT @p_danish, @coach2_id, 3, 'Steady defensive performance.'
WHERE NOT EXISTS (SELECT 1 FROM player_ratings WHERE player_id = @p_danish AND coach_id = @coach2_id);

INSERT INTO player_ratings (player_id, coach_id, rating, review)
SELECT @p_saad, @coach2_id, 3, 'Good work rate, finishing needs improvement.'
WHERE NOT EXISTS (SELECT 1 FROM player_ratings WHERE player_id = @p_saad AND coach_id = @coach2_id);

-- ----------------------------------------------------------------
-- Done. Now log in as admin and run Performance -> Recalculate
-- (performance/recalculate.php) to generate performance_analysis
-- and player_performance_history rows from the data above.
-- ----------------------------------------------------------------
