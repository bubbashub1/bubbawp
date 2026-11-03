-- Bubba Hub 3.0 activity schema parity: corrected from actual 44-column
-- legacy bh_activities phpMyAdmin column listing supplied 2026-11-03.
-- REVIEW ONLY. Do not run blindly: existing columns differ in type/length.
-- Run SHOW CREATE TABLE on staging and take a backup before migrations.
-- WordPress uses configured prefix, not necessarily wp_.
--
-- Existing BH3: id organiser_id title slug description status booking_url
-- price_from age_min_months age_max_months category is_free image_id
-- created_at updated_at.
-- Existing BH3 differences to review separately:
-- slug varchar(200) vs legacy varchar(255)
-- description longtext vs legacy text (keep longtext)
-- category varchar(150) vs legacy varchar(255)
-- booking_url varchar(2048) vs legacy varchar(1000) (keep 2048)
-- status varchar(30) vs legacy enum (keep varchar, validate in app)
-- created_at/updated_at without legacy automatic timestamp defaults
-- image_id is a BH3 WP-media field; retain.
--
-- Additive migration proposal for missing legacy fields:
ALTER TABLE wp_activities
 ADD COLUMN leader_id BIGINT(20) UNSIGNED NULL,
 ADD COLUMN booking_required TINYINT(1) NOT NULL DEFAULT 0,
 ADD COLUMN price DECIMAL(10,2) NULL,
 ADD COLUMN image_path VARCHAR(1000) NULL,
 ADD COLUMN price_type VARCHAR(40) NOT NULL DEFAULT 'per_session',
 ADD COLUMN currency CHAR(3) NOT NULL DEFAULT 'GBP',
 ADD COLUMN session_length_minutes SMALLINT(5) UNSIGNED NULL,
 ADD COLUMN term_time_only TINYINT(1) NOT NULL DEFAULT 0,
 ADD COLUMN contact_email VARCHAR(255) NULL,
 ADD COLUMN contact_phone VARCHAR(100) NULL,
 ADD COLUMN website VARCHAR(1000) NULL,
 ADD COLUMN facebook_url VARCHAR(500) NULL,
 ADD COLUMN instagram_url VARCHAR(500) NULL,
 ADD COLUMN featured TINYINT(1) NOT NULL DEFAULT 0,
 ADD COLUMN created_by BIGINT(20) UNSIGNED NULL,
 ADD COLUMN published_at DATETIME NULL,
 ADD COLUMN age_range VARCHAR(255) NULL,
 ADD COLUMN accessibility TEXT NULL,
 ADD COLUMN tags TEXT NULL,
 ADD COLUMN weekly_schedule TEXT NULL,
 ADD COLUMN price_per_family TINYINT(1) NOT NULL DEFAULT 0,
 ADD COLUMN price_per_session TINYINT(1) NOT NULL DEFAULT 0,
 ADD COLUMN drop_in_welcome TINYINT(1) NOT NULL DEFAULT 0,
 ADD COLUMN trial_available TINYINT(1) NOT NULL DEFAULT 0,
 ADD COLUMN holiday_sessions TINYINT(1) NOT NULL DEFAULT 0,
 ADD COLUMN siblings_welcome TINYINT(1) NOT NULL DEFAULT 0,
 ADD COLUMN what_to_bring VARCHAR(250) NULL,
 ADD COLUMN good_to_know VARCHAR(250) NULL,
 ADD COLUMN gallery_images TEXT NULL,
 ADD COLUMN block_length_weeks SMALLINT(5) UNSIGNED NULL;
--
-- Additional indexes to consider after inspecting existing indexes:
-- leader_id, created_by, featured, title, age_min_months, age_max_months.
-- Existing BH3 sessions table remains canonical for structured schedules;
-- weekly_schedule is retained as legacy/import compatibility text.
-- price_type enum full values and status enum full values were truncated
-- in supplied screenshot; confirm exact SHOW COLUMNS before mapping.
-- Run via idempotent guarded PHP migration, not raw SQL on existing DB.
