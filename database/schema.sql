-- ================================================================
--  SPORTS MANAGEMENT SYSTEM — DATABASE SCHEMA
--  Module 2: Database Architecture & Project Configuration
--  Government M.A.O Graduate College Lahore
--  ----------------------------------------------------------------
--  Engine: InnoDB (row-level locking + foreign keys)
--  Charset: utf8mb4 / utf8mb4_unicode_ci (full Unicode, emoji-safe)
--  Normal form: 3NF — every non-key column depends on the whole key,
--  nothing but the key, and nothing outside the key.
--
--  Import: phpMyAdmin > Import, or:
--    mysql -u root -p < schema.sql
-- ================================================================

CREATE DATABASE IF NOT EXISTS sports_management_system
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE sports_management_system;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;


-- ================================================================
-- 1. SPORTS  (created first — almost everything else references it)
-- ================================================================
CREATE TABLE sports (
    sport_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sport_name   VARCHAR(50) NOT NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sport_name (sport_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ================================================================
-- 2. ADMINS
-- ================================================================
CREATE TABLE admins (
    admin_id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name      VARCHAR(100) NOT NULL,
    email          VARCHAR(120) NOT NULL,
    password       VARCHAR(255) NOT NULL COMMENT 'bcrypt hash via PHP password_hash()',
    phone          VARCHAR(20)  DEFAULT NULL,
    profile_image  VARCHAR(255) DEFAULT NULL,
    role           ENUM('super_admin','admin') NOT NULL DEFAULT 'admin',
    status         ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_admin_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ================================================================


-- ================================================================
-- 4. COACHES  — one coach belongs to exactly one sport
-- ================================================================
CREATE TABLE coaches (
    coach_id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sport_id       INT UNSIGNED NOT NULL,
    full_name      VARCHAR(100) NOT NULL,
    email          VARCHAR(120) NOT NULL,
    password       VARCHAR(255) NOT NULL,
    phone          VARCHAR(20)  DEFAULT NULL,
    experience     VARCHAR(50)  DEFAULT NULL COMMENT 'e.g. "6 years"',
    qualification  VARCHAR(150) DEFAULT NULL,
    profile_image  VARCHAR(255) DEFAULT NULL,
    status         ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_coach_email (email),
    KEY idx_coach_sport (sport_id),
    CONSTRAINT fk_coach_sport FOREIGN KEY (sport_id)
        REFERENCES sports(sport_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ================================================================
-- 5. PLAYERS  — one player belongs to one coach & one sport.
--    NOTE: team_id has NO foreign key yet. teams.captain_id and
--    teams.vice_captain_id reference players, so teams must be
--    created AFTER players — but players.team_id must reference
--    teams. This circular dependency is solved the standard way:
--    create the column now, add its FK constraint later with
--    ALTER TABLE once the teams table exists (see Section 6b).
-- ================================================================
CREATE TABLE players (
    player_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    coach_id       INT UNSIGNED DEFAULT NULL,
    sport_id       INT UNSIGNED NOT NULL,
    team_id        INT UNSIGNED DEFAULT NULL COMMENT 'FK added later via ALTER TABLE, see Section 6b',
    full_name      VARCHAR(100) NOT NULL,
    roll_no        VARCHAR(30)  NOT NULL,
    email          VARCHAR(120) NOT NULL,
    password       VARCHAR(255) NOT NULL,
    phone          VARCHAR(20)  DEFAULT NULL,
    age            TINYINT UNSIGNED DEFAULT NULL,
    gender         ENUM('male','female','other') DEFAULT NULL,
    address        TEXT DEFAULT NULL,
    profile_image  VARCHAR(255) DEFAULT NULL,
    blood_group    VARCHAR(5)   DEFAULT NULL,
    height         DECIMAL(5,2) DEFAULT NULL COMMENT 'centimeters',
    weight         DECIMAL(5,2) DEFAULT NULL COMMENT 'kilograms',
    status         ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_player_email (email),
    UNIQUE KEY uq_player_roll_no (roll_no),
    KEY idx_player_coach (coach_id),
    KEY idx_player_sport (sport_id),
    KEY idx_player_team (team_id),
    CONSTRAINT fk_player_coach FOREIGN KEY (coach_id)
        REFERENCES coaches(coach_id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_player_sport FOREIGN KEY (sport_id)
        REFERENCES sports(sport_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ================================================================
-- 6a. TEAMS
-- ================================================================
CREATE TABLE teams (
    team_id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sport_id         INT UNSIGNED NOT NULL,
    coach_id         INT UNSIGNED DEFAULT NULL,
    team_name        VARCHAR(100) NOT NULL,
    captain_id       INT UNSIGNED DEFAULT NULL,
    vice_captain_id  INT UNSIGNED DEFAULT NULL,
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_team_sport (sport_id),
    KEY idx_team_coach (coach_id),
    KEY idx_team_captain (captain_id),
    KEY idx_team_vice_captain (vice_captain_id),
    CONSTRAINT fk_team_sport FOREIGN KEY (sport_id)
        REFERENCES sports(sport_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_team_coach FOREIGN KEY (coach_id)
        REFERENCES coaches(coach_id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_team_captain FOREIGN KEY (captain_id)
        REFERENCES players(player_id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_team_vice_captain FOREIGN KEY (vice_captain_id)
        REFERENCES players(player_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ================================================================
-- 6b. Close the loop: players.team_id -> teams.team_id
--     (This is the "ALTER TABLE" step required by the circular
--     players <-> teams relationship above.)
-- ================================================================
ALTER TABLE players
  ADD CONSTRAINT fk_player_team FOREIGN KEY (team_id)
      REFERENCES teams(team_id) ON DELETE SET NULL ON UPDATE CASCADE;


-- ================================================================
-- 7. TEAM_PLAYERS  — roster junction table (many-to-many)
-- ================================================================
CREATE TABLE team_players (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    team_id      INT UNSIGNED NOT NULL,
    player_id    INT UNSIGNED NOT NULL,
    joined_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_team_player (team_id, player_id),
    KEY idx_tp_team (team_id),
    KEY idx_tp_player (player_id),
    CONSTRAINT fk_tp_team FOREIGN KEY (team_id)
        REFERENCES teams(team_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_tp_player FOREIGN KEY (player_id)
        REFERENCES players(player_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ================================================================
-- 8. EVENTS
-- ================================================================
CREATE TABLE events (
    event_id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sport_id       INT UNSIGNED NOT NULL,
    coach_id       INT UNSIGNED DEFAULT NULL,
    title          VARCHAR(150) NOT NULL,
    description    TEXT DEFAULT NULL,
    venue          VARCHAR(150) DEFAULT NULL,
    event_date     DATE NOT NULL,
    start_time     TIME DEFAULT NULL,
    end_time       TIME DEFAULT NULL,
    banner_image   VARCHAR(255) DEFAULT NULL,
    status         ENUM('Upcoming','Running','Completed','Cancelled') NOT NULL DEFAULT 'Upcoming',
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_event_sport (sport_id),
    KEY idx_event_coach (coach_id),
    KEY idx_event_date (event_date),
    KEY idx_event_status (status),
    CONSTRAINT fk_event_sport FOREIGN KEY (sport_id)
        REFERENCES sports(sport_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_event_coach FOREIGN KEY (coach_id)
        REFERENCES coaches(coach_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ================================================================
-- 9. EVENT_PARTICIPANTS
-- ================================================================
CREATE TABLE event_participants (
    id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id              INT UNSIGNED NOT NULL,
    player_id             INT UNSIGNED NOT NULL,
    participation_status  ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
    registered_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_event_player (event_id, player_id),
    KEY idx_ep_event (event_id),
    KEY idx_ep_player (player_id),
    CONSTRAINT fk_ep_event FOREIGN KEY (event_id)
        REFERENCES events(event_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_ep_player FOREIGN KEY (player_id)
        REFERENCES players(player_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ================================================================
-- 10. MATCHES  — team_two is nullable for flexibility (not
--     currently used by Cricket/Football/Hockey, all of which are
--     team-vs-team)
-- ================================================================
CREATE TABLE matches (
    match_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sport_id     INT UNSIGNED NOT NULL,
    team_one     INT UNSIGNED NOT NULL,
    team_two     INT UNSIGNED DEFAULT NULL,
    venue        VARCHAR(150) DEFAULT NULL,
    match_date   DATE NOT NULL,
    match_time   TIME DEFAULT NULL,
    winner_team  INT UNSIGNED DEFAULT NULL,
    status       ENUM('Upcoming','Completed','Cancelled') NOT NULL DEFAULT 'Upcoming',
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_match_sport (sport_id),
    KEY idx_match_team_one (team_one),
    KEY idx_match_team_two (team_two),
    KEY idx_match_winner (winner_team),
    CONSTRAINT fk_match_sport FOREIGN KEY (sport_id)
        REFERENCES sports(sport_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_match_team_one FOREIGN KEY (team_one)
        REFERENCES teams(team_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_match_team_two FOREIGN KEY (team_two)
        REFERENCES teams(team_id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_match_winner FOREIGN KEY (winner_team)
        REFERENCES teams(team_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ================================================================
-- 11. PLAYER_SCORES  — per-match stat line, one row per player/match
-- ================================================================
CREATE TABLE player_scores (
    score_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    player_id     INT UNSIGNED NOT NULL,
    coach_id      INT UNSIGNED DEFAULT NULL,
    match_id      INT UNSIGNED NOT NULL,
    goals         INT UNSIGNED NOT NULL DEFAULT 0,
    runs          INT UNSIGNED NOT NULL DEFAULT 0,
    wickets       INT UNSIGNED NOT NULL DEFAULT 0,
    catches       INT UNSIGNED NOT NULL DEFAULT 0,
    assists       INT UNSIGNED NOT NULL DEFAULT 0,
    race_time     DECIMAL(8,2) DEFAULT NULL COMMENT 'seconds — not used by Cricket/Football/Hockey, kept for schema flexibility',
    points        INT UNSIGNED NOT NULL DEFAULT 0,
    custom_score  DECIMAL(8,2) DEFAULT NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_score_player_match (player_id, match_id),
    KEY idx_score_player (player_id),
    KEY idx_score_coach (coach_id),
    KEY idx_score_match (match_id),
    CONSTRAINT fk_score_player FOREIGN KEY (player_id)
        REFERENCES players(player_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_score_coach FOREIGN KEY (coach_id)
        REFERENCES coaches(coach_id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_score_match FOREIGN KEY (match_id)
        REFERENCES matches(match_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ================================================================
-- 12. PLAYER_RATINGS  — coach star ratings (1-5) per player
-- ================================================================
CREATE TABLE player_ratings (
    rating_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    player_id    INT UNSIGNED NOT NULL,
    coach_id     INT UNSIGNED NOT NULL,
    rating       TINYINT UNSIGNED NOT NULL,
    review       TEXT DEFAULT NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_rating_player (player_id),
    KEY idx_rating_coach (coach_id),
    CONSTRAINT fk_rating_player FOREIGN KEY (player_id)
        REFERENCES players(player_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_rating_coach FOREIGN KEY (coach_id)
        REFERENCES coaches(coach_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT chk_rating_range CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ================================================================
-- 13. PERFORMANCE_ANALYSIS  — one cached summary row per player,
--     recomputed whenever new scores/ratings come in (AI module).
--     NOTE: `rank` is a reserved word since MySQL 8.0 (window
--     functions) — it is always backtick-quoted below.
-- ================================================================
CREATE TABLE performance_analysis (
    analysis_id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    player_id          INT UNSIGNED NOT NULL,
    average_rating     DECIMAL(3,2) NOT NULL DEFAULT 0.00,
    total_score         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    matches_played      INT UNSIGNED NOT NULL DEFAULT 0,
    `rank`              INT UNSIGNED DEFAULT NULL,
    performance_level   ENUM('Excellent','Good','Average','Below Average') DEFAULT NULL,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_analysis_player (player_id),
    CONSTRAINT fk_analysis_player FOREIGN KEY (player_id)
        REFERENCES players(player_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ================================================================
-- 14. MESSAGES  — polymorphic chat between any two role accounts.
--     No FK on sender/receiver: they can point at admins, coaches,
--     players, and a single column can't hold a foreign key to
--     three different tables. Referential integrity
--     across roles is enforced in the PHP application layer.
-- ================================================================
CREATE TABLE messages (
    message_id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sender_role      ENUM('admin','coach','player') NOT NULL,
    sender_id        INT UNSIGNED NOT NULL,
    receiver_role    ENUM('admin','coach','player') NOT NULL,
    receiver_id      INT UNSIGNED NOT NULL,
    message          TEXT DEFAULT NULL,
    attachment       VARCHAR(255) DEFAULT NULL,
    attachment_type  ENUM('image','video','pdf','document','none') NOT NULL DEFAULT 'none',
    sent_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    seen_status      ENUM('sent','seen') NOT NULL DEFAULT 'sent',
    KEY idx_msg_sender (sender_role, sender_id),
    KEY idx_msg_receiver (receiver_role, receiver_id),
    KEY idx_msg_sent_at (sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ================================================================
-- 15. NOTIFICATIONS  — polymorphic, same reasoning as messages.
--     receiver_id is nullable to support a broadcast to an entire role.
-- ================================================================
CREATE TABLE notifications (
    notification_id  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title            VARCHAR(150) NOT NULL,
    message          TEXT DEFAULT NULL,
    receiver_role    ENUM('admin','coach','player','all') NOT NULL,
    receiver_id      INT UNSIGNED DEFAULT NULL COMMENT 'NULL = broadcast to entire receiver_role',
    status           ENUM('unread','read') NOT NULL DEFAULT 'unread',
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_notif_receiver (receiver_role, receiver_id),
    KEY idx_notif_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ================================================================
-- 16. ACTIVITY_LOGS  — polymorphic audit trail, same reasoning.
-- ================================================================
CREATE TABLE activity_logs (
    log_id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_role    ENUM('admin','coach','player') NOT NULL,
    user_id      INT UNSIGNED NOT NULL,
    activity     VARCHAR(255) NOT NULL,
    ip_address   VARCHAR(45) DEFAULT NULL COMMENT 'VARCHAR(45) supports IPv6',
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_log_user (user_role, user_id),
    KEY idx_log_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ================================================================
-- 17. GALLERY  — managed by admins, so uploaded_by has a real FK.
-- ================================================================
CREATE TABLE gallery (
    image_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title        VARCHAR(150) DEFAULT NULL,
    image        VARCHAR(255) NOT NULL,
    uploaded_by  INT UNSIGNED DEFAULT NULL COMMENT 'admins.admin_id',
    uploaded_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_gallery_uploader (uploaded_by),
    CONSTRAINT fk_gallery_admin FOREIGN KEY (uploaded_by)
        REFERENCES admins(admin_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ================================================================
-- 18. DOCUMENTS  — uploaded by coach OR player, so (like messages)
--     uploaded_by is polymorphic and left without a hard FK.
-- ================================================================
CREATE TABLE documents (
    document_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title         VARCHAR(150) DEFAULT NULL,
    file_path     VARCHAR(255) NOT NULL,
    uploaded_by   INT UNSIGNED DEFAULT NULL COMMENT 'coaches.coach_id or players.player_id (see uploader_role)',
    uploader_role ENUM('admin','coach','player') DEFAULT NULL,
    uploaded_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_doc_uploader (uploader_role, uploaded_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


SET FOREIGN_KEY_CHECKS = 1;


-- ================================================================
-- SAMPLE DATA
-- ================================================================

-- Sports (fixed 4-sport catalogue)
INSERT INTO sports (sport_name) VALUES
('Cricket'), ('Football'), ('Hockey');

-- Admin account
-- Password placeholder below is a bcrypt hash of "Admin@123".
-- Always regenerate real hashes with PHP's password_hash() — never
-- hardcode production passwords in a seed file.
INSERT INTO admins (full_name, email, password, phone, role, status) VALUES
('System Administrator', 'admin@maograduatecollege.edu.pk',
 '$2y$10$8KzP1Q0m1yN3f7q1p8b1EOe1k6h2s5w9d0f4g7j2l6m8n1o3p5q7r', '03000000000', 'super_admin', 'active');


-- One coach per sport
INSERT INTO coaches (sport_id, full_name, email, password, phone, experience, qualification, status) VALUES
(1, 'Coach M. Hassan',    'coach.cricket@maograduatecollege.edu.pk',  '$2y$10$8KzP1Q0m1yN3f7q1p8b1EOe1k6h2s5w9d0f4g7j2l6m8n1o3p5q7r', '03033333333', '8 years', 'PCB Level 2 Certified', 'active'),
(2, 'Coach Imran Butt',   'coach.football@maograduatecollege.edu.pk', '$2y$10$8KzP1Q0m1yN3f7q1p8b1EOe1k6h2s5w9d0f4g7j2l6m8n1o3p5q7r', '03022222222', '6 years', 'BS Sports Science', 'active'),
(3, 'Coach Bilal Sarwar', 'coach.hockey@maograduatecollege.edu.pk',   '$2y$10$8KzP1Q0m1yN3f7q1p8b1EOe1k6h2s5w9d0f4g7j2l6m8n1o3p5q7r', '03044444444', '5 years', 'PHF Level 2 Coaching Certificate', 'active');

-- A few players
INSERT INTO players (coach_id, sport_id, full_name, roll_no, email, password, phone, age, gender, blood_group, height, weight, status) VALUES
(1, 1, 'Hamza Sheikh', 'BSCS-22-033', 'hamza.sheikh@student.edu.pk', '$2y$10$8KzP1Q0m1yN3f7q1p8b1EOe1k6h2s5w9d0f4g7j2l6m8n1o3p5q7r', '03088888888', 20, 'male', 'A+',  172.00, 65.50, 'active'),
(1, 1, 'Ahmad Raza',   'BSCS-21-045', 'ahmad.raza@student.edu.pk',   '$2y$10$8KzP1Q0m1yN3f7q1p8b1EOe1k6h2s5w9d0f4g7j2l6m8n1o3p5q7r', '03066666666', 21, 'male', 'O+',  176.50, 68.20, 'active'),
(2, 2, 'Usman Ali',    'BSIT-21-012', 'usman.ali@student.edu.pk',    '$2y$10$8KzP1Q0m1yN3f7q1p8b1EOe1k6h2s5w9d0f4g7j2l6m8n1o3p5q7r', '03077777777', 22, 'male', 'B+',  179.00, 71.00, 'active'),
(3, 3, 'Bilal Ahmed',  'BSCS-20-007', 'bilal.ahmed@student.edu.pk',  '$2y$10$8KzP1Q0m1yN3f7q1p8b1EOe1k6h2s5w9d0f4g7j2l6m8n1o3p5q7r', '03011122233', 23, 'male', 'O-',  174.00, 66.00, 'active');

-- One team per sport, with captain/vice-captain
INSERT INTO teams (sport_id, coach_id, team_name, captain_id, vice_captain_id) VALUES
(1, 1, 'Blue XI Cricket Club', 1, 2),
(2, 2, 'College Titans FC',    3, NULL),
(3, 3, 'Hockey Warriors',      4, NULL);

-- Roster (team_players)
INSERT INTO team_players (team_id, player_id) VALUES
(1, 1), (1, 2), (2, 3), (3, 4);

-- Sample event
INSERT INTO events (sport_id, coach_id, title, description, venue, event_date, start_time, end_time, status) VALUES
(2, 2, 'Inter-Dept Football Opener', 'Season-opening league fixture between department teams.',
 'Main Ground', '2026-08-14', '16:00:00', '18:00:00', 'Upcoming');

-- Sample event registration
INSERT INTO event_participants (event_id, player_id, participation_status) VALUES
(1, 3, 'Approved');

-- Sample match + scores + rating
INSERT INTO matches (sport_id, team_one, team_two, venue, match_date, match_time, status) VALUES
(1, 1, NULL, 'Main Ground', '2026-07-19', '16:00:00', 'Completed');

INSERT INTO player_scores (player_id, coach_id, match_id, goals, assists, points) VALUES
(1, 1, 1, 2, 1, 9),
(2, 1, 1, 1, 0, 6);

INSERT INTO player_ratings (player_id, coach_id, rating, review) VALUES
(1, 1, 5, 'Outstanding performance, led the attack all match.'),
(2, 1, 4, 'Solid defensive work, good positioning.');

INSERT INTO performance_analysis (player_id, average_rating, total_score, matches_played, `rank`, performance_level) VALUES
(1, 5.00, 9.00, 1, 1, 'Excellent'),
(2, 4.00, 6.00, 1, 2, 'Good');

-- Sample notification + activity log + gallery item
INSERT INTO notifications (title, message, receiver_role, receiver_id, status) VALUES
('Match Result Posted', 'Your score for the Aug 14 fixture has been recorded.', 'player', 1, 'unread');

INSERT INTO activity_logs (user_role, user_id, activity, ip_address) VALUES
('admin', 1, 'Created event: Inter-Dept Football Opener', '127.0.0.1');

INSERT INTO gallery (title, image, uploaded_by) VALUES
('Titans vs Falcons — Matchday 3', 'gallery-1.jpg', 1);
