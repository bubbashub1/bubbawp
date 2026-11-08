# Events Manager Free + Ninja Forms migration plan (BubbaWP)

## Decision
BubbaWP is the sole active development target. Keep existing native child/bump records and activity/session tables. Events Manager is the proposed reservation authority; Ninja Forms collects supplementary enquiries and consent, never an independent reservation.

## Existing functionality to preserve
- Existing `bh_parent_booking` checkout and Stripe fee calculation until parity is proven.
- Booking lifecycle, leader dashboard, session editor and form 7/8 integrations.
- Existing Fluent Forms and submissions until a tested migration completes.
- All existing database tables and activity links; no automatic schema migration.

## First implementation
`BH_Events_Manager_Bridge::inspect()` is a passive admin-only compatibility inspector. It is intentionally not loaded by the plugin bootstrap, and must not be described as an active integration.

## Gates before activation
1. Verify actual Events Manager Free version and public APIs on staging.
2. Verify exact BubbaWP session/activity schema and recurrence semantics.
3. Map a test session to an event ID with idempotent mapping and no duplicates.
4. Verify booking capacity locking, guest/member bookings, child ownership checks and consent.
5. Verify Stripe one-payment checkout, single visible fee, webhook replay, refunds and payout accounting.
6. Verify Ninja Forms submission hooks and permissions; never trust child IDs from the browser.
7. Test confirmation emails, leader visibility, privacy export/erasure, cancellations.
8. Enable only on staging behind an explicit administrator-controlled switch.

No production change, payment migration or plugin deactivation is authorised by this plan.
