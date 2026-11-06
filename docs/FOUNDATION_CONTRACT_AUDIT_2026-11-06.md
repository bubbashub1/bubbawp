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

## Development-stage SQL and form gap matrix — initial assessment
This section compares identified feature requirements to source-defined storage. **Proposed** means not yet approved for schema creation; actual staging tables and form exports have not been inspected.

| Development area | Reuse/extend already defined | Candidate new storage only if justified | Form requirement / approach |
|---|---|---|---|
| Directory, calendar, timetable | `activities`, `venues`, `activity_venues`, `sessions`, `session_listing_links` | None initially; indexes/occurrence model to be validated | No Fluent Form for searching/filtering |
| Family profiles, favourites, planner, visited | `bh_family_profiles`, `bh_family_preferences`, `bh_saved_activities`, `bh_planner`, `bh_family_reminders` | School milestone rule/config only if cannot use existing reminders/options | Native authenticated fields; no new Fluent Form |
| Booking/reservations, attendees, consent | `bh3_bookings`, `bh3_booking_attendees`, `bh3_booking_consents`, `bh3_stripe_events` | Booking status transition history or reminder delivery ledger if existing records cannot satisfy audit/idempotency | Form 6 for enquiry, Form 7 for booking consent; verify separation and field mapping |
| Leader onboarding, payout, timetable | WP users, organiser mapping, existing listing/session tables and `bh3_payout_method` option/meta | Approval/invitation history only if not covered by existing schema | Native dashboard fields, not new Fluent Form by default |
| Support enquiries and public/private Q&A | Fluent Forms submissions; `bh_support_enquiry_status`, `bh_support_enquiry_history`; `bh_ask_questions`, `bh_ask_private_contacts`, `bh_ask_leader_categories`, `bh_ask_replies`, `bh_ask_audit` | No new tables until overlap between Q&A and enquiry workflow is resolved | Form 4 Ask Question; Form 5 Family Support, subject to export |
| Notifications, newsletters, automation | WP mail/FluentSMTP and existing event/booking IDs | `bhs_notification_templates`, `bhs_notification_jobs`, `bhs_notification_delivery_log` **candidates**; consider WP options/Cron and minimal logging first | Native settings/templates; opt-in form only if subscription data genuinely absent |
| Events, Expo, QR/check-in, exhibitors, sponsors, raffle | Inspect legacy Expo schema and ticket contracts first | `bhs_events`, `bhs_event_sessions`, `bhs_event_tickets`, `bhs_event_checkins`, `bhs_event_exhibitors`, `bhs_event_sponsors` **candidate entities, not approved tables** | Visitor registration, exhibitor application, sponsor enquiry, optional raffle consent — choose native vs Fluent Forms after legacy comparison |
| Claims, venue submissions, moderation | Organiser/venue/listing tables and WP users | `bhs_claim_requests`, `bhs_venue_submissions`, `bhs_moderation_audit` only after checking legacy and current pending status support | Prefer wizard/native forms with admin approval |
| Developments admin | Existing WP options `bh_development_tracker_statuses` and `bh3_development_statuses` | **No new SQL required** for v1; unified registry in PHP/config + WP options | Native WP Admin screen; **no Fluent Form** |

**Canonical decisions pending:** whether a session is a reusable timetable definition vs an occurrence; whether enquiries become bookings or remain separate records; native WP profile IDs vs legacy child/bump IDs; whether support Form 4 creates a Q&A record; whether event tickets reuse booking infrastructure. Do not create tables until those decisions and existing legacy schemas are verified.

**Development-stage form inventory:** Existing user-reported IDs 4 (Ask Question), 5 (Family Support), 6 (Interested in Joining), 7 (Booking Consent). New possible forms: event visitor registration, exhibitor application, sponsor enquiry; claim/venue submissions may be better native wizard workflows. All are *requirements to assess*, not requests to create duplicate forms.

## Legacy SQL source review — 26 core tables plus separate migrations (2026-11-06)
**Source:** `bubbaplugin/database/schema.sql` contains 26 `CREATE TABLE IF NOT EXISTS` definitions. These are definitions, **not evidence of deployment**. Additional SQL files below expand the legacy inventory.

### Reuse classification
- **Listings:** `bh_organisers`, `bh_activities`, `bh_venues`, `bh_sessions`. Reuse content and field intent; new WP `{prefix}activities`, `{prefix}venues`, `{prefix}activity_venues`, `{prefix}sessions` have different relationships. Legacy `bh_venues.activity_id` is not equivalent to a reusable many-to-many venue link.
- **Identity/family:** `bh_users`, `bh_user_preferences`, `bh_children`, `bh_bumps`, `bh_saved_activities`, `bh_planner`, `bh_hidden_planner_days`. Keep legacy IDs namespaced, map verified WP identity, and reconcile native `bh_family_profiles`/preferences/saved/planner before copying.
- **Bookings/consent:** `bh_bookings`, `bh_booking_sources`, `bh_booking_slots`, `bh_booking_reservations`, `bh_consent_profiles`, `bh_booking_consent_snapshots`. Strong reuse of provider/slot/reservation/consent semantics; avoid competing booking authorities with `{prefix}bh3_bookings`. Consent profiles include sensitive medical/emergency fields; minimise collection, enforce per-field sharing and retention; do not place these in generic admin registry logs.
- **Support/leader:** `bh_guidance_topics`, `bh_guidance_offers`, `bh_support_questions`, `bh_support_answers`, `bh_leader_faqs`, `bh_leader_expertise`. Reconcile with native Ask Support tables and Fluent Forms; choose a single question/answer source of truth.
- **Notifications/newsletters:** `bh_push_subscriptions`, `bh_push_notifications`, `bh_newsletter_subscribers`; `database/migrations/003_newsletter_roundup.sql` extends `bh_user_preferences` with newsletter preferences. Reuse subscriber/consent and delivery history concepts, but add a dedicated email delivery/idempotency ledger only if no existing table covers it.

### Additional legacy SQL files located and inspected
| File | Defined table(s) | Purpose |
|---|---|---|
| `database/migrations/2026-10-18-expo-foundation-staging.sql` | `bh_expo_reservations`, `bh_expo_tickets`, `bh_expo_exhibitors`, `bh_expo_tables` | Expo visitor/ticket/exhibitor/floor tables |
| `database/expo-booking-qr.sql` | `bh_expo_booking_qrs` | Hashed QR per reservation |
| `database/expo-family-checkins.sql` | `bh_expo_family_checkins` | Attendance counts per reservation |
| `database/expo-giveaway-claims.sql` | `bh_expo_giveaway_claims` | Giveaway claim deduplication |
| `sql/expo-raffle-prizes-2027.sql` | `bh_expo_raffle_prizes` | Raffle prizes |
| `database/sponsor-receipts.sql` | `wp_bh_sponsor_receipts` | Sponsor receipt ledger; hard-coded `wp_` prefix needs reconciliation |
| `database/leader-messages.sql` | `bh_leader_messages` | Leader inbox/messages |
| `sql/volunteer-assignments.sql` | `bh_volunteer_assignments` | Regional volunteer roles |

**Correction to earlier gap matrix:** Expo tickets, QR, exhibitors, check-ins and raffle are **not greenfield candidate tables**; legacy definitions already exist. Assess their schema, event scoping, data and actual staging deployment before proposing `bhs_events` etc. `bh_expo_*` tables are Expo-specific and may need an event-id/key strategy before use for general events.

### Data still needed — proposed minimal additions, not yet approved
1. **Cross-system identity/link registry:** verified mappings between legacy IDs and WP IDs for organisers, activities, sessions, families, bookings; prefer a deterministic mapping/import audit only if existing IDs/meta cannot provide a robust contract.
2. **Occurrence and capacity contract:** session recurrence, cancellations, exceptions, capacity/remaining seats, external provider slot key, last sync and provenance. Extend existing `bh_booking_slots` or native session/booking tables rather than inventing parallel slots.
3. **Booking lifecycle:** reservation vs confirmed enrolment, payment state, reminder delivery, status changes, consent version, source submission and idempotency keys. Extend existing booking schema; add a minimal history ledger only if needed.
4. **Event generalisation:** event identity, timed entry, tickets per email, QR check-in, exhibitor applications/approval, sponsor payments, raffle/giveaway consent; map onto existing Expo tables before new tables.
5. **Claims and venue moderation:** requester identity, record ownership, verification proof, approval status, timestamps and reviewer; check existing approval modules and schemas first.
6. **Communications:** opt-in evidence, template version, recipient, trigger, scheduled/sent status, retries and delivery result; reuse newsletter and push tables, consider new `bhs_message_delivery_log` only if required.
7. **Developments registry:** no SQL table initially; PHP feature manifest + WP options for status/notes/dependencies, with backward-compatible reads from both current trackers.

### Required pre-migration gates
- Inspect `SHOW CREATE TABLE`, indexes, counts and foreign keys in **staging read-only**; do not assume SQL file was run.
- Inventory remaining `database/`, `sql/`, and `migrations/` files, particularly Expo sponsors/raffle/volunteers and import tooling; this is not yet exhaustive.
- Compare column-by-column with native WP tables and confirm single authoritative write path for every entity.
- For forms 4–7 and Expo forms, inspect actual JSON definitions, notifications, consent versions, submission IDs and server-side validation.
- No implicit schema updates, global `bh3` replacement or data migrations under this documentation PR.

## Column-by-column reconciliation — priority entities (2026-11-06)
**Compared source definitions only:** legacy `bubbaplugin/database/schema.sql`, `database/migrations/2026-10-18-expo-foundation-staging.sql`, and native `bubbawp/includes/class-bh-schema.php`, `includes/class-bh-family-db-setup.php`. Live/staging schemas, rows and migration status unverified.

| Entity | Legacy fields / shape | Native WP fields / shape | Action |
|---|---|---|---|
| Organiser | `bh_organisers(id,user_id,organisation_name,slug,description,email,phone,website,status,...)` | `{prefix}organisers(id,name,slug,description,website_url,contact_email,...)` | Map `organisation_name→name`, `website→website_url`, `email→contact_email`; **missing** phone/status and verified WP owner mapping in native schema. Do not assume equal IDs. |
| Activity | `bh_activities(id,organiser_id,title,slug,description,category,age_range,county,price_from,booking_url,image_path,status,...)` | `{prefix}activities(id,organiser_id,title,slug,description,status,category,age_min_months,age_max_months,price_from,is_free,booking_url,image_id,...)` | Transform free-text age range to numeric bounds only when unambiguous; map legacy image paths to media IDs after attachment import; preserve county/location metadata and legacy ID. |
| Venue | `bh_venues(id,activity_id,venue_name,address,town,region,postcode,latitude,longitude,notes,...)` | `{prefix}venues(id,name,address_line1,town,postcode,region,latitude,longitude,...)` + `activity_venues(activity_id,venue_id)` | Normalize `venue_name→name`, `address→address_line1`, move activity link to join table; preserve notes and deduplicate shared venues. |
| Session | `bh_sessions(id,venue_id,day_of_week,price,term_time_only,frequency,start_date,end_date,...)` | `{prefix}sessions(id,activity_id,venue_id,day_of_week,start_date,end_date,frequency,term_time_only,status,...)` + `session_listing_links` | Legacy sessions attach via venue; native sessions attach activity explicitly. Resolve activity via verified venue link; check multi-activity sessions; **legacy price is absent from base native session schema**. |
| Family identity | `bh_users` and `bh_children(user_id,name,gender,date_of_birth,...)`, `bh_bumps(user_id,nickname,due_date,...)` | WP user + `{prefix}bh_family_profiles(id,user_id,profile_type,display_name,profile_date,...)` | Map verified legacy user to WP user, child/bump into typed profiles, preserve source IDs; avoid copying sensitive full dates into public booking payloads. Gender column is added by a separate implicit admin_init path, not base schema: fix migration. |
| Family preferences | `bh_user_preferences` contains region/town/day/categories, notification flags, newsletter fields via migration | `{prefix}bh_family_preferences(user_id,preferences_json,updated_at)` | Define versioned JSON keys, migrate only opted-in preferences and keep newsletter consent evidence; avoid conflicting defaults. |
| Saved/planner | `bh_saved_activities(user_id,activity_id,...)`; `bh_planner(user_id,activity_id,visited,...)` | `{prefix}bh_saved_activities(id,user_id,activity_id,...)`; `{prefix}bh_planner(id,user_id,activity_id,session_id,planned_date,visited,...)` | Identity/ID mapping required before import. Native planner supports session/date; legacy planner lacks these. Unique saved user/activity exists native. |
| Booking | `bh_bookings(id,user_id,activity_id,session_id,status,consent_given,amount,...)`; `bh_booking_reservations(id,slot_id,user_id,status,quantity,...)`; `bh_booking_slots` has occurrence/capacity/external source | `{prefix}bh3_bookings(id,public_reference,session_id,occurrence_date,activity_id,organiser_id,customer_user_id,booking_form_id,booking_submission_id,places,...,payment_status,...)` | **Critical:** native base booking has `payment_status` but no general `booking_status`/enrolment state; separate payment from reservation/confirmed/enrolled/cancelled status. Map slot IDs to occurrence; decide one authoritative booking row. |
| Booking consent | `bh_consent_profiles` (medical/emergency/photo fields) and `bh_booking_consent_snapshots` (versioned JSON at booking time) | `{prefix}bh3_booking_attendees` (profile ID/name/gender/year), `{prefix}bh3_booking_consents` (consent key/version/response/time/actor/source) | Native key/value consent lacks complete historical profile snapshot; determine minimum consent requirements and immutable snapshot handling; enforce per-field consent and retention. |
| Expo | `bh_expo_reservations(event_key,contact_name,email,adults,children,ticket_type,payment_status,booking_status,email_status,consent_at,...)`; tickets, exhibitors, tables | No Expo tables in native base schema | **Reuse legacy Expo foundation**, not speculative new ticket/QR tables. Check whether event_key supports multiple events and whether general event identity/metadata is missing. |

### Immediate schema proposals (design only)
1. **Extend native bookings** with `booking_status` (pending/enquired/reserved/confirmed/enrolled/cancelled/expired as carefully defined), `status_updated_at`, `source_slot_id` or external occurrence key if necessary; do not overload `payment_status`. Add history only if transition audit cannot be reconstructed.
2. **Session capacity and price:** confirm whether capacity/price are per recurring session or individual occurrence; legacy `bh_booking_slots` already models `starts_at`, `ends_at`, `capacity`, `seats_remaining`, `price`, `external_key`, `last_synced_at`. Prefer a single occurrence/slot authority rather than duplicating capacity on `sessions`.
3. **Organiser contact/ownership:** preserve phone/status and verified WP user-to-organiser relationship; never trust arbitrary self-assigned organiser IDs.
4. **Family profile provenance:** if importing, use a migration map for legacy child/bump ID and profile type; avoid a single ambiguous numeric `family_profile_id`.
5. **Expo:** keep `bh_expo_*` definitions until actual database checks. General event details and multiple event editions may require a separate event metadata record, but do not create it without checking legacy Expo API assumptions.

### Validation gates
- Column names listed here reflect base source DDL; runtime-added columns and other migrations may already extend them.
- Read-only staging `SHOW CREATE TABLE` and counts, unique constraints, indexes and any existing migration tracking.
- Explicit approved migration plan with backup, dry run, mapping report, idempotency and rollback.
