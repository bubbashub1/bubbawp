# Bubba Hub 3.0 → 4.0 — Development Blueprint
Status: planning baseline, 2026-11-06. No migration or deployment authorised by this document.

## Mission and rules
**Does it save time, make family life easier, or help people connect? If it doesn't, it probably isn't a priority.**
Prioritise less leader admin, family usefulness, community connection, less AI dependence.
Use **one authoritative domain record**, prefer existing tables and form definitions, preserve legacy data, use WP permissions/identity, and validate staging before changes. Do not equate code present in repository with deployed or tested.

## Sector delivery contract
For **every** feature maintain:
- Feature ID / phase / priority / owner
- User outcome and acceptance criteria
- Canonical existing tables and fields; legacy import/mapping
- Proposed field changes and draft SQL path, indexes, uniqueness, privacy/retention
- Form ID/export, fields, conditional rules, notifications and submission-to-table map
- Shortcode/block/REST endpoints and permissions
- Parent/child page paths, template and navigation
- Dependencies, status **separately** for schema, forms, code, QA and deployment
- Test scenarios, rollback plan and release decision

Status vocabulary: **Not assessed → Reuse confirmed / Draft prepared → Staging validated → Implemented → Tested → Ready to deploy → Deployed**; **Blocked** when appropriate. Do not claim staging validated without actual DB/form checks.

## Canonical page hierarchy (planned; verify WordPress pages and slugs)
```
/
├── directory/ [search; list/calendar/planner]
│   └── activity/{slug}/ [canonical activity permalink, not necessarily a child WP page]
├── organiser/{slug}/ [organiser permalink]
├── my-hub/
│   ├── profile/
│   ├── children/
│   ├── preferences/
│   ├── planner/
│   └── favourites/
├── leader/
│   ├── about/
│   ├── new-activity/
│   ├── bookings/
│   ├── messages/
│   ├── venues/
│   ├── business/
│   └── payment/
├── guidance/
│   ├── resources/
│   ├── blog/
│   └── ask/
├── events/ [future sector]
│   └── {event-slug}/ [future template]
├── membership-join/
├── auth/
└── legal/bookings/
```
These are target URLs, not proof that every WP page exists. Keep WP post-name permalink routing for activity/organiser; don't create a competing static /activity/ catch-all.

## Sector register
| Sector / phase | Data authority / reuse | Fields and proposed gaps | Forms | Shortcodes / integration | Pages / templates | Immediate next gate |
|---|---|---|---|---|---|---|
| Directory & Listings / 3.0 | Native organisers, activities, venues, activity_venues, sessions, session_listing_links; legacy bh_organisers, bh_activities, bh_venues, bh_sessions | Verified IDs, age bounds, category, accessibility, location, session recurrence/price, venue dedupe | Native listing wizard | `[bubbahub_directory]`, `[bubbahub_directory_preview]`, `[bubbahub_activity]`, `[bh_admin_activity_wizard]`; AJAX search planned | /directory/, /activity/{slug}/, /organiser/{slug}/ | Confirm field-level mapping, WP permalink routing and slot ownership |
| Family Hub / 3.0, 4.3 | WP users; bh_family_profiles, bh_family_preferences, bh_saved_activities, bh_planner, bh_family_reminders; legacy bh_children/bh_bumps | Typed profile ID, gender, date/year-of-birth privacy, pregnancy due date, school milestone, preferences, visited/planned date | Native profile forms; no new Fluent Form by default | `[bubbahub_family_hub]`, `[bubbahub_family_add_profile]`, `[bubbahub_family_profile]`, `[bubbahub_family_profiles]`, `[bubbahub_family_favourites]`, `[bubbahub_save_activity]`, `[bubbahub_family_settings]` | /my-hub/ and five child routes | Verify WP identity, migration provenance, explicit gender migration |
| Leader Portal / 3.0, 4.4 | WP user↔organiser mapping, organiser/venue/session tables; legacy bh_leader_faqs, bh_leader_expertise, bh_leader_messages | Verified owner, contact/status, payout method, venues, session editor, enquiries, leader FAQs | Native wizard and account editor; enquiry via form 6 | `[bh_leader_dashboard]`, `[bh_leader_bookings]`, `[bh_leader_booking_dashboard]`, `[bh_leader_venues]`, `[bh_leader_business]`, `[bh_leader_payout_settings]`, `[bh_leader_support]` | /leader/ with about/new-activity/bookings/messages/venues/business/payment | Verify access and organiser ownership; map fields to forms |
| Booking & Consent / 3.0, 4.2 | Native bh3_bookings, bh3_booking_attendees, bh3_booking_consents, bh3_stripe_events; legacy bh_booking_sources/slots/reservations, bh_consent_profiles/snapshots | Booking vs payment status, occurrence/capacity, attendee IDs, consent version/snapshot, reminders, source/provider ID | #6 enquiry; #7 booking consent (verify JSON) | `[bh_parent_booking]`, `[bh_booking_readiness]`, `[bh_leader_booking_dashboard]`; Stripe/Fluent Forms | /leader/bookings/, /leader/payment/, activity booking CTA; /legal/bookings/ | Choose one booking writer, test form IDs and consent |
| Support & Guidance / 3.0 | Native bh_ask_questions, bh_ask_private_contacts, bh_ask_leader_categories, bh_ask_replies, bh_ask_audit; Fluent Forms support inbox; legacy bh_support_questions/answers | Visibility, moderation, verified leader replies, private contact, category, response history | #4 Ask Question; #5 Family Support | `[bh_ask_question]`, `[bh_support_hub]`, `[bh_support_articles]`, `[bh_support_resources]`, `[bh_website_help]`, `[bh_leader_ask_inbox]` | /guidance/, /guidance/resources/, /guidance/blog/, /guidance/ask/ | Confirm which submission creates public/private question |
| Membership / 3.0 | Simple Membership + WP user/leader mapping; membership plugin tables | Family/Leader Free/Pro permissions, annual pricing, verified upgrade state | Membership plugin registration (not a new Fluent Form by default) | `[bh_membership_registration]`; Simple Membership | /membership-join/, /auth/ | Verify tier IDs and permissions on staging |
| Communications / 3.0, 4.5 | FluentSMTP, WP mail, legacy bh_newsletter_subscribers, bh_push_subscriptions, bh_push_notifications | Consent, frequency, template/version, delivery/idempotency and unsubscribe | Native preferences; opt-in form only if missing | Email templates and notifications; no verified public shortcode yet | Admin Communications; My Hub preferences | Inspect existing send/log workflow before new SQL |
| Events & Expo / 4.6 | Legacy bh_expo_reservations/tickets/exhibitors/tables/booking_qrs/family_checkins/raffle_prizes, sponsor receipts | Event identity, timed entry, capacity, one QR per reservation/email, exhibitor/sponsor approvals | Visitor, exhibitor, sponsor (platform/IDs TBD) | Event template/shortcodes **TBD**; QR check-in existing legacy PHP | /events/, /events/{event-slug}/; Expo page TBD | Verify legacy schema and multi-event generalisation |
| Claims, Moderation & Data Quality / 3.0, 4.1/4.7 | Existing listing/admin approval modules; support workflow | Claimant verification, ownership, duplicate review, evidence, audit | Native claim and venue submission, no duplicate form until verified | Admin tools; shortcode TBD | Admin Listings/Moderation; leader venues | Inspect existing approval persistence |
| Platform & Developments / 3.0, 4.7 | WP options bh_development_tracker_statuses and bh3_development_statuses; schema audit docs | Unified feature manifest, separate readiness statuses, dependencies, QA evidence | None | WP admin Bubba Hub → Developments | /wp-admin/admin.php?page=bh3-developments | Consolidate duplicate tracker menus without losing statuses |

## Implementation sequence for each sector
1. **Discovery**: compare legacy SQL, native schema, actual staging SHOW CREATE TABLE and counts; identify authoritative IDs.
2. **Contract**: agree fields, permissions, validation, retention, forms, pages, shortcodes, URLs, acceptance tests.
3. **Preparation**: create reviewed, idempotent, versioned SQL migration and rollback; draft/validate form export JSON; prepare page/shortcode placeholders where missing.
4. **Staging foundation**: backup, explicit install, verify constraints and form submissions; no silent schema changes.
5. **Code integration**: feature flags, domain writes, server-side owner checks, mail/booking idempotency, frontend integration.
6. **QA and deployment**: test guest/member/leader/admin, mobile, security and consent, error paths; merge PR, deploy staging, verify; production only after approval.

## Draft pack
- `01-booking-lifecycle.DRAFT.sql`
- `02-mapping-and-delivery.DRAFT.sql`
- `03-events-generalisation.DRAFT.sql`
- `FORM_CONTRACTS.md`
- `README.md`

## Outstanding questions
Actual staging DDL and plugin-generated columns; real Fluent Forms #4–7 exports; authoritative session occurrence and booking state; existing Expo sponsorship and event schema; actual WP page tree and shortcode placements. These remain **unverified** until runtime inspection.
