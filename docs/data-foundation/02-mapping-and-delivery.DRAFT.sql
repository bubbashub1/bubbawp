-- DESIGN DRAFT ONLY. DO NOT RUN WITHOUT STAGING VERIFICATION.
-- Mapping table is optional: use verified existing WP user meta / import map if sufficient.
CREATE TABLE IF NOT EXISTS {{WP_PREFIX}}bhs_legacy_id_map (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  entity_type VARCHAR(40) NOT NULL,
  legacy_id BIGINT UNSIGNED NOT NULL,
  wp_id BIGINT UNSIGNED NOT NULL,
  verified_at DATETIME NOT NULL,
  verification_method VARCHAR(60) NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY source_identity (entity_type, legacy_id),
  KEY destination (entity_type, wp_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Delivery ledger is optional; reuse existing mail/push logs if they provide
-- durable deduplication and auditability. No private message bodies stored here.
CREATE TABLE IF NOT EXISTS {{WP_PREFIX}}bhs_message_delivery_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  channel VARCHAR(20) NOT NULL,
  template_key VARCHAR(100) NOT NULL,
  subject_type VARCHAR(40) NOT NULL,
  subject_id BIGINT UNSIGNED NOT NULL,
  recipient_hash CHAR(64) NOT NULL,
  idempotency_key VARCHAR(190) NOT NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'queued',
  attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  last_error_code VARCHAR(80) NULL,
  scheduled_at DATETIME NULL,
  sent_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY dedupe (idempotency_key),
  KEY pending (status, scheduled_at),
  KEY subject (subject_type, subject_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- Hash recipients with an application secret; do not use plain unsalted SHA-256.
-- Retention, opt-out and retry policy must be defined before implementation.
