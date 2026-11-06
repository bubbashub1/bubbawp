-- DESIGN DRAFT ONLY. No new ticket, exhibitor or QR tables proposed:
-- reuse bh_expo_reservations, bh_expo_tickets, bh_expo_exhibitors,
-- bh_expo_tables, bh_expo_booking_qrs and bh_expo_family_checkins.
-- This table is OPTIONAL if existing event_key plus WP posts/settings is sufficient.
CREATE TABLE IF NOT EXISTS {{WP_PREFIX}}bhs_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_key VARCHAR(80) NOT NULL,
  title VARCHAR(255) NOT NULL,
  description LONGTEXT NULL,
  venue_id BIGINT UNSIGNED NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NOT NULL,
  timezone VARCHAR(64) NOT NULL DEFAULT 'Europe/London',
  capacity INT UNSIGNED NULL,
  registration_opens_at DATETIME NULL,
  registration_closes_at DATETIME NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'draft',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY event_key (event_key),
  KEY event_date (starts_at, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- Before approval: inspect Expo event_key assumptions, check-in capacity,
-- timed entry, event-specific consent, sponsor/raffle schemas and booking APIs.
