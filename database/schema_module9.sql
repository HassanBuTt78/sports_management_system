-- ================================================================
--  SPORTS MANAGEMENT SYSTEM — MODULE 9 ADDITIVE MIGRATION
--  Match Management & Live Scoring
--  ----------------------------------------------------------------
--  `matches` already has status/match_time/winner_team from Module 2
--  — this migration only adds genuinely NEW columns (never touches
--  or duplicates those).
--
--  STATUS VOCABULARY NOTE: the brief asks for Scheduled/Live/
--  Completed/Cancelled — the existing `status` ENUM (defined back
--  in schema.sql) already covers exactly those 3 values.
--
--  `player_scores` gets new NULLABLE per-sport columns for the
--  detailed stats this module's brief asks for (cricket/football/
--  hockey) — the original 8 columns from Module 2 are untouched,
--  so any code from earlier modules that reads them keeps working
--  unchanged. A few extra columns below (blocks, service_aces,
--  digs, finish_position, lap_time) were added for sports that
--  are no longer part of this project (Volleyball, Racing) — they
--  stay as unused, harmless nullable columns rather than being
--  dropped, to avoid any risk to existing installations' data.
--
--  Import AFTER schema.sql, schema_module3.sql, schema_module6.sql,
--  schema_module7.sql, schema_module8.sql:
--    mysql -u root -p sports_management_system < schema_module9.sql
-- ================================================================

USE sports_management_system;

ALTER TABLE matches
    ADD COLUMN match_title       VARCHAR(150) DEFAULT NULL AFTER match_id,
    ADD COLUMN event_id          INT UNSIGNED DEFAULT NULL AFTER sport_id,
    ADD COLUMN coach_id          INT UNSIGNED DEFAULT NULL COMMENT 'Assigned/overseeing coach for this match' AFTER event_id,
    ADD COLUMN referee           VARCHAR(100) DEFAULT NULL AFTER venue,
    ADD COLUMN runner_up_team    INT UNSIGNED DEFAULT NULL AFTER winner_team,
    ADD COLUMN mvp_player_id     INT UNSIGNED DEFAULT NULL AFTER runner_up_team,
    ADD COLUMN cancelled_reason  VARCHAR(255) DEFAULT NULL,
    ADD COLUMN result_summary    TEXT DEFAULT NULL COMMENT 'Free-text result summary, e.g. "Titans won 3-1"',
    ADD COLUMN created_by_role   ENUM('admin','coach') DEFAULT NULL,
    ADD COLUMN created_by_id     INT UNSIGNED DEFAULT NULL,
    ADD COLUMN updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    ADD CONSTRAINT fk_match_event FOREIGN KEY (event_id)
        REFERENCES events(event_id) ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT fk_match_coach FOREIGN KEY (coach_id)
        REFERENCES coaches(coach_id) ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT fk_match_runnerup FOREIGN KEY (runner_up_team)
        REFERENCES teams(team_id) ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT fk_match_mvp FOREIGN KEY (mvp_player_id)
        REFERENCES players(player_id) ON DELETE SET NULL ON UPDATE CASCADE;

-- ================================================================
-- player_scores — new nullable per-sport columns for Live Score Entry
-- (goals/runs/wickets/catches/assists/race_time/points/custom_score
-- already existed from Module 2 and are untouched)
-- ================================================================
ALTER TABLE player_scores
    ADD COLUMN balls           INT UNSIGNED DEFAULT NULL COMMENT 'Cricket',
    ADD COLUMN overs           DECIMAL(4,1) DEFAULT NULL COMMENT 'Cricket',
    ADD COLUMN run_outs        INT UNSIGNED DEFAULT NULL COMMENT 'Cricket',
    ADD COLUMN strike_rate     DECIMAL(6,2) DEFAULT NULL COMMENT 'Cricket',
    ADD COLUMN economy         DECIMAL(5,2) DEFAULT NULL COMMENT 'Cricket',
    ADD COLUMN yellow_cards    TINYINT UNSIGNED DEFAULT NULL COMMENT 'Football',
    ADD COLUMN red_cards       TINYINT UNSIGNED DEFAULT NULL COMMENT 'Football',
    ADD COLUMN saves           INT UNSIGNED DEFAULT NULL COMMENT 'Football (goalkeeper)',
    ADD COLUMN blocks          INT UNSIGNED DEFAULT NULL COMMENT 'unused — was Volleyball, no longer a supported sport',
    ADD COLUMN service_aces    INT UNSIGNED DEFAULT NULL COMMENT 'unused — was Volleyball, no longer a supported sport',
    ADD COLUMN digs            INT UNSIGNED DEFAULT NULL COMMENT 'unused — was Volleyball, no longer a supported sport',
    ADD COLUMN finish_position INT UNSIGNED DEFAULT NULL COMMENT 'unused — was Racing, no longer a supported sport',
    ADD COLUMN lap_time        DECIMAL(8,2) DEFAULT NULL COMMENT 'unused — was Racing, no longer a supported sport';

-- ================================================================
-- player_ratings — add an optional match_id so a rating can be
-- tied to a specific match (Module 9's "Enter player ratings").
-- Nullable + ON DELETE SET NULL: general, non-match ratings from
-- before this module keep working unchanged.
-- ================================================================
ALTER TABLE player_ratings
    ADD COLUMN match_id INT UNSIGNED DEFAULT NULL AFTER coach_id,
    ADD CONSTRAINT fk_rating_match FOREIGN KEY (match_id)
        REFERENCES matches(match_id) ON DELETE SET NULL ON UPDATE CASCADE,
    ADD UNIQUE KEY uq_rating_player_match (player_id, match_id);

-- ================================================================
-- match_awards — Best Bowler / Best Batsman / Top Scorer / Fastest
-- Runner / Best Goalkeeper (sport-specific, alongside MVP which
-- lives directly on matches.mvp_player_id)
-- ================================================================
CREATE TABLE IF NOT EXISTS match_awards (
    award_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    match_id    INT UNSIGNED NOT NULL,
    award_type  VARCHAR(50) NOT NULL,
    player_id   INT UNSIGNED NOT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_award_match (match_id),
    CONSTRAINT fk_award_match FOREIGN KEY (match_id)
        REFERENCES matches(match_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_award_player FOREIGN KEY (player_id)
        REFERENCES players(player_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- match_media — photos / videos / scoresheets per match
-- ================================================================
CREATE TABLE IF NOT EXISTS match_media (
    media_id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    match_id         INT UNSIGNED NOT NULL,
    media_type       ENUM('photo','video','scoresheet') NOT NULL,
    file_path        VARCHAR(255) NOT NULL,
    caption          VARCHAR(255) DEFAULT NULL,
    uploaded_by_role ENUM('admin','coach') DEFAULT NULL,
    uploaded_by_id   INT UNSIGNED DEFAULT NULL,
    uploaded_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_media_match (match_id),
    CONSTRAINT fk_matchmedia_match FOREIGN KEY (match_id)
        REFERENCES matches(match_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- match_remarks — coach's post-match notes
-- ================================================================
CREATE TABLE IF NOT EXISTS match_remarks (
    remark_id  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    match_id   INT UNSIGNED NOT NULL,
    coach_id   INT UNSIGNED NOT NULL,
    remark     TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_remark_match (match_id),
    CONSTRAINT fk_remark_match FOREIGN KEY (match_id)
        REFERENCES matches(match_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_remark_coach FOREIGN KEY (coach_id)
        REFERENCES coaches(coach_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
