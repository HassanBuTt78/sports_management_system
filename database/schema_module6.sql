-- ================================================================
--  SPORTS MANAGEMENT SYSTEM — MODULE 6 ADDITIVE MIGRATION
--  Coach Management
--  ----------------------------------------------------------------
--  Module 6's brief requires Gender and Address fields for coaches,
--  but the `coaches` table (Module 2) has neither column. Rather
--  than silently dropping those fields, this migration adds them —
--  nullable, additive only. No existing column, row, or table is
--  modified; every current query and Module 3/4/5 feature keeps
--  working exactly as before.
--
--  Import AFTER schema.sql and schema_module3.sql:
--    mysql -u root -p sports_management_system < schema_module6.sql
-- ================================================================

USE sports_management_system;

ALTER TABLE coaches
    ADD COLUMN gender  ENUM('male','female','other') DEFAULT NULL AFTER experience,
    ADD COLUMN address TEXT DEFAULT NULL AFTER gender;
