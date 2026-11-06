-- DESIGN DRAFT ONLY. NOT APPROVED OR EXECUTABLE AS-IS.
-- Replace {{WP_PREFIX}} after staging DDL review; never run against live.
-- Existing canonical booking candidate: {{WP_PREFIX}}bh3_bookings.
-- Booking lifecycle is separate from payment_status.
-- Before migration: check if booking_status/status_updated_at already exist and
-- whether a booking-status history or reminder ledger is already implemented.

ALTER TABLE {{WP_PREFIX}}bh3_bookings
  ADD COLUMN booking_status VARCHAR(32) NOT NULL DEFAULT 'pending',
  ADD COLUMN status_updated_at DATETIME NULL;

-- New version-neutral history table only if current logging is insufficient.
CREATE TABLE IF NOT EXISTS {{WP_PREFIX}}bhs_booking_status_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  booking_id BIGINT UNSIGNED NOT NULL,
  old_status VARCHAR(32) NULL,
  new_status VARCHAR(32) NOT NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  reason VARCHAR(255) NULL,
  source VARCHAR(40) NOT NULL DEFAULT 'admin',
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY booking_time (booking_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Proposed state names require a product decision:
-- pending, reserved, confirmed, enrolled, cancelled, expired.
-- Enquiry is NOT an automatic booking. Payment status remains separate.
-- Status transitions must be validated server-side, with idempotent writes.
