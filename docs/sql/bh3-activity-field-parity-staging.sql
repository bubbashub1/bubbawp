-- Bubba Hub 3.0: proposed activity-field parity upgrade for STAGING ONLY.
-- IMPORTANT: Review SHOW CREATE TABLE <wp_prefix>activities and legacy
-- SHOW COLUMNS FROM bh_activities before executing. Replace wp_ with actual
-- WordPress prefix. Do not execute on live. Back up staging first.
-- MySQL 8+ supports ADD COLUMN IF NOT EXISTS only on some variants;
-- use the companion PHP guarded upgrader for safe reruns.
-- This file is an inventory / review reference, not an automatic migration.
--
-- Fields already present in wp_activities:
-- id organiser_id title slug description status category age_min_months
-- age_max_months price_from is_free booking_url image_id created_at updated_at
--
-- Additional activity-specific fields evidenced by legacy export/API:
ALTER TABLE wp_activities
  ADD COLUMN featured TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN age_range VARCHAR(120) NULL,
  ADD COLUMN county VARCHAR(100) NULL,
  ADD COLUMN price_per_family DECIMAL(10,2) NULL,
  ADD COLUMN price_per_session DECIMAL(10,2) NULL,
  ADD COLUMN price_free TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN booking_required TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN drop_in_welcome TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN trial_available TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN term_time_only TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN holiday_sessions TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN siblings_welcome TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN what_to_bring TEXT NULL,
  ADD COLUMN good_to_know TEXT NULL,
  ADD COLUMN image_path VARCHAR(2048) NULL,
  ADD COLUMN image_1 VARCHAR(2048) NULL,
  ADD COLUMN image_2 VARCHAR(2048) NULL,
  ADD COLUMN image_3 VARCHAR(2048) NULL,
  ADD COLUMN facebook_url VARCHAR(2048) NULL,
  ADD COLUMN instagram_url VARCHAR(2048) NULL,
  ADD COLUMN accessibility TEXT NULL,
  ADD COLUMN tags TEXT NULL;
--
-- Intentionally NOT copied into activities: organisation_name, email,
-- phone, website (organiser/contact), venue_name, address, town, region,
-- postcode, latitude, longitude, venue contact fields (venues), sessions
-- (sessions table), gallery images beyond 3 (future child image table).
-- Existing is_free is canonical; legacy price_free requires mapping on import.
-- Verify any additional live-only columns with INFORMATION_SCHEMA before
-- declaring parity complete.
