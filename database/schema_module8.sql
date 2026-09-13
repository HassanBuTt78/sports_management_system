-- ================================================================
--  SPORTS MANAGEMENT SYSTEM — MODULE 8 ADDITIVE MIGRATION
--  Event Management
--  ----------------------------------------------------------------
--  Gaps between the brief and the existing schema:
--    1. `events` is missing organizer, end_date, registration
--       deadline, max participants, and "who created this / is it
--       approved" (needed for "Approve Coach Events").
--    2. The `status` ENUM only has 4 of the 6 required states —
--       widened to add 'Registration Open' / 'Registration Closed'.
--       This is a MODIFY, not a drop: all 4 existing values are
--       kept, so the one seeded event (status 'Upcoming') stays
--       valid without any data migration.
--    3. No table exists for event media (images/videos/PDF
--       schedule/rules document) tied to a specific event — the
--       generic Module 2 `gallery`/`documents` tables have no
--       event_id, so a new table is added rather than overloading
--       those.
--
--  Import AFTER schema.sql, schema_module3.sql, schema_module6.sql,
--  schema_module7.sql:
--    mysql -u root -p sports_management_system < schema_module8.sql
-- ================================================================

USE sports_management_system;

ALTER TABLE events
    ADD COLUMN organizer              VARCHAR(150) DEFAULT NULL AFTER coach_id,
    ADD COLUMN end_date               DATE DEFAULT NULL AFTER event_date,
    ADD COLUMN registration_deadline  DATETIME DEFAULT NULL AFTER end_date,
    ADD COLUMN max_participants       INT UNSIGNED DEFAULT NULL AFTER registration_deadline,
    ADD COLUMN created_by_role        ENUM('admin','coach') DEFAULT NULL AFTER max_participants,
    ADD COLUMN created_by_id          INT UNSIGNED DEFAULT NULL AFTER created_by_role,
    ADD COLUMN is_approved            TINYINT(1) NOT NULL DEFAULT 1 AFTER created_by_id
        COMMENT '0 = coach-created, pending admin approval; 1 = live/approved',
    MODIFY COLUMN status ENUM('Upcoming','Registration Open','Registration Closed','Running','Completed','Cancelled')
        NOT NULL DEFAULT 'Upcoming';

-- ================================================================
-- event_media
-- ------------------------------------------------------------
-- Images, videos, PDF schedule, and rules document per event.
-- ================================================================
CREATE TABLE IF NOT EXISTS event_media (
    media_id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id         INT UNSIGNED NOT NULL,
    media_type       ENUM('image','video','pdf_schedule','rules_document') NOT NULL,
    file_path        VARCHAR(255) NOT NULL,
    uploaded_by_role ENUM('admin','coach') DEFAULT NULL,
    uploaded_by_id   INT UNSIGNED DEFAULT NULL,
    uploaded_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_media_event (event_id),
    CONSTRAINT fk_media_event FOREIGN KEY (event_id)
        REFERENCES events(event_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
