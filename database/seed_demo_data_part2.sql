-- ================================================================
--  SPORTS MANAGEMENT SYSTEM — DEMO DATA SEED (PART 2 — full coverage)
--  ------------------------------------------------------------
--  Builds on seed_demo_data.sql. Adds two more sports (Volleyball,
--  Racing) with their own coach + team + rival + roster, and —
--  for ALL FOUR sports — a run of 5 completed matches per rivalry
--  spaced over the last ~9 weeks, each with real per-player scores
--  so that after recalculation every seeded player has a genuine
--  performance TREND across previous matches (not just one data
--  point), which is what the player dashboard's trend chart and
--  performance/player.php's history chart both plot.
--
--  Run this AFTER seed_demo_data.sql:
--    mysql -u root -p sports_management_system < database/seed_demo_data.sql
--    mysql -u root -p sports_management_system < database/seed_demo_data_part2.sql
--
--  Then log in as admin -> Performance -> Recalculate so the
--  scoring engine turns these matches into performance_analysis
--  and player_performance_history rows.
--
--  SAFE TO RE-RUN — every insert is guarded.
--  Demo password for every new account: Demo@1234
-- ================================================================

USE sports_management_system;
SET @demo_password = '$2b$10$nZBuncC3UO1M.OG10ablmuWZvlH0oeDoFzVzYGcmUjcd01Bm8wQVK';

-- ----------------------------------------------------------------
-- 1. Sports + 2 more coaches (Volleyball, Racing)
-- ----------------------------------------------------------------
INSERT IGNORE INTO sports (sport_name) VALUES ('Volleyball'), ('Racing');

SET @cricket_id    = (SELECT sport_id FROM sports WHERE sport_name = 'Cricket' LIMIT 1);
SET @football_id   = (SELECT sport_id FROM sports WHERE sport_name = 'Football' LIMIT 1);
SET @volleyball_id = (SELECT sport_id FROM sports WHERE sport_name = 'Volleyball' LIMIT 1);
SET @racing_id     = (SELECT sport_id FROM sports WHERE sport_name = 'Racing' LIMIT 1);

INSERT INTO coaches (sport_id, full_name, email, password, phone, experience, qualification, status) VALUES
  (@volleyball_id, 'Coach Sana Malik',  'coach3@gmail.com', @demo_password, '03011122235', '5 years', 'BS Physical Education', 'active'),
  (@racing_id,     'Coach Faisal Khan', 'coach4@gmail.com', @demo_password, '03011122236', '6 years', 'Certified Athletics Coach', 'active')
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name);

SET @coach1_id = (SELECT coach_id FROM coaches WHERE email = 'coach1@gmail.com' LIMIT 1);
SET @coach2_id = (SELECT coach_id FROM coaches WHERE email = 'coach2@gmail.com' LIMIT 1);
SET @coach3_id = (SELECT coach_id FROM coaches WHERE email = 'coach3@gmail.com' LIMIT 1);
SET @coach4_id = (SELECT coach_id FROM coaches WHERE email = 'coach4@gmail.com' LIMIT 1);

-- ----------------------------------------------------------------
-- 2. Players — 2 more for Cricket's LION squad, 4 new for a
--    Volleyball squad, 4 new for a Racing squad. (Football's
--    EAGLES roster from Part 1 already has 4 players.)
-- ----------------------------------------------------------------
INSERT INTO players (coach_id, sport_id, full_name, roll_no, email, password, phone, age, gender, blood_group, height, weight, status)
VALUES
  (@coach1_id, @cricket_id, 'Ahmed Sarfaraz', 'CRK-2026-103', 'ahmed.sarfaraz@student.edu.pk', @demo_password, '03013340009', 21, 'male', 'B+', 177.0, 70.0, 'active'),
  (@coach1_id, @cricket_id, 'Kamran Nasir', 'CRK-2026-104', 'kamran.nasir@student.edu.pk', @demo_password, '03013340010', 22, 'male', 'O+', 179.0, 73.0, 'active'),
  (@coach3_id, @volleyball_id, 'Ali Raza', 'VOL-2026-101', 'ali.raza.vb@student.edu.pk', @demo_password, '03013340001', 20, 'male', 'A+', 182.0, 75.0, 'active'),
  (@coach3_id, @volleyball_id, 'Fahad Nasir', 'VOL-2026-102', 'fahad.nasir@student.edu.pk', @demo_password, '03013340002', 21, 'male', 'B+', 185.0, 78.0, 'active'),
  (@coach3_id, @volleyball_id, 'Zeeshan Iqbal', 'VOL-2026-103', 'zeeshan.iqbal@student.edu.pk', @demo_password, '03013340003', 22, 'male', 'O+', 180.0, 74.0, 'active'),
  (@coach3_id, @volleyball_id, 'Waqas Ahmed', 'VOL-2026-104', 'waqas.ahmed@student.edu.pk', @demo_password, '03013340004', 20, 'male', 'AB+', 178.0, 72.0, 'active'),
  (@coach4_id, @racing_id, 'Imran Sheikh', 'RAC-2026-101', 'imran.sheikh@student.edu.pk', @demo_password, '03013340005', 21, 'male', 'O+', 172.0, 62.0, 'active'),
  (@coach4_id, @racing_id, 'Talha Riaz', 'RAC-2026-102', 'talha.riaz@student.edu.pk', @demo_password, '03013340006', 20, 'male', 'B+', 174.0, 64.0, 'active'),
  (@coach4_id, @racing_id, 'Noman Aslam', 'RAC-2026-103', 'noman.aslam@student.edu.pk', @demo_password, '03013340007', 22, 'male', 'A-', 176.0, 66.0, 'active'),
  (@coach4_id, @racing_id, 'Hassan Javed', 'RAC-2026-104', 'hassan.javed@student.edu.pk', @demo_password, '03013340008', 21, 'male', 'O-', 173.0, 63.0, 'active')
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name);

SET @p_ahmed_sarfaraz = (SELECT player_id FROM players WHERE email = 'ahmed.sarfaraz@student.edu.pk' LIMIT 1);
SET @p_kamran_nasir = (SELECT player_id FROM players WHERE email = 'kamran.nasir@student.edu.pk' LIMIT 1);
SET @p_ali_raza = (SELECT player_id FROM players WHERE email = 'ali.raza.vb@student.edu.pk' LIMIT 1);
SET @p_fahad_nasir = (SELECT player_id FROM players WHERE email = 'fahad.nasir@student.edu.pk' LIMIT 1);
SET @p_zeeshan_iqbal = (SELECT player_id FROM players WHERE email = 'zeeshan.iqbal@student.edu.pk' LIMIT 1);
SET @p_waqas_ahmed = (SELECT player_id FROM players WHERE email = 'waqas.ahmed@student.edu.pk' LIMIT 1);
SET @p_imran_sheikh = (SELECT player_id FROM players WHERE email = 'imran.sheikh@student.edu.pk' LIMIT 1);
SET @p_talha_riaz = (SELECT player_id FROM players WHERE email = 'talha.riaz@student.edu.pk' LIMIT 1);
SET @p_noman_aslam = (SELECT player_id FROM players WHERE email = 'noman.aslam@student.edu.pk' LIMIT 1);
SET @p_hassan_javed = (SELECT player_id FROM players WHERE email = 'hassan.javed@student.edu.pk' LIMIT 1);

-- Existing Football roster from Part 1
SET @p_usman_tariq = (SELECT player_id FROM players WHERE email = 'usman.tariq@student.edu.pk' LIMIT 1);
SET @p_hamza_iqbal = (SELECT player_id FROM players WHERE email = 'hamza.iqbal@student.edu.pk' LIMIT 1);
SET @p_danish_aziz = (SELECT player_id FROM players WHERE email = 'danish.aziz@student.edu.pk' LIMIT 1);
SET @p_saad_yousaf = (SELECT player_id FROM players WHERE email = 'saad.yousaf@student.edu.pk' LIMIT 1);
SET @p_bilal_hassan = (SELECT player_id FROM players WHERE email = 'bilal.hassan@student.edu.pk' LIMIT 1);
SET @p_zain_malik = (SELECT player_id FROM players WHERE email = 'zain.malik@student.edu.pk' LIMIT 1);

-- ----------------------------------------------------------------
-- 3. Teams — new squads for Volleyball + Racing, plus their rivals
-- ----------------------------------------------------------------
INSERT INTO teams (sport_id, coach_id, team_name)
SELECT @volleyball_id, @coach3_id, 'SPIKERS' WHERE NOT EXISTS (SELECT 1 FROM teams WHERE team_name = 'SPIKERS');
INSERT INTO teams (sport_id, coach_id, team_name)
SELECT @volleyball_id, NULL, 'THUNDER' WHERE NOT EXISTS (SELECT 1 FROM teams WHERE team_name = 'THUNDER');
INSERT INTO teams (sport_id, coach_id, team_name)
SELECT @racing_id, @coach4_id, 'SPRINTERS' WHERE NOT EXISTS (SELECT 1 FROM teams WHERE team_name = 'SPRINTERS');
INSERT INTO teams (sport_id, coach_id, team_name)
SELECT @racing_id, NULL, 'BLAZE' WHERE NOT EXISTS (SELECT 1 FROM teams WHERE team_name = 'BLAZE');

SET @team_lion      = (SELECT team_id FROM teams WHERE team_name = 'LION' LIMIT 1);
SET @team_tigers    = (SELECT team_id FROM teams WHERE team_name = 'TIGERS' LIMIT 1);
SET @team_eagles    = (SELECT team_id FROM teams WHERE team_name = 'EAGLES' LIMIT 1);
SET @team_falcons   = (SELECT team_id FROM teams WHERE team_name = 'FALCONS' LIMIT 1);
SET @team_spikers   = (SELECT team_id FROM teams WHERE team_name = 'SPIKERS' LIMIT 1);
SET @team_thunder   = (SELECT team_id FROM teams WHERE team_name = 'THUNDER' LIMIT 1);
SET @team_sprinters = (SELECT team_id FROM teams WHERE team_name = 'SPRINTERS' LIMIT 1);
SET @team_blaze     = (SELECT team_id FROM teams WHERE team_name = 'BLAZE' LIMIT 1);

UPDATE teams SET captain_id = @p_ali_raza, vice_captain_id = @p_fahad_nasir WHERE team_id = @team_spikers;
UPDATE teams SET captain_id = @p_imran_sheikh, vice_captain_id = @p_talha_riaz WHERE team_id = @team_sprinters;

UPDATE players SET team_id = @team_lion WHERE player_id IN (@p_ahmed_sarfaraz, @p_kamran_nasir);
UPDATE players SET team_id = @team_spikers WHERE player_id IN (@p_ali_raza, @p_fahad_nasir, @p_zeeshan_iqbal, @p_waqas_ahmed);
UPDATE players SET team_id = @team_sprinters WHERE player_id IN (@p_imran_sheikh, @p_talha_riaz, @p_noman_aslam, @p_hassan_javed);

INSERT IGNORE INTO team_players (team_id, player_id) VALUES
  (@team_lion, @p_ahmed_sarfaraz), (@team_lion, @p_kamran_nasir),
  (@team_spikers, @p_ali_raza), (@team_spikers, @p_fahad_nasir), (@team_spikers, @p_zeeshan_iqbal), (@team_spikers, @p_waqas_ahmed),
  (@team_sprinters, @p_imran_sheikh), (@team_sprinters, @p_talha_riaz), (@team_sprinters, @p_noman_aslam), (@team_sprinters, @p_hassan_javed);

-- ----------------------------------------------------------------
-- 4. Events for the 2 new sports (Part 1 already added Cricket +
--    Football events)
-- ----------------------------------------------------------------
INSERT INTO events (sport_id, coach_id, title, description, venue, event_date, start_time, status)
SELECT @volleyball_id, @coach3_id, 'Volleyball Smash Tournament', 'Inter-department volleyball knockout tournament.', 'Indoor Sports Complex', DATE_ADD(CURDATE(), INTERVAL 18 DAY), '15:00:00', 'Upcoming'
WHERE NOT EXISTS (SELECT 1 FROM events WHERE title = 'Volleyball Smash Tournament');

INSERT INTO events (sport_id, coach_id, title, description, venue, event_date, start_time, status)
SELECT @racing_id, @coach4_id, 'College Athletics Meet', 'Annual 100m/200m/400m track meet.', 'College Athletics Track', DATE_ADD(CURDATE(), INTERVAL 25 DAY), '08:00:00', 'Upcoming'
WHERE NOT EXISTS (SELECT 1 FROM events WHERE title = 'College Athletics Meet');

SET @event_volleyball = (SELECT event_id FROM events WHERE title = 'Volleyball Smash Tournament' LIMIT 1);
SET @event_racing = (SELECT event_id FROM events WHERE title = 'College Athletics Meet' LIMIT 1);

INSERT IGNORE INTO event_participants (event_id, player_id, participation_status) VALUES
  (@event_volleyball, @p_ali_raza, 'Approved'), (@event_volleyball, @p_fahad_nasir, 'Approved'),
  (@event_volleyball, @p_zeeshan_iqbal, 'Approved'), (@event_volleyball, @p_waqas_ahmed, 'Approved'),
  (@event_racing, @p_imran_sheikh, 'Approved'), (@event_racing, @p_talha_riaz, 'Approved'),
  (@event_racing, @p_noman_aslam, 'Approved'), (@event_racing, @p_hassan_javed, 'Approved');

-- ----------------------------------------------------------------
-- 5. Match HISTORY — 5 completed matches per rivalry, spaced over
--    the last ~9 weeks, plus 1 upcoming match each. Each match
--    gets real per-player scores below, giving every seeded
--    player a genuine multi-point performance trend.
-- ----------------------------------------------------------------
-- team_lion vs team_tigers
INSERT INTO matches (sport_id, team_one, team_two, venue, match_date, winner_team, status)
SELECT @cricket_id, @team_lion, @team_tigers, 'College Cricket Ground', DATE_SUB(CURDATE(), INTERVAL 9 WEEK), @team_lion, 'Completed'
WHERE NOT EXISTS (SELECT 1 FROM matches WHERE team_one=@team_lion AND team_two=@team_tigers AND sport_id=@cricket_id AND match_date = DATE_SUB(CURDATE(), INTERVAL 9 WEEK));
INSERT INTO matches (sport_id, team_one, team_two, venue, match_date, winner_team, status)
SELECT @cricket_id, @team_lion, @team_tigers, 'College Cricket Ground', DATE_SUB(CURDATE(), INTERVAL 7 WEEK), @team_tigers, 'Completed'
WHERE NOT EXISTS (SELECT 1 FROM matches WHERE team_one=@team_lion AND team_two=@team_tigers AND sport_id=@cricket_id AND match_date = DATE_SUB(CURDATE(), INTERVAL 7 WEEK));
INSERT INTO matches (sport_id, team_one, team_two, venue, match_date, winner_team, status)
SELECT @cricket_id, @team_lion, @team_tigers, 'College Cricket Ground', DATE_SUB(CURDATE(), INTERVAL 5 WEEK), @team_lion, 'Completed'
WHERE NOT EXISTS (SELECT 1 FROM matches WHERE team_one=@team_lion AND team_two=@team_tigers AND sport_id=@cricket_id AND match_date = DATE_SUB(CURDATE(), INTERVAL 5 WEEK));
INSERT INTO matches (sport_id, team_one, team_two, venue, match_date, winner_team, status)
SELECT @cricket_id, @team_lion, @team_tigers, 'College Cricket Ground', DATE_SUB(CURDATE(), INTERVAL 3 WEEK), @team_lion, 'Completed'
WHERE NOT EXISTS (SELECT 1 FROM matches WHERE team_one=@team_lion AND team_two=@team_tigers AND sport_id=@cricket_id AND match_date = DATE_SUB(CURDATE(), INTERVAL 3 WEEK));
INSERT INTO matches (sport_id, team_one, team_two, venue, match_date, winner_team, status)
SELECT @cricket_id, @team_lion, @team_tigers, 'College Cricket Ground', DATE_SUB(CURDATE(), INTERVAL 1 WEEK), @team_tigers, 'Completed'
WHERE NOT EXISTS (SELECT 1 FROM matches WHERE team_one=@team_lion AND team_two=@team_tigers AND sport_id=@cricket_id AND match_date = DATE_SUB(CURDATE(), INTERVAL 1 WEEK));
INSERT INTO matches (sport_id, team_one, team_two, venue, match_date, status)
SELECT @cricket_id, @team_lion, @team_tigers, 'College Cricket Ground', DATE_ADD(CURDATE(), INTERVAL 12 DAY), 'Upcoming'
WHERE NOT EXISTS (SELECT 1 FROM matches WHERE team_one=@team_lion AND team_two=@team_tigers AND sport_id=@cricket_id AND status='Upcoming');

-- team_eagles vs team_falcons
INSERT INTO matches (sport_id, team_one, team_two, venue, match_date, winner_team, status)
SELECT @football_id, @team_eagles, @team_falcons, 'Main Football Ground', DATE_SUB(CURDATE(), INTERVAL 9 WEEK), @team_eagles, 'Completed'
WHERE NOT EXISTS (SELECT 1 FROM matches WHERE team_one=@team_eagles AND team_two=@team_falcons AND sport_id=@football_id AND match_date = DATE_SUB(CURDATE(), INTERVAL 9 WEEK));
INSERT INTO matches (sport_id, team_one, team_two, venue, match_date, winner_team, status)
SELECT @football_id, @team_eagles, @team_falcons, 'Main Football Ground', DATE_SUB(CURDATE(), INTERVAL 7 WEEK), @team_falcons, 'Completed'
WHERE NOT EXISTS (SELECT 1 FROM matches WHERE team_one=@team_eagles AND team_two=@team_falcons AND sport_id=@football_id AND match_date = DATE_SUB(CURDATE(), INTERVAL 7 WEEK));
INSERT INTO matches (sport_id, team_one, team_two, venue, match_date, winner_team, status)
SELECT @football_id, @team_eagles, @team_falcons, 'Main Football Ground', DATE_SUB(CURDATE(), INTERVAL 5 WEEK), @team_eagles, 'Completed'
WHERE NOT EXISTS (SELECT 1 FROM matches WHERE team_one=@team_eagles AND team_two=@team_falcons AND sport_id=@football_id AND match_date = DATE_SUB(CURDATE(), INTERVAL 5 WEEK));
INSERT INTO matches (sport_id, team_one, team_two, venue, match_date, winner_team, status)
SELECT @football_id, @team_eagles, @team_falcons, 'Main Football Ground', DATE_SUB(CURDATE(), INTERVAL 3 WEEK), @team_eagles, 'Completed'
WHERE NOT EXISTS (SELECT 1 FROM matches WHERE team_one=@team_eagles AND team_two=@team_falcons AND sport_id=@football_id AND match_date = DATE_SUB(CURDATE(), INTERVAL 3 WEEK));
INSERT INTO matches (sport_id, team_one, team_two, venue, match_date, winner_team, status)
SELECT @football_id, @team_eagles, @team_falcons, 'Main Football Ground', DATE_SUB(CURDATE(), INTERVAL 1 WEEK), @team_falcons, 'Completed'
WHERE NOT EXISTS (SELECT 1 FROM matches WHERE team_one=@team_eagles AND team_two=@team_falcons AND sport_id=@football_id AND match_date = DATE_SUB(CURDATE(), INTERVAL 1 WEEK));
INSERT INTO matches (sport_id, team_one, team_two, venue, match_date, status)
SELECT @football_id, @team_eagles, @team_falcons, 'Main Football Ground', DATE_ADD(CURDATE(), INTERVAL 12 DAY), 'Upcoming'
WHERE NOT EXISTS (SELECT 1 FROM matches WHERE team_one=@team_eagles AND team_two=@team_falcons AND sport_id=@football_id AND status='Upcoming');

-- team_spikers vs team_thunder
INSERT INTO matches (sport_id, team_one, team_two, venue, match_date, winner_team, status)
SELECT @volleyball_id, @team_spikers, @team_thunder, 'Indoor Sports Complex', DATE_SUB(CURDATE(), INTERVAL 9 WEEK), @team_spikers, 'Completed'
WHERE NOT EXISTS (SELECT 1 FROM matches WHERE team_one=@team_spikers AND team_two=@team_thunder AND sport_id=@volleyball_id AND match_date = DATE_SUB(CURDATE(), INTERVAL 9 WEEK));
INSERT INTO matches (sport_id, team_one, team_two, venue, match_date, winner_team, status)
SELECT @volleyball_id, @team_spikers, @team_thunder, 'Indoor Sports Complex', DATE_SUB(CURDATE(), INTERVAL 7 WEEK), @team_thunder, 'Completed'
WHERE NOT EXISTS (SELECT 1 FROM matches WHERE team_one=@team_spikers AND team_two=@team_thunder AND sport_id=@volleyball_id AND match_date = DATE_SUB(CURDATE(), INTERVAL 7 WEEK));
INSERT INTO matches (sport_id, team_one, team_two, venue, match_date, winner_team, status)
SELECT @volleyball_id, @team_spikers, @team_thunder, 'Indoor Sports Complex', DATE_SUB(CURDATE(), INTERVAL 5 WEEK), @team_spikers, 'Completed'
WHERE NOT EXISTS (SELECT 1 FROM matches WHERE team_one=@team_spikers AND team_two=@team_thunder AND sport_id=@volleyball_id AND match_date = DATE_SUB(CURDATE(), INTERVAL 5 WEEK));
INSERT INTO matches (sport_id, team_one, team_two, venue, match_date, winner_team, status)
SELECT @volleyball_id, @team_spikers, @team_thunder, 'Indoor Sports Complex', DATE_SUB(CURDATE(), INTERVAL 3 WEEK), @team_spikers, 'Completed'
WHERE NOT EXISTS (SELECT 1 FROM matches WHERE team_one=@team_spikers AND team_two=@team_thunder AND sport_id=@volleyball_id AND match_date = DATE_SUB(CURDATE(), INTERVAL 3 WEEK));
INSERT INTO matches (sport_id, team_one, team_two, venue, match_date, winner_team, status)
SELECT @volleyball_id, @team_spikers, @team_thunder, 'Indoor Sports Complex', DATE_SUB(CURDATE(), INTERVAL 1 WEEK), @team_thunder, 'Completed'
WHERE NOT EXISTS (SELECT 1 FROM matches WHERE team_one=@team_spikers AND team_two=@team_thunder AND sport_id=@volleyball_id AND match_date = DATE_SUB(CURDATE(), INTERVAL 1 WEEK));
INSERT INTO matches (sport_id, team_one, team_two, venue, match_date, status)
SELECT @volleyball_id, @team_spikers, @team_thunder, 'Indoor Sports Complex', DATE_ADD(CURDATE(), INTERVAL 12 DAY), 'Upcoming'
WHERE NOT EXISTS (SELECT 1 FROM matches WHERE team_one=@team_spikers AND team_two=@team_thunder AND sport_id=@volleyball_id AND status='Upcoming');

-- team_sprinters vs team_blaze
INSERT INTO matches (sport_id, team_one, team_two, venue, match_date, winner_team, status)
SELECT @racing_id, @team_sprinters, @team_blaze, 'College Athletics Track', DATE_SUB(CURDATE(), INTERVAL 9 WEEK), @team_sprinters, 'Completed'
WHERE NOT EXISTS (SELECT 1 FROM matches WHERE team_one=@team_sprinters AND team_two=@team_blaze AND sport_id=@racing_id AND match_date = DATE_SUB(CURDATE(), INTERVAL 9 WEEK));
INSERT INTO matches (sport_id, team_one, team_two, venue, match_date, winner_team, status)
SELECT @racing_id, @team_sprinters, @team_blaze, 'College Athletics Track', DATE_SUB(CURDATE(), INTERVAL 7 WEEK), @team_blaze, 'Completed'
WHERE NOT EXISTS (SELECT 1 FROM matches WHERE team_one=@team_sprinters AND team_two=@team_blaze AND sport_id=@racing_id AND match_date = DATE_SUB(CURDATE(), INTERVAL 7 WEEK));
INSERT INTO matches (sport_id, team_one, team_two, venue, match_date, winner_team, status)
SELECT @racing_id, @team_sprinters, @team_blaze, 'College Athletics Track', DATE_SUB(CURDATE(), INTERVAL 5 WEEK), @team_sprinters, 'Completed'
WHERE NOT EXISTS (SELECT 1 FROM matches WHERE team_one=@team_sprinters AND team_two=@team_blaze AND sport_id=@racing_id AND match_date = DATE_SUB(CURDATE(), INTERVAL 5 WEEK));
INSERT INTO matches (sport_id, team_one, team_two, venue, match_date, winner_team, status)
SELECT @racing_id, @team_sprinters, @team_blaze, 'College Athletics Track', DATE_SUB(CURDATE(), INTERVAL 3 WEEK), @team_sprinters, 'Completed'
WHERE NOT EXISTS (SELECT 1 FROM matches WHERE team_one=@team_sprinters AND team_two=@team_blaze AND sport_id=@racing_id AND match_date = DATE_SUB(CURDATE(), INTERVAL 3 WEEK));
INSERT INTO matches (sport_id, team_one, team_two, venue, match_date, winner_team, status)
SELECT @racing_id, @team_sprinters, @team_blaze, 'College Athletics Track', DATE_SUB(CURDATE(), INTERVAL 1 WEEK), @team_blaze, 'Completed'
WHERE NOT EXISTS (SELECT 1 FROM matches WHERE team_one=@team_sprinters AND team_two=@team_blaze AND sport_id=@racing_id AND match_date = DATE_SUB(CURDATE(), INTERVAL 1 WEEK));
INSERT INTO matches (sport_id, team_one, team_two, venue, match_date, status)
SELECT @racing_id, @team_sprinters, @team_blaze, 'College Athletics Track', DATE_ADD(CURDATE(), INTERVAL 12 DAY), 'Upcoming'
WHERE NOT EXISTS (SELECT 1 FROM matches WHERE team_one=@team_sprinters AND team_two=@team_blaze AND sport_id=@racing_id AND status='Upcoming');

SET @match_team_lion_9 = (SELECT match_id FROM matches WHERE team_one=@team_lion AND team_two=@team_tigers AND sport_id=@cricket_id AND match_date = DATE_SUB(CURDATE(), INTERVAL 9 WEEK) LIMIT 1);
SET @match_team_lion_7 = (SELECT match_id FROM matches WHERE team_one=@team_lion AND team_two=@team_tigers AND sport_id=@cricket_id AND match_date = DATE_SUB(CURDATE(), INTERVAL 7 WEEK) LIMIT 1);
SET @match_team_lion_5 = (SELECT match_id FROM matches WHERE team_one=@team_lion AND team_two=@team_tigers AND sport_id=@cricket_id AND match_date = DATE_SUB(CURDATE(), INTERVAL 5 WEEK) LIMIT 1);
SET @match_team_lion_3 = (SELECT match_id FROM matches WHERE team_one=@team_lion AND team_two=@team_tigers AND sport_id=@cricket_id AND match_date = DATE_SUB(CURDATE(), INTERVAL 3 WEEK) LIMIT 1);
SET @match_team_lion_1 = (SELECT match_id FROM matches WHERE team_one=@team_lion AND team_two=@team_tigers AND sport_id=@cricket_id AND match_date = DATE_SUB(CURDATE(), INTERVAL 1 WEEK) LIMIT 1);
SET @match_team_eagles_9 = (SELECT match_id FROM matches WHERE team_one=@team_eagles AND team_two=@team_falcons AND sport_id=@football_id AND match_date = DATE_SUB(CURDATE(), INTERVAL 9 WEEK) LIMIT 1);
SET @match_team_eagles_7 = (SELECT match_id FROM matches WHERE team_one=@team_eagles AND team_two=@team_falcons AND sport_id=@football_id AND match_date = DATE_SUB(CURDATE(), INTERVAL 7 WEEK) LIMIT 1);
SET @match_team_eagles_5 = (SELECT match_id FROM matches WHERE team_one=@team_eagles AND team_two=@team_falcons AND sport_id=@football_id AND match_date = DATE_SUB(CURDATE(), INTERVAL 5 WEEK) LIMIT 1);
SET @match_team_eagles_3 = (SELECT match_id FROM matches WHERE team_one=@team_eagles AND team_two=@team_falcons AND sport_id=@football_id AND match_date = DATE_SUB(CURDATE(), INTERVAL 3 WEEK) LIMIT 1);
SET @match_team_eagles_1 = (SELECT match_id FROM matches WHERE team_one=@team_eagles AND team_two=@team_falcons AND sport_id=@football_id AND match_date = DATE_SUB(CURDATE(), INTERVAL 1 WEEK) LIMIT 1);
SET @match_team_spikers_9 = (SELECT match_id FROM matches WHERE team_one=@team_spikers AND team_two=@team_thunder AND sport_id=@volleyball_id AND match_date = DATE_SUB(CURDATE(), INTERVAL 9 WEEK) LIMIT 1);
SET @match_team_spikers_7 = (SELECT match_id FROM matches WHERE team_one=@team_spikers AND team_two=@team_thunder AND sport_id=@volleyball_id AND match_date = DATE_SUB(CURDATE(), INTERVAL 7 WEEK) LIMIT 1);
SET @match_team_spikers_5 = (SELECT match_id FROM matches WHERE team_one=@team_spikers AND team_two=@team_thunder AND sport_id=@volleyball_id AND match_date = DATE_SUB(CURDATE(), INTERVAL 5 WEEK) LIMIT 1);
SET @match_team_spikers_3 = (SELECT match_id FROM matches WHERE team_one=@team_spikers AND team_two=@team_thunder AND sport_id=@volleyball_id AND match_date = DATE_SUB(CURDATE(), INTERVAL 3 WEEK) LIMIT 1);
SET @match_team_spikers_1 = (SELECT match_id FROM matches WHERE team_one=@team_spikers AND team_two=@team_thunder AND sport_id=@volleyball_id AND match_date = DATE_SUB(CURDATE(), INTERVAL 1 WEEK) LIMIT 1);
SET @match_team_sprinters_9 = (SELECT match_id FROM matches WHERE team_one=@team_sprinters AND team_two=@team_blaze AND sport_id=@racing_id AND match_date = DATE_SUB(CURDATE(), INTERVAL 9 WEEK) LIMIT 1);
SET @match_team_sprinters_7 = (SELECT match_id FROM matches WHERE team_one=@team_sprinters AND team_two=@team_blaze AND sport_id=@racing_id AND match_date = DATE_SUB(CURDATE(), INTERVAL 7 WEEK) LIMIT 1);
SET @match_team_sprinters_5 = (SELECT match_id FROM matches WHERE team_one=@team_sprinters AND team_two=@team_blaze AND sport_id=@racing_id AND match_date = DATE_SUB(CURDATE(), INTERVAL 5 WEEK) LIMIT 1);
SET @match_team_sprinters_3 = (SELECT match_id FROM matches WHERE team_one=@team_sprinters AND team_two=@team_blaze AND sport_id=@racing_id AND match_date = DATE_SUB(CURDATE(), INTERVAL 3 WEEK) LIMIT 1);
SET @match_team_sprinters_1 = (SELECT match_id FROM matches WHERE team_one=@team_sprinters AND team_two=@team_blaze AND sport_id=@racing_id AND match_date = DATE_SUB(CURDATE(), INTERVAL 1 WEEK) LIMIT 1);

-- ----------------------------------------------------------------
-- 6. Per-match player scores — a gentle improving trend across
--    the 5 matches for every roster player.
-- ----------------------------------------------------------------
-- Cricket (runs / wickets / catches)
INSERT INTO player_scores (player_id, coach_id, match_id, runs, wickets, catches, custom_score) VALUES
  (@p_bilal_hassan, @coach1_id, @match_team_lion_9, 22, 0, 1, 52),
  (@p_bilal_hassan, @coach1_id, @match_team_lion_7, 29, 1, 0, 59),
  (@p_bilal_hassan, @coach1_id, @match_team_lion_5, 36, 0, 2, 66),
  (@p_bilal_hassan, @coach1_id, @match_team_lion_3, 43, 2, 1, 73),
  (@p_bilal_hassan, @coach1_id, @match_team_lion_1, 50, 1, 2, 80),
  (@p_zain_malik, @coach1_id, @match_team_lion_9, 18, 0, 1, 48),
  (@p_zain_malik, @coach1_id, @match_team_lion_7, 24, 1, 0, 55),
  (@p_zain_malik, @coach1_id, @match_team_lion_5, 30, 0, 2, 62),
  (@p_zain_malik, @coach1_id, @match_team_lion_3, 36, 2, 1, 69),
  (@p_zain_malik, @coach1_id, @match_team_lion_1, 42, 1, 2, 76),
  (@p_ahmed_sarfaraz, @coach1_id, @match_team_lion_9, 25, 0, 1, 55),
  (@p_ahmed_sarfaraz, @coach1_id, @match_team_lion_7, 30, 1, 0, 61),
  (@p_ahmed_sarfaraz, @coach1_id, @match_team_lion_5, 35, 0, 2, 67),
  (@p_ahmed_sarfaraz, @coach1_id, @match_team_lion_3, 40, 2, 1, 73),
  (@p_ahmed_sarfaraz, @coach1_id, @match_team_lion_1, 45, 1, 2, 79),
  (@p_kamran_nasir, @coach1_id, @match_team_lion_9, 15, 0, 1, 45),
  (@p_kamran_nasir, @coach1_id, @match_team_lion_7, 23, 1, 0, 53),
  (@p_kamran_nasir, @coach1_id, @match_team_lion_5, 31, 0, 2, 61),
  (@p_kamran_nasir, @coach1_id, @match_team_lion_3, 39, 2, 1, 69),
  (@p_kamran_nasir, @coach1_id, @match_team_lion_1, 47, 1, 2, 77)
ON DUPLICATE KEY UPDATE runs=VALUES(runs), wickets=VALUES(wickets), catches=VALUES(catches), custom_score=VALUES(custom_score);

-- Football (goals / assists)
INSERT INTO player_scores (player_id, coach_id, match_id, goals, assists, custom_score) VALUES
  (@p_usman_tariq, @coach2_id, @match_team_eagles_9, 1, 1, 55),
  (@p_usman_tariq, @coach2_id, @match_team_eagles_7, 2, 0, 61),
  (@p_usman_tariq, @coach2_id, @match_team_eagles_5, 1, 2, 67),
  (@p_usman_tariq, @coach2_id, @match_team_eagles_3, 2, 1, 73),
  (@p_usman_tariq, @coach2_id, @match_team_eagles_1, 3, 1, 79),
  (@p_hamza_iqbal, @coach2_id, @match_team_eagles_9, 0, 2, 50),
  (@p_hamza_iqbal, @coach2_id, @match_team_eagles_7, 1, 1, 56),
  (@p_hamza_iqbal, @coach2_id, @match_team_eagles_5, 1, 1, 62),
  (@p_hamza_iqbal, @coach2_id, @match_team_eagles_3, 1, 2, 68),
  (@p_hamza_iqbal, @coach2_id, @match_team_eagles_1, 2, 1, 74),
  (@p_danish_aziz, @coach2_id, @match_team_eagles_9, 0, 0, 45),
  (@p_danish_aziz, @coach2_id, @match_team_eagles_7, 0, 1, 52),
  (@p_danish_aziz, @coach2_id, @match_team_eagles_5, 1, 0, 59),
  (@p_danish_aziz, @coach2_id, @match_team_eagles_3, 0, 1, 66),
  (@p_danish_aziz, @coach2_id, @match_team_eagles_1, 1, 1, 73),
  (@p_saad_yousaf, @coach2_id, @match_team_eagles_9, 1, 0, 48),
  (@p_saad_yousaf, @coach2_id, @match_team_eagles_7, 1, 0, 54),
  (@p_saad_yousaf, @coach2_id, @match_team_eagles_5, 0, 1, 60),
  (@p_saad_yousaf, @coach2_id, @match_team_eagles_3, 1, 0, 66),
  (@p_saad_yousaf, @coach2_id, @match_team_eagles_1, 1, 1, 72)
ON DUPLICATE KEY UPDATE goals=VALUES(goals), assists=VALUES(assists), custom_score=VALUES(custom_score);

-- Volleyball (points)
INSERT INTO player_scores (player_id, coach_id, match_id, points, custom_score) VALUES
  (@p_ali_raza, @coach3_id, @match_team_spikers_9, 12, 50),
  (@p_ali_raza, @coach3_id, @match_team_spikers_7, 15, 57),
  (@p_ali_raza, @coach3_id, @match_team_spikers_5, 18, 64),
  (@p_ali_raza, @coach3_id, @match_team_spikers_3, 21, 71),
  (@p_ali_raza, @coach3_id, @match_team_spikers_1, 24, 78),
  (@p_fahad_nasir, @coach3_id, @match_team_spikers_9, 10, 46),
  (@p_fahad_nasir, @coach3_id, @match_team_spikers_7, 13, 53),
  (@p_fahad_nasir, @coach3_id, @match_team_spikers_5, 16, 60),
  (@p_fahad_nasir, @coach3_id, @match_team_spikers_3, 19, 67),
  (@p_fahad_nasir, @coach3_id, @match_team_spikers_1, 22, 74),
  (@p_zeeshan_iqbal, @coach3_id, @match_team_spikers_9, 14, 55),
  (@p_zeeshan_iqbal, @coach3_id, @match_team_spikers_7, 16, 61),
  (@p_zeeshan_iqbal, @coach3_id, @match_team_spikers_5, 18, 67),
  (@p_zeeshan_iqbal, @coach3_id, @match_team_spikers_3, 20, 73),
  (@p_zeeshan_iqbal, @coach3_id, @match_team_spikers_1, 22, 79),
  (@p_waqas_ahmed, @coach3_id, @match_team_spikers_9, 9, 42),
  (@p_waqas_ahmed, @coach3_id, @match_team_spikers_7, 13, 50),
  (@p_waqas_ahmed, @coach3_id, @match_team_spikers_5, 17, 58),
  (@p_waqas_ahmed, @coach3_id, @match_team_spikers_3, 21, 66),
  (@p_waqas_ahmed, @coach3_id, @match_team_spikers_1, 25, 74)
ON DUPLICATE KEY UPDATE points=VALUES(points), custom_score=VALUES(custom_score);

-- Racing (race_time in seconds — lower is better)
INSERT INTO player_scores (player_id, coach_id, match_id, race_time, custom_score) VALUES
  (@p_imran_sheikh, @coach4_id, @match_team_sprinters_9, 62.5, 48),
  (@p_imran_sheikh, @coach4_id, @match_team_sprinters_7, 60.5, 56),
  (@p_imran_sheikh, @coach4_id, @match_team_sprinters_5, 58.5, 64),
  (@p_imran_sheikh, @coach4_id, @match_team_sprinters_3, 56.5, 72),
  (@p_imran_sheikh, @coach4_id, @match_team_sprinters_1, 54.5, 80),
  (@p_talha_riaz, @coach4_id, @match_team_sprinters_9, 64.0, 44),
  (@p_talha_riaz, @coach4_id, @match_team_sprinters_7, 62.2, 52),
  (@p_talha_riaz, @coach4_id, @match_team_sprinters_5, 60.4, 60),
  (@p_talha_riaz, @coach4_id, @match_team_sprinters_3, 58.6, 68),
  (@p_talha_riaz, @coach4_id, @match_team_sprinters_1, 56.8, 76),
  (@p_noman_aslam, @coach4_id, @match_team_sprinters_9, 61.0, 52),
  (@p_noman_aslam, @coach4_id, @match_team_sprinters_7, 59.5, 59),
  (@p_noman_aslam, @coach4_id, @match_team_sprinters_5, 58.0, 66),
  (@p_noman_aslam, @coach4_id, @match_team_sprinters_3, 56.5, 73),
  (@p_noman_aslam, @coach4_id, @match_team_sprinters_1, 55.0, 80),
  (@p_hassan_javed, @coach4_id, @match_team_sprinters_9, 65.5, 40),
  (@p_hassan_javed, @coach4_id, @match_team_sprinters_7, 63.3, 49),
  (@p_hassan_javed, @coach4_id, @match_team_sprinters_5, 61.1, 58),
  (@p_hassan_javed, @coach4_id, @match_team_sprinters_3, 58.9, 67),
  (@p_hassan_javed, @coach4_id, @match_team_sprinters_1, 56.7, 76)
ON DUPLICATE KEY UPDATE race_time=VALUES(race_time), custom_score=VALUES(custom_score);

-- ----------------------------------------------------------------
-- 7. Coach ratings for every newly seeded player
-- ----------------------------------------------------------------
INSERT INTO player_ratings (player_id, coach_id, rating, review)
SELECT @p_ahmed_sarfaraz, @coach1_id, 4, 'Consistent run-scorer, good shot selection.'
WHERE NOT EXISTS (SELECT 1 FROM player_ratings WHERE player_id=@p_ahmed_sarfaraz AND coach_id=@coach1_id);
INSERT INTO player_ratings (player_id, coach_id, rating, review)
SELECT @p_kamran_nasir, @coach1_id, 3, 'Improving bowling accuracy match by match.'
WHERE NOT EXISTS (SELECT 1 FROM player_ratings WHERE player_id=@p_kamran_nasir AND coach_id=@coach1_id);
INSERT INTO player_ratings (player_id, coach_id, rating, review)
SELECT @p_ali_raza, @coach3_id, 5, 'Excellent spiker, strong team leader as captain.'
WHERE NOT EXISTS (SELECT 1 FROM player_ratings WHERE player_id=@p_ali_raza AND coach_id=@coach3_id);
INSERT INTO player_ratings (player_id, coach_id, rating, review)
SELECT @p_fahad_nasir, @coach3_id, 4, 'Reliable blocker, good court awareness.'
WHERE NOT EXISTS (SELECT 1 FROM player_ratings WHERE player_id=@p_fahad_nasir AND coach_id=@coach3_id);
INSERT INTO player_ratings (player_id, coach_id, rating, review)
SELECT @p_zeeshan_iqbal, @coach3_id, 4, 'Great serving accuracy.'
WHERE NOT EXISTS (SELECT 1 FROM player_ratings WHERE player_id=@p_zeeshan_iqbal AND coach_id=@coach3_id);
INSERT INTO player_ratings (player_id, coach_id, rating, review)
SELECT @p_waqas_ahmed, @coach3_id, 3, 'Developing well, needs more match time.'
WHERE NOT EXISTS (SELECT 1 FROM player_ratings WHERE player_id=@p_waqas_ahmed AND coach_id=@coach3_id);
INSERT INTO player_ratings (player_id, coach_id, rating, review)
SELECT @p_imran_sheikh, @coach4_id, 5, 'Fastest improvement in the squad this season.'
WHERE NOT EXISTS (SELECT 1 FROM player_ratings WHERE player_id=@p_imran_sheikh AND coach_id=@coach4_id);
INSERT INTO player_ratings (player_id, coach_id, rating, review)
SELECT @p_talha_riaz, @coach4_id, 4, 'Strong consistent sprint times.'
WHERE NOT EXISTS (SELECT 1 FROM player_ratings WHERE player_id=@p_talha_riaz AND coach_id=@coach4_id);
INSERT INTO player_ratings (player_id, coach_id, rating, review)
SELECT @p_noman_aslam, @coach4_id, 4, 'Great starts off the blocks.'
WHERE NOT EXISTS (SELECT 1 FROM player_ratings WHERE player_id=@p_noman_aslam AND coach_id=@coach4_id);
INSERT INTO player_ratings (player_id, coach_id, rating, review)
SELECT @p_hassan_javed, @coach4_id, 3, 'Steady progress, working on endurance.'
WHERE NOT EXISTS (SELECT 1 FROM player_ratings WHERE player_id=@p_hassan_javed AND coach_id=@coach4_id);

-- ----------------------------------------------------------------
-- Done. Log in as admin -> Performance -> Recalculate to turn all
-- of the above into performance_analysis + player_performance_history
-- rows (5 history points per seeded player = real trend charts).
-- ----------------------------------------------------------------
