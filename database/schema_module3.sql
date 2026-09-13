-- ================================================================
--  SPORTS MANAGEMENT SYSTEM — MODULE 3 ADDITIONAL TABLES
--  Authentication System
--  ----------------------------------------------------------------
--  IMPORTANT: this file does NOT touch any table from
--  database/schema.sql (Module 2). It only ADDS three new,
--  polymorphic support tables — the same role+id pattern already
--  used by `messages`, `notifications`, and `activity_logs` — so
--  account security state doesn't require altering admins/coaches/
--  players at all.
--
--  Import AFTER schema.sql:
--    mysql -u root -p sports_management_system < schema_module3.sql
-- ================================================================

USE sports_management_system;

-- ================================================================
-- login_security
-- ------------------------------------------------------------
-- Tracks failed login attempts + temporary lockouts per account.
-- One row per (user_role, user_id), created on first failed
-- attempt and reset to 0 on a successful login.
-- ================================================================
CREATE TABLE IF NOT EXISTS login_security (
    security_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_role        ENUM('admin','coach','player') NOT NULL,
    user_id          INT UNSIGNED NOT NULL,
    failed_attempts  TINYINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until     DATETIME DEFAULT NULL COMMENT 'NULL = not locked',
    last_attempt_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_security_user (user_role, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ================================================================
-- password_resets
-- ------------------------------------------------------------
-- One row per "Forgot Password" request. The OTP itself is never
-- stored in plain text — only its hash — same principle as user
-- passwords.
-- ================================================================
CREATE TABLE IF NOT EXISTS password_resets (
    reset_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_role    ENUM('admin','coach','player') NOT NULL,
    user_id      INT UNSIGNED NOT NULL,
    email        VARCHAR(120) NOT NULL,
    otp_hash     VARCHAR(255) NOT NULL,
    expires_at   DATETIME NOT NULL,
    is_used      TINYINT(1) NOT NULL DEFAULT 0,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_reset_user (user_role, user_id),
    KEY idx_reset_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ================================================================
-- remember_tokens
-- ------------------------------------------------------------
-- "Remember Me" persistent login, using the selector/validator
-- pattern (NOT a single raw token in a cookie):
--   - `selector`  is looked up directly (safe — it's not secret)
--   - `validator` is a random secret, only its HASH is stored, and
--     it's compared with hash_equals() to stay constant-time-safe
-- This means a stolen database dump alone can't be replayed as a
-- valid login cookie.
-- ================================================================
CREATE TABLE IF NOT EXISTS remember_tokens (
    token_id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_role       ENUM('admin','coach','player') NOT NULL,
    user_id         INT UNSIGNED NOT NULL,
    selector        VARCHAR(24) NOT NULL,
    validator_hash  VARCHAR(255) NOT NULL,
    expires_at      DATETIME NOT NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_selector (selector),
    KEY idx_token_user (user_role, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
