# Staging schema and form review — safe execution checklist
Status: preparation only. No live database access or staging schema output has been obtained.

## 1. Establish correct environment
1. Open staging phpMyAdmin, confirm selected database is **SCSTAGING-353034334ce5** (verify current database name; do not assume unchanged).
2. Ensure a current backup exists before any later migration approval.
3. Run `STAGING_READ_ONLY_AUDIT.sql` from this folder. The script only queries metadata, not family records.
4. Compare table names with the actual `$wpdb->prefix`. Never assume `wp_`, `bh_`, or `bhs_` without checking.

## 2. Compare repository DDL to staging
Repository reference:
- `plugin/bubba-hub-manager/includes/class-bh-schema.php` — organisers, venues, activities, sessions, booking and consent schema.
- `plugin/bubba-hub-manager/includes/class-bh-family-db-setup.php` — family tables.
- `plugin/bubba-hub-manager/includes/class-bh-ask-support-storage.php` — support tables.
- `docs/data-foundation/*_FIELD_MAP.md` — sector-by-sector contracts.

For each table record: present/missing; existing columns; missing columns; mismatched types/defaults; missing/extra indexes; expected primary and unique keys; data count only if needed and privacy-safe. **Do not automatically modify an existing table.**

## 3. Form exports (do not export submissions)
- Fluent Forms #4: Ask a Question.
- Fluent Forms #5: Family Support.
- Fluent Forms #6: Interested in Joining / enquiry, not confirmed booking.
- Fluent Forms #7: Booking Consent.
For each export record: form ID, form title, field `name` keys, required flags, options, conditional logic, consent copy, notifications, recipients, confirmation behavior, privacy settings. Redact secrets and email addresses not needed for mapping.

## 4. Review matrix
| Sector | Repository contract | Staging DDL | Form export | WP page/shortcode | Owner/access test |
|---|---|---|---|---|---|
| Directory | Drafted | Pending | Native wizard pending | Pending | Pending |
| Family | Drafted | Pending | Native editor pending | Pending | Pending |
| Leader | Drafted | Pending | #6 pending | Pending | Pending |
| Booking | Drafted | Pending | #6/#7 pending | Pending | Pending |
| Support | Drafted | Pending | #4/#5 pending | Pending | Pending |
| Membership | Drafted | Pending | Plugin settings pending | Pending | Pending |
| Communications | Drafted | Pending | Opt-in pending | Pending | Pending |
| Events | Drafted | Pending | Planned forms | Pending | Pending |
| Claims | Drafted | Pending | Planned forms | Pending | Pending |
| Platform | Drafted | Pending | N/A | Pending | Pending |

## 5. Decision rules
- Missing table: propose separate **staging-only** creation migration after approval.
- Missing field: propose explicit additive ALTER script with type, nullable/default, index, rollback and data compatibility notes.
- Existing equivalent field: map it; do not duplicate.
- Conflicting writers: choose canonical writer before syncing or backfilling.
- Personal data: minimise exports, require ownership checks, do not include sensitive child information in ordinary notifications.
- Never treat passing GitHub CI as evidence of live staging schema compatibility.

## 6. Deliverables once real evidence is available
- Verified table/column diff with source evidence.
- Verified Form 4–7 field keys and notifications mapping.
- Minimal proposed SQL scripts per sector, each marked DRAFT until explicit staging approval.
- Test cases and rollback notes.
