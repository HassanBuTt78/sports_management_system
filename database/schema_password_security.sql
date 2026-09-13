-- ================================================================
--  SECURITY QUESTION PASSWORD RECOVERY — additive migration
--  ----------------------------------------------------------------
--  Replaces the Module 3 OTP-simulation "forgot password" flow
--  with a security-question flow, per request. Also supports the
--  registration forms now letting admin/coach set a password
--  directly (no more auto-generated password shown on screen).
--
--  security_answer_hash is hashed the same way as password
--  (password_hash()) — never stored in plain text. The answer is
--  lowercased + trimmed before hashing so "Lahore" and "lahore "
--  both verify correctly later.
--
--  Run in phpMyAdmin's SQL tab after schema.sql through
--  schema_module11.sql:
--    mysql -u root -p sports_management_system < schema_password_security.sql
-- ================================================================

USE sports_management_system;

ALTER TABLE admins
    ADD COLUMN security_question    VARCHAR(255) DEFAULT NULL,
    ADD COLUMN security_answer_hash VARCHAR(255) DEFAULT NULL;

ALTER TABLE coaches
    ADD COLUMN security_question    VARCHAR(255) DEFAULT NULL,
    ADD COLUMN security_answer_hash VARCHAR(255) DEFAULT NULL;

ALTER TABLE players
    ADD COLUMN security_question    VARCHAR(255) DEFAULT NULL,
    ADD COLUMN security_answer_hash VARCHAR(255) DEFAULT NULL;
