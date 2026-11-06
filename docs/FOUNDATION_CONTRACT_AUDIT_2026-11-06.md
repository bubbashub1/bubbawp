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

## Second pass — source-level integration inventory (2026-11-06)
This is a **targeted source/code-search inventory**, not an exhaustive recursive repository scan or staging verification. GitHub code search returns bounded matches; additional files may exist.

### Naming decision: BHS
- **New** identifiers should use `bhs` / `bhs_` consistently, without a version number.
- Preserve existing `bh_`, `bh3_`, `BH3_*`, `BH_*_3`, shortcodes, WP option/meta keys and admin-post actions until compatibility is tested.
- Audit usages before aliases/renames; do **not** globally search-and-replace. Existing `bh3_organiser_id`, `bh3_booking_form_id`, `bh3_session_booking_config`, `bh3_development_statuses` and `bh3_bookings` are active data contracts.
- New WP table names must be assembled from `$wpdb->prefix` plus a documented `bhs_*` suffix, after checking for existing tables and avoiding `wp_bhs_*` vs standalone `bh_*` collisions.

### Confirmed shortcode registrations (selected modules)
| Area | Shortcodes | Source |
|---|---|---|
| Directory/activity | `bubbahub_directory`, `bubbahub_directory_preview`, `bubbahub_activity`, `bh_admin_activity_wizard` | directory, preview, activity-detail, manager |
| Leader | `bh_leader_dashboard`, `bh_leader_bookings`, `bh_leader_booking_dashboard`, `bh_leader_support`, `bh_leader_account`, `bh_leader_statistics`, `bh_leader_venues`, `bh_leader_business` | Leader modules |
| Marketplace | `bh_leader_payout_settings`, `bh_parent_booking`, `bh_booking_readiness`, `bh_leader_email_verification`, `bh_leader_account_setup` | marketplace settings |
| Family | `bubbahub_family_hub`, `bubbahub_family_add_profile`, `bubbahub_family_profile`, `bubbahub_family_profiles`, `bubbahub_family_favourites`, `bubbahub_save_activity`, `bubbahub_family_settings`, `bubbahub_family_schema_audit` | Families modules |
| Support | `bh_ask_question`, `bh_support_hub`, `bh_support_articles`, `bh_support_resources`, `bh_website_help`, `bh_leader_ask_inbox`, `bh_leader_ask_support` | Support and Ask Support modules |
| Membership | `bh_membership_registration` | membership registration |
| Theme | `bh_compare_activities`, `bh_header_search` | bubbatheme/functions.php |

Some are staging-only, feature-flagged, or fallback registrations; presence in source does **not** establish availability on a particular page.

### Confirmed write/action handlers (selected)
- Activities/venues/sessions: `admin_post_bh3_update_activity`, `admin_post_bh3_change_activity_status`, `admin_post_bh3_save_venue`, `admin_post_bh3_attach_venue`, `admin_post_bh3_detach_venue`, `admin_post_bh3_save_session`, `admin_post_bh3_delete_session`.
- Leader/booking: `admin_post_bh3_leader_account_save`, `admin_post_bh3_save_commission`, `admin_post_bh3_verify_stripe`, `admin_post_bh3_stripe_onboard`, `admin_post_bh3_save_payout_method`, `admin_post_bh3_admin_link_leader`, `admin_post_bh3_request_leader_email_verification`, `admin_post_bh3_confirm_leader_email_verification`.
- Family: `admin_post_bh3_native_family_profile`, `admin_post_bh3_native_family_update`, `admin_post_bh3_native_family_delete`, `admin_post_bh3_family_reminder_save`, `admin_post_bh3_family_reminder_delete`.
- Support: `admin_post_bh3_support_install_schema`, `admin_post_bh3_save_ask_support`.
- Developments: `admin_post_bh3_save_development_statuses`; another tracker saves by direct admin form POST.
**Important:** Retain existing handler names during initial BHS adoption; forms may post directly to them.

### Source-confirmed tables and dependencies
| Contract | Source-defined storage | Integration concern |
|---|---|---|
| WP listings | `{prefix}organisers`, `{prefix}activities`, `{prefix}venues`, `{prefix}activity_venues`, `{prefix}sessions`, `{prefix}session_listing_links` | Reconcile legacy `bh_*` IDs, schema and relationships |
| WP bookings | `{prefix}bh3_bookings`, `{prefix}bh3_booking_attendees`, `{prefix}bh3_booking_consents`, `{prefix}bh3_stripe_events` | Canonical booking/payment records not yet approved |
| Native family | `{prefix}bh_family_profiles`, `{prefix}bh_family_reminders` | Distinct from legacy `bh_children`/`bh_bumps`; map profiles before booking consent |
| Support workflow | `{prefix}fluentform_submissions`, `{prefix}bh_support_enquiry_status`, `{prefix}bh_support_enquiry_history` | Support statuses reference Fluent Forms entry ID + form ID, without duplicating answer text |
| Identity | WP user metadata `bh3_organiser_id`; legacy `bh_legacy_user_id` bridge; legacy `bh_users` | Never assume WP user ID equals legacy user ID |

### Forms and third-party integrations
- Booking marketplace code reads `{prefix}fluentform_submissions`, defaults `bh3_booking_form_id` to **7**, and uses `bh3_booking_form_field_map` to bind submissions. Form 7's actual field keys and hooks still need verification from staging.
- Support inbox uses configured `bh_support_fluent_form_ids` and separate workflow tables; verify form 4/5 configuration and admin access.
- Marketplace registers staging-only REST routes for test webhook, parent booking binding and bound checkout. Stripe test account, webhook verification, idempotency, payout and permission review remain release gates.
- `docs/FAMILY_ACCOUNT_LINKING.md` documents the legacy MU bridge `bh_wp_resolve_legacy_identity()` and `bh_legacy_user_id` metadata; it explicitly warns against automatic email-only linking. FluentAuth does not automatically create legacy PHP sessions.
- Simple Membership registration is a separate shortcode. Verify tier/role mapping and payment state independently.
- Theme search and comparison are registered in `bubbatheme`; the plugin remains responsible for protected data operations.

### High-priority safety finding
`modules/Families/class-bh-family-native-profiles.php` hooks `maybe_add_gender_column` on `admin_init` and, on staging for an administrator, directly executes `ALTER TABLE {prefix}bh_family_profiles ADD COLUMN gender ...` if missing. **This is an implicit schema mutation on admin page load**, unlike the explicitly confirmed schema installers. Before merging a unified admin screen, move this to an explicit, backed-up, versioned staging migration with a dry-run preview and audit trail. Do not trigger it as part of a read-only inventory.

### Proposed canonical relationship contract (requires staging validation)
```
WP user --verified link--> organiser
organiser 1--N activities
activities N--N venues (activity_venues)
activities N--N sessions (session_listing_links) [verify actual semantics]
session + occurrence_date 1--N booking
booking 1--N attendees
booking 1--N consent records / snapshots
WP user 1--N native family profiles (child/bump)
booking attendee 0..1 --> family profile
Fluent Forms submission (form_id + entry_id) --> booking/enquiry/support workflow
WP user --verified legacy bridge--> legacy bh_users --> bh_children/bh_bumps
```
Use explicit ID namespaces in registry records: `wp_user_id`, `legacy_user_id`, `organiser_id`, `activity_id`, `session_id`, `booking_id`, `form_id`, `submission_id`. A shared numeric value does not prove identity across namespaces.

### Next checks
1. Read-only staging schema comparison and form 4–7 JSON export, including actual field names, notifications and hook configuration.
2. Verify `session_listing_links` cardinality, occurrence uniqueness, and booking-to-Fluent-Forms idempotency.
3. Inventory all remaining files/routes/actions; current table is selected source evidence, not exhaustive.
4. Consolidate two admin tracker screens without losing `bh_development_tracker_statuses` or `bh3_development_statuses`.
5. Write BHS compatibility adapter tests before changing existing persistent keys.
