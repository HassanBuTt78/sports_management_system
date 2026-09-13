-- ================================================================
--  SPORTS MANAGEMENT SYSTEM — HOCKEY DEMO DATA
--  ------------------------------------------------------------
--  Adds Hockey as the 3rd sport (replacing Volleyball/Racing per
--  the locked-down spec: exactly Cricket, Football, Hockey).
--  Run this AFTER migrate_remove_principal_and_extra_sports.sql
--  (or after seed_demo_data.sql if this is a fresh DB that never
--  had Volleyball/Racing in the first place).
--
--  Adds: 1 coach, 1 squad (HAWKS) + 1 rival (STRIKERS), 4 players,
--  1 event, and 5 completed matches with per-player scores spread
--  over the last ~9 weeks (for a real performance trend), plus
--  1 upcoming match.
--
--  Run with:
--    mysql -u root -p sports_management_system < database/seed_demo_data_hockey.sql
--
--  Then log in as admin -> Performance -> Recalculate.
--  SAFE TO RE-RUN. Demo password for the new coach/players: Demo@1234
-- ================================================================

USE sports_management_system;
SET @demo_password = '$2b$10$nZBuncC3UO1M.OG10ablmuWZvlH0oeDoFzVzYGcmUjcd01Bm8wQVK';

INSERT IGNORE INTO sports (sport_name) VALUES ('Hockey');
SET @hockey_id = (SELECT sport_id FROM sports WHERE sport_name = 'Hockey' LIMIT 1);

-- Coach — reuses the coach3@gmail.com slot (previously Volleyball,
-- now repointed at Hockey; harmless if this is a fresh DB and the
-- row doesn't exist yet).
INSERT INTO coaches (sport_id, full_name, email, password, phone, experience, qualification, status)
VALUES (@hockey_id, 'Coach Bilal Sarwar', 'coach3@gmail.com', @demo_password, '03011122237', '5 years', 'PHF Level 2 Coaching Certificate', 'active')
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), sport_id = VALUES(sport_id);

SET @coach3_id = (SELECT coach_id FROM coaches WHERE email = 'coach3@gmail.com' LIMIT 1);

INSERT INTO players (coach_id, sport_id, full_name, roll_no, email, password, phone, age, gender, blood_group, height, weight, status)
VALUES
  (@coach3_id, @hockey_id, 'Hamza Sarwar', 'HKY-2026-101', 'hamza.sarwar@student.edu.pk', @demo_password, '03014440001', 21, 'male', 'A+', 176.0, 71.0, 'active'),
  (@coach3_id, @hockey_id, 'Owais Tariq', 'HKY-2026-102', 'owais.tariq@student.edu.pk', @demo_password, '03014440002', 20, 'male', 'B+', 179.0, 74.0, 'active'),
  (@coach3_id, @hockey_id, 'Rayyan Malik', 'HKY-2026-103', 'rayyan.malik@student.edu.pk', @demo_password, '03014440003', 22, 'male', 'O+', 174.0, 69.0, 'active'),
  (@coach3_id, @hockey_id, 'Ibrahim Nasir', 'HKY-2026-104', 'ibrahim.nasir@student.edu.pk', @demo_password, '03014440004', 21, 'male', 'AB+', 181.0, 76.0, 'active')
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name);

SET @p_hamza_sarwar = (SELECT player_id FROM players WHERE email = 'hamza.sarwar@student.edu.pk' LIMIT 1);
SET @p_owais_tariq = (SELECT player_id FROM players WHERE email = 'owais.tariq@student.edu.pk' LIMIT 1);
SET @p_rayyan_malik = (SELECT player_id FROM players WHERE email = 'rayyan.malik@student.edu.pk' LIMIT 1);
SET @p_ibrahim_nasir = (SELECT player_id FROM players WHERE email = 'ibrahim.nasir@student.edu.pk' LIMIT 1);

INSERT INTO teams (sport_id, coach_id, team_name)
SELECT @hockey_id, @coach3_id, 'HAWKS' WHERE NOT EXISTS (SELECT 1 FROM teams WHERE team_name = 'HAWKS');
INSERT INTO teams (sport_id, coach_id, team_name)
SELECT @hockey_id, NULL, 'STRIKERS' WHERE NOT EXISTS (SELECT 1 FROM teams WHERE team_name = 'STRIKERS');

SET @team_hawks    = (SELECT team_id FROM teams WHERE team_name = 'HAWKS' LIMIT 1);
SET @team_strikers = (SELECT team_id FROM teams WHERE team_name = 'STRIKERS' LIMIT 1);

UPDATE teams SET captain_id = @p_hamza_sarwar, vice_captain_id = @p_owais_tariq WHERE team_id = @team_hawks;
UPDATE players SET team_id = @team_hawks WHERE player_id IN (@p_hamza_sarwar, @p_owais_tariq, @p_rayyan_malik, @p_ibrahim_nasir);
INSERT IGNORE INTO team_players (team_id, player_id) VALUES
  (@team_hawks, @p_hamza_sarwar), (@team_hawks, @p_owais_tariq), (@team_hawks, @p_rayyan_malik), (@team_hawks, @p_ibrahim_nasir);

INSERT INTO events (sport_id, coach_id, title, description, venue, event_date, start_time, status)
SELECT @hockey_id, @coach3_id, 'Hockey Championship Cup', 'Inter-college field hockey knockout tournament.', 'College Hockey Ground', DATE_ADD(CURDATE(), INTERVAL 16 DAY), '10:00:00', 'Upcoming'
WHERE NOT EXISTS (SELECT 1 FROM events WHERE title = 'Hockey Championship Cup');
SET @event_hockey = (SELECT event_id FROM events WHERE title = 'Hockey Championship Cup' LIMIT 1);
INSERT IGNORE INTO event_participants (event_id, player_id, participation_status) VALUES
  (@event_hockey, @p_hamza_sarwar, 'Approved'), (@event_hockey, @p_owais_tariq, 'Approved'),
  (@event_hockey, @p_rayyan_malik, 'Approved'), (@event_hockey, @p_ibrahim_nasir, 'Approved');

-- HAWKS vs STRIKERS — match history
INSERT INTO matches (sport_id, team_one, team_two, venue, match_date, winner_team, status)
SELECT @hockey_id, @team_hawks, @team_strikers, 'College Hockey Ground', DATE_SUB(CURDATE(), INTERVAL 9 WEEK), @team_hawks, 'Completed'
WHERE NOT EXISTS (SELECT 1 FROM matches WHERE team_one=@team_hawks AND team_two=@team_strikers AND sport_id=@hockey_id AND match_date = DATE_SUB(CURDATE(), INTERVAL 9 WEEK));
INSERT INTO matches (sport_id, team_one, team_two, venue, match_date, winner_team, status)
SELECT @hockey_id, @team_hawks, @team_strikers, 'College Hockey Ground', DATE_SUB(CURDATE(), INTERVAL 7 WEEK), @team_strikers, 'Completed'
WHERE NOT EXISTS (SELECT 1 FROM matches WHERE team_one=@team_hawks AND team_two=@team_strikers AND sport_id=@hockey_id AND match_date = DATE_SUB(CURDATE(), INTERVAL 7 WEEK));
INSERT INTO matches (sport_id, team_one, team_two, venue, match_date, winner_team, status)
SELECT @hockey_id, @team_hawks, @team_strikers, 'College Hockey Ground', DATE_SUB(CURDATE(), INTERVAL 5 WEEK), @team_hawks, 'Completed'
WHERE NOT EXISTS (SELECT 1 FROM matches WHERE team_one=@team_hawks AND team_two=@team_strikers AND sport_id=@hockey_id AND match_date = DATE_SUB(CURDATE(), INTERVAL 5 WEEK));
INSERT INTO matches (sport_id, team_one, team_two, venue, match_date, winner_team, status)
SELECT @hockey_id, @team_hawks, @team_strikers, 'College Hockey Ground', DATE_SUB(CURDATE(), INTERVAL 3 WEEK), @team_hawks, 'Completed'
WHERE NOT EXISTS (SELECT 1 FROM matches WHERE team_one=@team_hawks AND team_two=@team_strikers AND sport_id=@hockey_id AND match_date = DATE_SUB(CURDATE(), INTERVAL 3 WEEK));
INSERT INTO matches (sport_id, team_one, team_two, venue, match_date, winner_team, status)
SELECT @hockey_id, @team_hawks, @team_strikers, 'College Hockey Ground', DATE_SUB(CURDATE(), INTERVAL 1 WEEK), @team_strikers, 'Completed'
WHERE NOT EXISTS (SELECT 1 FROM matches WHERE team_one=@team_hawks AND team_two=@team_strikers AND sport_id=@hockey_id AND match_date = DATE_SUB(CURDATE(), INTERVAL 1 WEEK));
INSERT INTO matches (sport_id, team_one, team_two, venue, match_date, status)
SELECT @hockey_id, @team_hawks, @team_strikers, 'College Hockey Ground', DATE_ADD(CURDATE(), INTERVAL 12 DAY), 'Upcoming'
WHERE NOT EXISTS (SELECT 1 FROM matches WHERE team_one=@team_hawks AND team_two=@team_strikers AND sport_id=@hockey_id AND status='Upcoming');

SET @match_hawks_9 = (SELECT match_id FROM matches WHERE team_one=@team_hawks AND team_two=@team_strikers AND sport_id=@hockey_id AND match_date = DATE_SUB(CURDATE(), INTERVAL 9 WEEK) LIMIT 1);
SET @match_hawks_7 = (SELECT match_id FROM matches WHERE team_one=@team_hawks AND team_two=@team_strikers AND sport_id=@hockey_id AND match_date = DATE_SUB(CURDATE(), INTERVAL 7 WEEK) LIMIT 1);
SET @match_hawks_5 = (SELECT match_id FROM matches WHERE team_one=@team_hawks AND team_two=@team_strikers AND sport_id=@hockey_id AND match_date = DATE_SUB(CURDATE(), INTERVAL 5 WEEK) LIMIT 1);
SET @match_hawks_3 = (SELECT match_id FROM matches WHERE team_one=@team_hawks AND team_two=@team_strikers AND sport_id=@hockey_id AND match_date = DATE_SUB(CURDATE(), INTERVAL 3 WEEK) LIMIT 1);
SET @match_hawks_1 = (SELECT match_id FROM matches WHERE team_one=@team_hawks AND team_two=@team_strikers AND sport_id=@hockey_id AND match_date = DATE_SUB(CURDATE(), INTERVAL 1 WEEK) LIMIT 1);

INSERT INTO player_scores (player_id, coach_id, match_id, goals, assists, custom_score) VALUES
  (@p_hamza_sarwar, @coach3_id, @match_hawks_9, 1, 1, 55),
  (@p_hamza_sarwar, @coach3_id, @match_hawks_7, 2, 0, 61),
  (@p_hamza_sarwar, @coach3_id, @match_hawks_5, 1, 2, 67),
  (@p_hamza_sarwar, @coach3_id, @match_hawks_3, 2, 1, 73),
  (@p_hamza_sarwar, @coach3_id, @match_hawks_1, 3, 1, 79),
  (@p_owais_tariq, @coach3_id, @match_hawks_9, 0, 2, 50),
  (@p_owais_tariq, @coach3_id, @match_hawks_7, 1, 1, 56),
  (@p_owais_tariq, @coach3_id, @match_hawks_5, 1, 1, 62),
  (@p_owais_tariq, @coach3_id, @match_hawks_3, 1, 2, 68),
  (@p_owais_tariq, @coach3_id, @match_hawks_1, 2, 1, 74),
  (@p_rayyan_malik, @coach3_id, @match_hawks_9, 0, 0, 45),
  (@p_rayyan_malik, @coach3_id, @match_hawks_7, 0, 1, 52),
  (@p_rayyan_malik, @coach3_id, @match_hawks_5, 1, 0, 59),
  (@p_rayyan_malik, @coach3_id, @match_hawks_3, 0, 1, 66),
  (@p_rayyan_malik, @coach3_id, @match_hawks_1, 1, 1, 73),
  (@p_ibrahim_nasir, @coach3_id, @match_hawks_9, 1, 0, 48),
  (@p_ibrahim_nasir, @coach3_id, @match_hawks_7, 1, 0, 54),
  (@p_ibrahim_nasir, @coach3_id, @match_hawks_5, 0, 1, 60),
  (@p_ibrahim_nasir, @coach3_id, @match_hawks_3, 1, 0, 66),
  (@p_ibrahim_nasir, @coach3_id, @match_hawks_1, 1, 1, 72)
ON DUPLICATE KEY UPDATE goals=VALUES(goals), assists=VALUES(assists), custom_score=VALUES(custom_score);

INSERT INTO player_ratings (player_id, coach_id, rating, review)
SELECT @p_hamza_sarwar, @coach3_id, 5, 'Excellent striker, sharp finishing all season.'
WHERE NOT EXISTS (SELECT 1 FROM player_ratings WHERE player_id=@p_hamza_sarwar AND coach_id=@coach3_id);
INSERT INTO player_ratings (player_id, coach_id, rating, review)
SELECT @p_owais_tariq, @coach3_id, 4, 'Great playmaker, creates space well.'
WHERE NOT EXISTS (SELECT 1 FROM player_ratings WHERE player_id=@p_owais_tariq AND coach_id=@coach3_id);
INSERT INTO player_ratings (player_id, coach_id, rating, review)
SELECT @p_rayyan_malik, @coach3_id, 3, 'Solid defensive positioning.'
WHERE NOT EXISTS (SELECT 1 FROM player_ratings WHERE player_id=@p_rayyan_malik AND coach_id=@coach3_id);
INSERT INTO player_ratings (player_id, coach_id, rating, review)
SELECT @p_ibrahim_nasir, @coach3_id, 4, 'Consistent goal threat.'
WHERE NOT EXISTS (SELECT 1 FROM player_ratings WHERE player_id=@p_ibrahim_nasir AND coach_id=@coach3_id);

-- Done. Log in as admin -> Performance -> Recalculate.
