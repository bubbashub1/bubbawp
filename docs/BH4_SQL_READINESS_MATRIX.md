# Bubba Hub 4.0 — SQL readiness and canonical data audit

Status: **Source-level inventory; staging database NOT verified**. Prepared 2026-11-06. No SQL executed.

## Source-defined physical tables

All `{prefix}` references mean the *configured* WordPress table prefix, not necessarily `wp_`. Never assume legacy standalone `bh_*` tables share that prefix.

| Sector | Source-defined tables / storage | Required staging validation |
| --- | --- | --- |
| Listings | `{prefix}organisers`, `{prefix}activities`, `{prefix}venues`, `{prefix}activity_venues` | Existence, columns/types, indexes, row counts, slug uniqueness, orphaned links, legacy ID mapping |
| Sessions | `{prefix}sessions`, `{prefix}session_listing_links` | Recurrence, date exceptions, venue ownership, duplicate links, actual occurrence model |
| Bookings | `{prefix}bh3_bookings`, `{prefix}bh3_booking_attendees`, `{prefix}bh3_booking_consents`, `{prefix}bh3_stripe_events` | Keys, status/payment split, consent version, attendee ownership, idempotency, concurrency; PR #163 unmerged at initial audit |
| Family | `{prefix}bh_family_profiles`, `{prefix}bh_family_reminders`; legacy `bh_children`, `bh_bumps` | Physical presence, ownership, data migration/provenance, deletion and retention |
| Planner | Legacy `bh_planner`, `bh_saved_activities`, `bh_user_preferences`; WP native implementations to verify | Exact physical table names, feature writers, profile links and duplicate saved entries |
| Support | `{prefix}fluentform_submissions`, `{prefix}bh_support_enquiry_status`, `{prefix}bh_support_enquiry_history`; legacy ask/support tables | Form IDs 4/5/6/7, workflow permissions, private contacts, no duplicated sensitive answers |
| Identity/membership | WP `users`, `usermeta`, Simple Membership tables; legacy `bh_users` | WP-to-legacy identity bridge, tier IDs, access controls, no email-only automatic linking |
| Events | Legacy Expo ticket, check-in, exhibitor, sponsor and raffle storage (exact names to inventory) | Identify actual tables/keys, attendee and capacity ownership, QR uniqueness; do not invent a new event schema |
| Communications | FluentSMTP/Fluent Forms plus legacy newsletter/message data | Identify physical tables, consent/retention, verified leader recipients and failed delivery handling |
| Inspiration | Directory activity, venue, session, facilities and preferences data | Decide whether existing fields/meta suffice before proposing `bhs_*` tables; indoor/covered, accessibility, budget, holiday dates and verification provenance |

## Authoritative relationship contract (requires sign-off)

1. WP user → optional legacy identity bridge → owned family profiles and/or verified organiser account.
2. Organiser → activities; activity ↔ venues via join table; activity → sessions; session → dated occurrence (explicit recurrence and exceptions).
3. Occurrence → enquiry or reservation → booking attendees and versioned consents; payment status separate from booking lifecycle; booking links to a trusted organiser.
4. Family profile → favourites, visited and planner entries, each scoped to owning WP user.
5. Event occurrence → ticket/booking and QR check-in, with capacity and duplicate entry protections.
6. Inspiration uses verified activity/venue/session metadata; do not duplicate booking or personal profile records.

## Required read-only staging evidence

- Confirm actual configured `$wpdb->prefix` and inventory all application tables (table names only).
- For each table: `SHOW CREATE TABLE`, `SHOW INDEX`, field/type/null/default comparison, row count, orphan/duplicate summary **without exposing family records**.
- Compare against `includes/class-bh-schema.php`, Family DB setup declarations, support modules and legacy `bubbaplugin/database/schema.sql`.
- Check Form #4–#7 field keys and hooks; distinguish published form from tested data binding.
- Determine whether native booking, legacy reservation and Fluent Forms entries can refer to the same logical booking. Document mapping and conflict rules.
- Confirm legacy user IDs versus WP IDs, organiser ownership and membership entitlements.
- Inventory Events/Expo and newsletters explicitly before adding new tables.
- Run only administrator-scoped, read-only diagnostics on staging. Backup and approve migration separately; never auto-install or overwrite existing tables.

## Implementation gates

**Gate A — schema inventory:** table names, DDL, indexes, counts, source owner and source-of-truth status recorded for every sector.

**Gate B — reconciliation:** canonical IDs and relationship map signed off, missing/extra columns triaged; no silent `bh_*` → `bh3_*` migration.

**Gate C — additive migration review:** only if required; backup, data-safe SQL, rollback, staging test and explicit approval.

**Gate D — functional QA:** directory, planner, leader bookings, consent, Stripe, memberships, support, events and inspiration end-to-end.

**Current result:** Gate A remains incomplete until a real staging database audit is run. Existing Developments panels offer partial schema checks, not exhaustive live evidence. Do not mark SQL ready yet.
