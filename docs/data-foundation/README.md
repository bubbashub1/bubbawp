# Data-first build pack — DRAFT, not installed

Purpose: prepare SQL and form contracts before implementing Bubba Hub 3.0/4.0 features.

## Execution safety
- Nothing in this directory is automatically loaded by the WordPress plugin.
- SQL files are **design drafts** for manual review, not deployment scripts. Do not paste them into live phpMyAdmin.
- Replace `{{WP_PREFIX}}` only after confirming the actual WordPress prefix and schema in staging; check existing table/column definitions, indexes, data and code paths first.
- Back up staging, dry-run a migration plan, obtain approval and create a separate versioned installer with rollback and idempotency checks before execution.
- Never rename existing `bh_` or `bh3_` tables globally. `bhs_` is reserved for new version-neutral structures, not a forced rename.

## Files
- `01-booking-lifecycle.DRAFT.sql`: additive booking status and transition history proposal.
- `02-mapping-and-delivery.DRAFT.sql`: optional cross-system ID mapping and notification delivery ledger.
- `03-events-generalisation.DRAFT.sql`: optional shared event metadata; **legacy Expo tickets, QR, exhibitors and check-ins remain authoritative until assessed**.
- `FORM_CONTRACTS.md`: existing forms 4–7 and proposed events/claim forms, field ownership, privacy and notification contract.

## Reuse first
Legacy: `bh_organisers`, `bh_activities`, `bh_venues`, `bh_sessions`, `bh_children`, `bh_bumps`, `bh_booking_sources`, `bh_booking_slots`, `bh_booking_reservations`, `bh_consent_profiles`, `bh_booking_consent_snapshots`, `bh_newsletter_subscribers`, `bh_expo_reservations`, `bh_expo_tickets`, `bh_expo_exhibitors`, `bh_expo_tables`, `bh_expo_booking_qrs`, `bh_expo_family_checkins`, `bh_expo_raffle_prizes`.

Native: `{prefix}organisers`, `{prefix}activities`, `{prefix}venues`, `{prefix}activity_venues`, `{prefix}sessions`, `{prefix}bh3_bookings`, `{prefix}bh3_booking_attendees`, `{prefix}bh3_booking_consents`, `{prefix}bh_family_profiles`, `{prefix}bh_family_preferences`, `{prefix}bh_saved_activities`, `{prefix}bh_planner`, `{prefix}bh_family_reminders`.

## Decisions required before SQL approval
1. Confirm exact staging DDL and whether any proposed columns/tables already exist.
2. Select a single canonical booking write path and a separate enquiry workflow.
3. Define session occurrence/slot ownership and provider sync idempotency.
4. Validate verified WP/legacy identity mappings before importing families.
5. Confirm event scoping and existing Expo payment/sponsor/raffle tables.
6. Decide which form submissions become domain records and how consent snapshots are retained.
