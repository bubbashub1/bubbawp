-- Bubba Hub staging read-only database inventory.
-- Run ONLY after confirming phpMyAdmin is connected to STAGING.
-- Does not create/alter/delete data. Prefix and table names are discovered below.
SELECT DATABASE() AS selected_database, @@version AS mysql_version;
SHOW TABLES;
-- Inspect exact columns, types, indexes, nullability and defaults for relevant tables.
-- This query uses information_schema for the current selected database.
SELECT TABLE_NAME, COLUMN_NAME, ORDINAL_POSITION, COLUMN_TYPE,
       IS_NULLABLE, COLUMN_DEFAULT, COLUMN_KEY, EXTRA
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND (
    TABLE_NAME LIKE '%bh3_booking%'
    OR TABLE_NAME LIKE '%bh_family%'
    OR TABLE_NAME LIKE '%organisers'
    OR TABLE_NAME LIKE '%activities'
    OR TABLE_NAME LIKE '%venues'
    OR TABLE_NAME LIKE '%sessions'
    OR TABLE_NAME LIKE '%fluentform%'
  )
ORDER BY TABLE_NAME, ORDINAL_POSITION;
SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND (
    TABLE_NAME LIKE '%bh3_booking%'
    OR TABLE_NAME LIKE '%bh_family%'
    OR TABLE_NAME LIKE '%organisers'
    OR TABLE_NAME LIKE '%activities'
    OR TABLE_NAME LIKE '%venues'
    OR TABLE_NAME LIKE '%sessions'
  )
ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;
-- No customer records or form submissions are queried.
-- Do NOT export sensitive values; share schema-only output for contract review.
