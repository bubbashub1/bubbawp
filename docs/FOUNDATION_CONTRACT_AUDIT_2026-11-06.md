# Foundation contract audit — 2026-11-06

Status: **Initial source inspection; not a live/staging database inventory**. Do not run migrations from this document.

## Verified repository entrypoints
- `bubbawp/plugin/bubba-hub-manager/bubba-hub-manager.php` loads `class-bh-manager.php` and `class-bh-development-tracker.php`.
- `class-bh-manager.php` also loads `class-bh-developments-admin.php` and registers **Bubba Hub → Developments**. Thus there are **two separate roadmap/tracker screens**, with distinct option keys. Consolidate rather than add a third.
- `bubbatheme/functions.php` registers `[bh_compare_activities]` and `[bh_header_search]`; comparison uses browser localStorage. The theme also contains activity rendering. Review plugin/theme responsibility before moving functionality.
- `bubbaplugin/database/schema.sql` defines standalone `bh_*` data contracts, including organiser, activity, venue, session, user, family, booking, consent, support and external booking structures. These are **source definitions**, not proof of tables present on staging.
- `bubbawp/includes/class-bh-schema.php` defines WordPress-prefix-aware tables: organisers, venues, activities, activity_venues, sessions, session_listing_links, bh3_bookings, bh3_stripe_events, bh3_booking_consents and bh3_booking_attendees. The manager exposes staging-only, explicitly confirmed installation routes. Do not run these without comparing actual schemas.

## Highest-risk conflicts
1. **Two booking models:** legacy `bh_bookings`/`bh_booking_reservations` vs new `{prefix}bh3_bookings` and attendee/consent tables. Decide authoritative booking and reconciliation IDs before integrating forms.
2. **Different venue models:** legacy `bh_venues.activity_id` vs WP `{prefix}activity_venues` join table. Map cardinality and migration rules.
3. **Different user identities:** legacy `bh_users` vs WordPress users / membership identity. Do not silently mix IDs.
4. **Duplicate admin roadmap screens:** `bh-development-tracker` and `bh3-developments`. Preserve saved statuses when consolidating.
5. **Theme-owned shortcodes:** clarify plugin-owned data logic and graceful theme fallback.
6. **Old checklist status:** `MASTER_CHECKLIST.md` describes earlier foundation stage; update only after evidence-based source and staging checks.

## Form registry (reported configuration; verify on staging)
- Fluent Forms #4: Ask a Question.
- #5: Family support.
- #6: Interested in joining / enquiry.
- #7: Booking consent.
For each: export JSON, field keys, consent version, notification destination, entry ID, webhooks/hooks, linked table/record IDs, permissions and retention. Never assume the form ID is stable across environments.

## Proposed single development registry contract
Record: stable feature ID; 3.0/4.0; sector; title; purpose; priority; status; owner; existing files; WP screen/shortcode; existing tables; proposed tables; form ID and field mapping; identity/permission contract; dependencies; migration needed; QA evidence; PR link; deployment stage.
Statuses must distinguish **draft/planned**, **building**, **ready for QA**, **QA passed**, **deployed** and **blocked**. Manual statuses must not imply automated test success.

## Next read-only checks before any SQL/feature PR
- Inspect complete plugin source for `CREATE TABLE`, `dbDelta`, `$wpdb->prefix`, `add_shortcode`, `fluentform`, `admin_post_`, REST routes and scheduled hooks.
- Compare actual staging `SHOW TABLES` / `SHOW CREATE TABLE` with both source schemas; report columns, keys, row counts and owners, without copying sensitive records.
- Export Fluent Forms 4–7 schemas and confirm WP user/member mapping.
- Build canonical ERD: organiser → activity ↔ venue → session/occurrence → enquiry/reservation/booking → attendee/consent; family user → child/bump → planner.
- Review existing 3.0 and 4.0 roadmap items and deduplicate, preserving saved WP option values.
- Staging-only tests, backup, and explicit approval before migrations or deployment.

No production database changes authorised by this audit.

## Approved naming convention — 2026-11-06
User approved **`bhs`** as the stable naming prefix for **new Bubba Hub development**, independent of the 3.0/4.0 product version.

- New custom tables: use `bhs_*` as the application-specific table stem, with actual WordPress table names resolved via the configured prefix and checked for double-prefix/collision (e.g. `$wpdb->prefix . 'bhs_bookings'` only after confirming this is the desired physical name).
- New PHP classes, action names, option keys, admin slugs and shortcodes: prefer consistent `BHS_*`, `bhs_*`, and `bhs-*` forms, according to identifier type.
- **Do not rename** existing `bh_*`, `bh3_*`, WordPress-prefixed tables, hooks, option keys, shortcodes, admin slugs, form IDs or external endpoints as part of the naming decision.
- Provide compatibility aliases/adapters where public identifiers change. For database renames, require verified staging schema and data, a migration and rollback plan, and explicit approval before executing.
- Audit the `bh3` references and produce an old → new mapping before implementation. Naming approval is **not** approval to execute database migrations.
