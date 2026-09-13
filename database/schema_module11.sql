-- ================================================================
--  SPORTS MANAGEMENT SYSTEM — MODULE 11 ADDITIVE MIGRATION
--  Player Performance Analysis & Smart Ranking System
--  ----------------------------------------------------------------
--  `performance_analysis` already exists (Module 2) as a one-row-
--  per-player CURRENT SNAPSHOT cache — reused and extended here,
--  never replaced. Its 4-value performance_level ENUM is widened
--  to the 6 values this module's brief requires (existing values
--  kept, so any old row stays valid).
--
--  `player_performance_history` is NEW — the brief explicitly
--  distinguishes "never overwrite old match performance" from the
--  current snapshot, so this is a genuinely separate concept, not
--  a duplicate of performance_analysis or player_scores.
--
--  `team_performance` / `coach_performance` are NEW cache tables,
--  mirroring performance_analysis's own "one row per subject,
--  recomputed on demand" pattern — nothing existing already held
--  this aggregate data.
--
--  Import AFTER schema.sql through schema_module10.sql:
--    mysql -u root -p sports_management_system < schema_module11.sql
-- ================================================================

USE sports_management_system;

ALTER TABLE performance_analysis
    MODIFY COLUMN performance_level ENUM('Excellent','Very Good','Good','Average','Needs Improvement','Insufficient Data') DEFAULT NULL,
    ADD COLUMN sport_id                  INT UNSIGNED DEFAULT NULL AFTER player_id,
    ADD COLUMN team_id                   INT UNSIGNED DEFAULT NULL AFTER sport_id,
    ADD COLUMN performance_score         DECIMAL(5,2) DEFAULT NULL COMMENT '0-100 normalized',
    ADD COLUMN star_rating               DECIMAL(2,1) DEFAULT NULL COMMENT '0-5, derived from performance_score',
    ADD COLUMN coach_rating_component    DECIMAL(5,2) DEFAULT NULL,
    ADD COLUMN match_component           DECIMAL(5,2) DEFAULT NULL,
    ADD COLUMN sport_stats_component     DECIMAL(5,2) DEFAULT NULL,
    ADD COLUMN consistency_component     DECIMAL(5,2) DEFAULT NULL,
    ADD COLUMN improvement_component     DECIMAL(5,2) DEFAULT NULL,
    ADD COLUMN matches_won               INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN matches_lost              INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN matches_drawn             INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN previous_rank             INT UNSIGNED DEFAULT NULL,
    ADD COLUMN rank_change               INT DEFAULT NULL COMMENT 'positive = moved up, negative = moved down, NULL = new',
    ADD COLUMN trend                     ENUM('Improving','Stable','Declining','New','Insufficient Data') DEFAULT NULL,
    ADD KEY idx_perf_sport (sport_id),
    ADD KEY idx_perf_team (team_id),
    ADD CONSTRAINT fk_perf_sport FOREIGN KEY (sport_id) REFERENCES sports(sport_id) ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT fk_perf_team FOREIGN KEY (team_id) REFERENCES teams(team_id) ON DELETE SET NULL ON UPDATE CASCADE;

-- ================================================================
-- player_performance_history — one immutable row per player per
-- completed match; never updated after insert, never deleted by
-- recalculation (recalculation only ever adds rows for newly-
-- completed matches that don't already have one).
-- ================================================================
CREATE TABLE IF NOT EXISTS player_performance_history (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    player_id           INT UNSIGNED NOT NULL,
    match_id            INT UNSIGNED NOT NULL,
    sport_id            INT UNSIGNED NOT NULL,
    performance_score   DECIMAL(5,2) DEFAULT NULL,
    coach_rating        DECIMAL(3,2) DEFAULT NULL,
    sport_score         DECIMAL(5,2) DEFAULT NULL,
    consistency_score   DECIMAL(5,2) DEFAULT NULL,
    improvement_score   DECIMAL(5,2) DEFAULT NULL,
    performance_level   ENUM('Excellent','Very Good','Good','Average','Needs Improvement','Insufficient Data') DEFAULT NULL,
    `rank`              INT UNSIGNED DEFAULT NULL COMMENT 'overall rank at the time this record was calculated',
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_history_player_match (player_id, match_id),
    KEY idx_history_player (player_id),
    KEY idx_history_sport (sport_id),
    CONSTRAINT fk_history_player FOREIGN KEY (player_id) REFERENCES players(player_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_history_match FOREIGN KEY (match_id) REFERENCES matches(match_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_history_sport FOREIGN KEY (sport_id) REFERENCES sports(sport_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- team_performance — recomputed snapshot, one row per team
-- ================================================================
CREATE TABLE IF NOT EXISTS team_performance (
    team_id                 INT UNSIGNED PRIMARY KEY,
    matches_played          INT UNSIGNED NOT NULL DEFAULT 0,
    wins                    INT UNSIGNED NOT NULL DEFAULT 0,
    losses                  INT UNSIGNED NOT NULL DEFAULT 0,
    draws                   INT UNSIGNED NOT NULL DEFAULT 0,
    win_percentage          DECIMAL(5,2) DEFAULT NULL,
    avg_player_rating       DECIMAL(3,2) DEFAULT NULL,
    avg_team_performance    DECIMAL(5,2) DEFAULT NULL,
    team_rank               INT UNSIGNED DEFAULT NULL,
    top_player_id           INT UNSIGNED DEFAULT NULL,
    updated_at              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_teamperf_team FOREIGN KEY (team_id) REFERENCES teams(team_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_teamperf_topplayer FOREIGN KEY (top_player_id) REFERENCES players(player_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- coach_performance — recomputed snapshot, one row per coach
-- ================================================================
CREATE TABLE IF NOT EXISTS coach_performance (
    coach_id                   INT UNSIGNED PRIMARY KEY,
    players_managed            INT UNSIGNED NOT NULL DEFAULT 0,
    avg_player_performance     DECIMAL(5,2) DEFAULT NULL,
    player_improvement         DECIMAL(5,2) DEFAULT NULL COMMENT 'avg improvement % across managed players',
    team_win_percentage        DECIMAL(5,2) DEFAULT NULL,
    avg_coach_rating           DECIMAL(3,2) DEFAULT NULL COMMENT 'avg of ratings this coach gave, informational',
    completed_matches          INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at                 TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_coachperf_coach FOREIGN KEY (coach_id) REFERENCES coaches(coach_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
