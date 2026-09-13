-- ================================================================
--  RESET ADMIN PASSWORD -> admin1234
--  ------------------------------------------------------------
--  Sets the password for every row in the `admins` table to the
--  bcrypt hash of "admin1234". Run this on the sports_management_system
--  database (NOT the plain sports_management one — see config.php's
--  DB_NAME to confirm which DB your app actually uses).
--
--  Run with:
--    mysql -u root -p sports_management_system < database/reset_admin_password.sql
--  or paste the UPDATE statement into phpMyAdmin's SQL tab.
--
--  New password: admin1234
--  Change it again from the admin profile page after logging in.
-- ================================================================

USE sports_management_system;

UPDATE admins
SET password = '$2b$10$KfKqmeanVAyGPb0XCWJMleuKduCQkx2Alu4N//RFJb0TivkdaJWLC';

-- Sanity check: confirm which admin account(s) now have the new password.
SELECT admin_id, full_name, email, role, status FROM admins;
