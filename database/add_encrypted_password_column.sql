-- ================================================================
--  MIGRATION — add admin-recoverable encrypted credential column
--  ------------------------------------------------------------
--  Adds ONE new column each to `coaches` and `players`:
--    encrypted_password  TEXT  (AES-256-GCM ciphertext, base64)
--
--  This does NOT touch the existing `password` column (bcrypt
--  hashes via password_hash/password_verify) — that stays exactly
--  as-is and keeps authenticating logins the same way it always
--  has. `encrypted_password` is a SEPARATE, additional field used
--  only by Admin's "Show Password" action (see
--  admin/coach/reveal_password.php / admin/player/reveal_password.php).
--
--  Existing accounts: their `encrypted_password` will be NULL
--  after this migration, since their original plaintext was never
--  stored anywhere (correctly — bcrypt is one-way). Admin must use
--  "Reset Password" once per existing account to populate a
--  recoverable credential; until then, "Show Password" will report
--  "No recoverable password on file" for that account, which is
--  expected and NOT a bug — see the app's password_hash()/bcrypt
--  design, which never permits reversing an existing hash.
--
--  Run with:
--    mysql -u root -p sports_management_system < database/add_encrypted_password_column.sql
--
--  SAFE TO RE-RUN — checks information_schema before altering, so
--  running this twice is a no-op the second time.
-- ================================================================

USE sports_management_system;

SET @col_exists_coaches := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'coaches' AND COLUMN_NAME = 'encrypted_password'
);
SET @sql_coaches := IF(@col_exists_coaches = 0,
    'ALTER TABLE coaches ADD COLUMN encrypted_password TEXT DEFAULT NULL AFTER password',
    'SELECT ''coaches.encrypted_password already exists, skipping'' AS notice'
);
PREPARE stmt_coaches FROM @sql_coaches;
EXECUTE stmt_coaches;
DEALLOCATE PREPARE stmt_coaches;

SET @col_exists_players := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'players' AND COLUMN_NAME = 'encrypted_password'
);
SET @sql_players := IF(@col_exists_players = 0,
    'ALTER TABLE players ADD COLUMN encrypted_password TEXT DEFAULT NULL AFTER password',
    'SELECT ''players.encrypted_password already exists, skipping'' AS notice'
);
PREPARE stmt_players FROM @sql_players;
EXECUTE stmt_players;
DEALLOCATE PREPARE stmt_players;

-- Verify
DESCRIBE coaches;
DESCRIBE players;
