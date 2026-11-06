# Bubba Hub 4.0 — Unified Development Roadmap

Status: **Unified delivery and future roadmap; statuses require independent verification**  
Created: 2026-11-05  
Owner: Bubba Hub South West

## Unified scope and delivery rule (2026-11-06)

**Bubba Hub 4.0 is the single project roadmap.** The former 3.0 migration, parity, booking, family, leader, admin and QA work is now the **4.0 Core / Launch Foundation** workstream, not a second product or abandoned backlog. The previous 4.1–4.7 roadmap identifiers remain stable to preserve saved WordPress tracker statuses; additional inspiration scope is tracked as 4.8.

This is a roadmap consolidation only: retain the `bubbawp` plugin, `bubbatheme`, existing plugin version, database tables, class names, routes, shortcodes, forms, option keys and Git history. Do not rename or migrate data as a side effect. Existing status records in both WP development trackers must be retained until a verified, lossless consolidation.

**4.0 Core / Launch Foundation (formerly 3.0):** dynamic directory and activity pages, organiser/venue/session relationships, calendar and location search, family profiles and planner, leader portal and booking management, consent/payment lifecycle, memberships, support, events parity, communications, security, accessibility, deployment and staging QA. Source presence is not proof of completion. Track every journey as planned → built → staging-tested → deployed.

**Launch gates:** activity discovery → session → booking/enquiry; family profile → saved/planner; leader ownership → listing/timetable → reservation; verified consent/recipient handling; payments and membership access; legacy link/data parity; admin backup/rollback; mobile and accessibility testing. Essential unfinished parity remains a launch requirement, not deferred to enhancements.

### Family inspiration — named features to preserve

These are explicit product requirements, **not yet verified as implemented**. Build with deterministic directory filters, real timetables, manually curated content and existing family preferences; no required AI or paid weather service.

| Feature | Family experience | Data / rules | Priority |
| --- | --- | --- | --- |
| **Rainy Day Rescue** | Indoor, covered and weather-friendly nearby activities, plus curated at-home rainy-day ideas | Indoor/weather suitability tag; location, age, opening/session times, budget; no fabricated live weather | Core inspiration |
| **What's On This Weekend?** | Nearby weekend sessions/events matching ages, budget and location; save to planner | Actual Saturday/Sunday occurrences, availability caveat, free filter | Core inspiration |
| **Free & Under £5 Finder** | Affordable activities for families | Explicit per-person/per-session pricing and free category; avoid unknown-price claims | Core inspiration |
| **School Holiday Hub** | Holiday clubs, one-off events and family outings | Verified date ranges, school-holiday region, age and cost | Core inspiration |
| **What Can We Do Right Now?** | Quick options today/soon | Verified published opening times and session occurrences, not invented real-time availability | Enhancement |
| **Inspire Me / Surprise Me** | Rule-based discovery and family-friendly suggestions | Age, location, interests, days, accessibility and budget; optional user control | Core inspiration |
| **Bubba Adventures / Family Trails & Days Out** | Curated trails, themed days out and printable guides | Curated local routes and accessible descriptions | Enhancement |
| **Family Week and Bubba Brief** | Weekly personalised ideas, planner items, reminders and saved activities | Family preferences, saved/planned/booking signals; opt-in notifications | Enhancement |
| **Little Explorers Passport / Memories** | Visited places, milestones and optional private memories | Explicit family ownership, privacy and retention | Future |
| **Family Activity Lists** | Rainy Day Ideas, Weekend Ideas, Places to Try | Family-managed saved lists; discoverability free | Enhancement |
| **Sibling matching and accessibility** | Ideas suitable for multiple children's ages and needs | Consent-minimised profiles, inclusive accessibility filters | Enhancement |

Discovery and basic suggestions stay available to Free users. Pro can add convenience (advanced lists, brief, planning tools), but must not lock basic local activity discovery behind a paywall. No birthday-party finder in this scope.

## Product vision and decision principles

**Vision:** A family-first community platform that helps families discover, connect, plan and be inspired, while enabling activity leaders to manage their services with less administration and without dependence on AI.

**Three product pillars:**
1. **Less AI dependence, more dependable automation:** deterministic workflows, structured WordPress data, reusable templates, clear user controls and optional rather than required AI. Essential browsing, booking, notifications and publishing must work without AI services.
2. **Less administration for leaders:** one mobile-friendly portal for listings, schedules, venues, bookings, messages, reminders and payments; minimise duplicate entry, unnecessary clicks and manual reconciliation.
3. **A useful, connected and inspiring family portal:** personal profiles, discovery, favourites, planning, support, community connections and relevant ideas for family time, with privacy and safeguarding built in.

**Feature prioritisation test:** Does this feature (a) save time, (b) make family life easier, or (c) help families and local leaders connect? Prefer features that measurably achieve at least one. Avoid adding complexity or recurring service costs without a clear benefit.

**Outcome measures to define before delivery:** leader minutes per routine task, percentage of bookings/reminders handled without manual intervention, parent repeat usage, activity discovery-to-booking completion, data freshness, and support/connection engagement. Do not collect unnecessary child or family data.

## Cross-sector guiding principle

> **Does it save time, make family life easier, or help people connect? If it doesn't, it probably isn't a priority.**

Apply this principle to all Bubba Hub development, including 3.0 parity work and future releases, across Directory, Family Hub, Leader Portal, Bookings, Events, Support & Guidance, Community, Membership, Admin and Communications.

### Feature decision test

- **Save time:** reduce repetitive admin, searching, clicks or duplicate entry.
- **Make family life easier:** improve accessible discovery, planning, booking, confidence and support.
- **Help people connect:** enable appropriate, safe connections among families, leaders and local services.
- Meeting one goal makes a feature worth considering; two or three strengthen its priority.
- Features meeting none should normally be deferred **except** essential security, safeguarding, privacy, accessibility, legal compliance, data integrity or reliability work.

**Design guardrails:** mobile-first simplicity; accessible and inclusive experiences; privacy and safeguarding by design; core functions operate without AI or new paid dependencies; avoid feature bloat.

## Scope and release boundary

Bubba Hub **4.0 Core / Launch Foundation** includes the existing WordPress migration, legacy feature parity, security, staging QA, and reliable core Directory, Family Hub, Leader Portal and Booking system. Do not defer critical legacy parity or safety fixes to later enhancement phases simply because they are unfinished.

Bubba Hub **4.0 enhancement phases** cover automation, time-saving workflows, family inspiration and sustainable revenue. This document is planning only: no feature here is implemented or approved for immediate deployment by virtue of inclusion.

Constraints: prefer existing WordPress infrastructure, no AI or additional paid services at this stage; preserve established data tables and access boundaries; prioritise low admin overhead, mobile usability and privacy.

## Proposed phases

| Phase | Workstream | Candidate features | Dependencies |
| --- | --- | --- | --- |
| 4.1 | Data Quality & Automation | Listing health dashboard, missing fields/schedules, broken booking links, organiser re-verification, duplicate activity/venue/organiser detection, safe merge preview, stale listing flags | 4.0 core directory and data integrity |
| 4.2 | Smart Booking & Timetables | Waiting lists, space-available notifications, session cancellation/rescheduling, quick bulk timetable editor, recurring schedule patterns, parent booking history improvements | 4.0 core booking consent, enrolment, session and notification QA |
| 4.3 | Family Hub Plus | Planner Pro parity review, age-appropriate recommendations, school application tracker, pregnancy reminders, enhanced personal calendar and booking history | 4.0 core family profiles, planner and privacy controls |
| 4.4 | Leader Business Tools | Performance dashboard, leader onboarding checklist, social tools parity review, optional listing boosts, leader articles/FAQs and entitlement rules | 4.0 core leader permissions, storefront, memberships |
| 4.5 | Communications Centre | Delivery history, notification preferences, message templates, newsletter roundups, opt-in and unsubscribe controls, automated operational reminders | 4.0 core FluentSMTP, consent and verified recipient handling |
| 4.6 | Events & Expo Plus | Recurring events, ticketing enhancements, QR check-in, volunteer tools, exhibitors, sponsor packages, raffle/reporting review | 4.0 core Events sector templates and shared booking foundation |
| 4.7 | Platform & Administration | Audit trails, advanced exports, team roles, accessibility/SEN metadata and filters, admin reporting, safe legacy redirect monitoring | 4.0 core launch and migration controls |
| 4.8 | Family Inspiration & Discovery | Rainy Day Rescue, Weekend Finder, Free & Under £5, School Holiday Hub, Inspire Me, Family Trails, Bubba Brief, Little Explorers Passport | Core directory, session dates, family preferences and planner |

## PR delivery audit — 2026-11-06

**Source:** recent GitHub pull request states reviewed on 2026-11-06. A merged PR proves code landed in main, **not** that staging or live functionality was tested.

| PR | State at review | Scope | Next gate |
| --- | --- | --- | --- |
| #156 | Merged | Booking lifecycle transition and organiser-scoped authorisation helpers | Enforce in actual write endpoints |
| #157 | Merged | Lifecycle and organiser-isolation PHP smoke tests | Extend to real booking persistence |
| #158 | Merged | CI runner for booking lifecycle smoke tests | Keep CI passing on subsequent PRs |
| #159 | Merged | Fluent Form #7 consent field contract | Verify deployed form definition |
| #160 | Merged | Form #7 trusted booking binding validator | Add authorised integration tests |
| #161 | Merged | Dormant optional Turnstile server verifier | Configure/test only when needed |
| #162 | Merged | Read-only authenticated Form #7 booking ownership resolver | Guest token design, consent persistence and hook still missing |
| #163 | Open draft | Additive booking_status schema declaration and manual staging migration plan | Review legacy records, backup, schema diff and migration approval |
| #164 | Open draft | One 4.0 roadmap, inspiration and core requirements | CI, review, merge, staging admin verification |

### Immediate development sequence

1. **Merge and validate roadmap PR #164**, without schema or user-data changes; confirm existing saved development statuses still display.
2. **Review PR #163** independently. Do not assume schema declaration implies an installed column; no automatic SQL and no historical booking status assumptions.
3. **Consolidate two Developments admin screens** after comparing their option keys and current UI. Preserve `bh_development_tracker_statuses`, `bh3_development_statuses` and `bhs_development_readiness` if present; avoid deleting or silently overwriting saved status data.
4. **Booking read-only integration first:** leader-scoped booking list/calendar and parent-facing booking state, with tests for permissions and no child details leaked.
5. **Booking writes only after staging sign-off:** explicit canonical booking authority, capacity/concurrency, trusted Form #7 mapping, guest-token design, versioned consent, booking/payment separation, payment webhook idempotency and notification failure handling.
6. **Family inspiration data foundation:** add verified indoor/covered suitability, facilities, age and price metadata contracts before surfacing Rainy Day Rescue or weekend recommendations. No invented weather/availability.
7. **Staging journey tests:** family discovery → timetable → enquiry/booking → planner; leader listing → booking → enrolment; consent, membership and email flows; then controlled deployment.

**No feature is marked QA-passed or deployed solely from PR merge.** Do not run migrations, Stripe writes or send customer emails from roadmap work.

## Core feature inventory — explicit acceptance checklist

This is the consolidated functional inventory for **4.0 Core**, not an assertion that every item is implemented. Each journey needs evidence from staging before being marked complete.

| Area | Core capabilities to preserve or complete |
| --- | --- |
| Directory | Live/AJAX search and filters (category, age, day, price, region, town, session, term-time, free); list/map/calendar/planner results; near-me/postcode; sorting; mobile navigation; accessible results |
| Activity & organiser | Dynamic /activity/{slug}/ and /organiser/{slug}/, maps, timetable, save/planner/visited/compare, booking/enquiry CTA, verified organiser, claims and edit history |
| Family Hub | WordPress family profiles for children and bumps, gender/year-of-birth sharing controls, interests/days/location, favourites, planner, visited, school application and pregnancy reminders, privacy and user ownership |
| Leader Portal | Front-end listings wizard, existing/new venues, timetable editor, leader storefront, bookings calendar/list, messages, enquiries, payouts, reminders, ownership and Free/Pro permissions |
| Bookings | One canonical booking record, occurrence/capacity checks, guest and logged-in parent flows, consent Form #7 mapping and version snapshots, child/bump selection, reservation vs payment status, leader confirmation/enrolment/cancellation, Stripe webhook idempotency, booking legal terms |
| Membership | Family and Leader Free/Pro tiers, annual plans, tier comparison, registration/login, membership entitlements, renewal/expiry handling |
| Support & Community | Public/private moderated Ask a Question Form #4, family support Form #5, resource library, leader opt-in replies, verified routing, safe community participation |
| Events & Expo | Public events and calendar, event detail templates, visitor tickets, timed entry, QR check-in, exhibitor/sponsor/volunteer flows, raffle/legal review and capacity |
| Communications | FluentSMTP verified delivery, transactional vs marketing consent, email templates, notifications/reminders, newsletter subscription/unsubscribe, delivery history |
| Platform & Team | WP admin Developments single roadmap, team roles, data quality/claim tools, legacy URL redirects, data backups, privacy, security, accessibility and staging-to-live rollback |

**No new duplicate admin trackers:** retain existing saved `bh3_development_statuses` and `bhs_development_readiness` options; unify their presentation before retiring any legacy tracker screen. Preserve existing historical status values.

**Naming:** new identifiers use `bhs` where applicable; do not rename existing `bh_`, `bh3_`, shortcode, class, or table identifiers as part of roadmap consolidation.

## Final scope-completeness register — avoid later feature surprises

The following are **explicit acceptance requirements or deferred enhancements**, not commitments to build all of them before launch. Where functionality already exists, verify and reuse it; do not create competing modules.

| Requirement | Scope / acceptance test | Target |
| --- | --- | --- |
| Family onboarding | A new user can set location, interests, preferred days and optional child/bump profiles; skip nonessential questions | Core |
| Facilities and accessibility | Structured, searchable buggy access, toilets, changing, feeding, parking and sensory/access needs; unknown values shown as unknown | Core data contract; filters staged |
| Multi-child matching | Show activities fitting one or more selected children with clear age suitability and no public disclosure of child data | Enhancement |
| Cancellation and reschedule alerts | Leaders can cancel an occurrence, affected bookers get appropriate transactional notices and an audit trail; no false confirmed bookings | Core safety |
| Waiting list | Opt-in queue, capacity-safe offer/expiry and explicit parent confirmation | Enhancement |
| Leader leave and holiday exceptions | Skip or pause recurring sessions without deleting history or modifying unrelated dates | Core timetable |
| Listing corrections | Families can flag stale time, price, venue or closed activities for moderation; no public auto-edits | Core data quality |
| Recently viewed | Private, opt-in/local-only history with clear control to clear it | Enhancement |
| Collections and sharing | Save custom activity lists; sharing requires deliberate action and never exposes private family profiles | Enhancement |
| Seasonal campaigns | Curated half-term, summer, Halloween, Christmas and rainy-day collections with verified dates | Enhancement |
| Notification controls | Separate essential booking notices from optional reminders and marketing; unsubscribe and consent records respected | Core |
| Leader onboarding and verification | Claim/verify email, organisation, listings, payout preferences and consent to terms; clear progress steps | Core |
| Admin operational overview | Pending claims, failed notifications, booking exceptions, stale listings and audit trails without exposing unnecessary family data | Core |
| Retention and safeguarding | Document retention/deletion/export, moderator access, consent withdrawal, child data minimisation and private communications | Essential |
| Failure and recovery journeys | Clear empty states, broken-link handling, duplicate booking protection, email failure retry, backups and rollback | Essential |
| Data provenance | Store last independently verified listing evidence/date, owner and update source; flag unconfirmed rather than guessing | Core |
| Analytics and success criteria | Track search-to-booking and leader time saved using privacy-aware aggregate reporting; no new paid analytics requirement | Enhancement |

### Scope freeze discipline

- **Core / Essential:** required for safe, reliable launch, subject to existing implementation and staged QA.
- **Enhancement:** explicitly recorded but not a launch blocker unless a safety dependency makes it essential.
- **Future:** revisit only after core journeys are reliable.
- Do not add new phases for individual ideas. Add them under an existing sector with a measurable acceptance criterion and dependency.
- After this inventory, new suggestions should normally replace or simplify an existing feature, not expand the launch scope. Exceptions: demonstrated legal, safeguarding, security, accessibility or data-integrity gaps.
- Any future addition must pass: *Does it save time, make family life easier, or help people connect?*

## Legacy feature parity watchlist

Explicitly compare the old `bubbaplugin` implementations before designing replacements:
- `api/leader-social.php`, `api/leader-boosts.php`, `api/leader-articles.php`, `api/leader-faqs.php`
- `api/planner-pro.php`, `planner-pro.html`
- `api/newsletter.php`, `cron/newsletter.php`, `api/admin-email-centre.php`, `account/notifications.html`
- `api/organiser-terms.php`, admin import/export, organiser claims, listing verification and data export
- Expo visitor tickets, QR check-in, volunteers, sponsors, exhibitors and raffle tools

**Parity rule:** If a legacy feature is essential for launch, keep it in the 4.0 core parity backlog; later enhancements must not replace the migration requirement.

## Candidate acceptance criteria

- 4.1: admins can identify stale or incomplete listings and review duplicates without automatic destructive merges.
- 4.2: leaders can edit multiple session dates quickly; cancellation/waitlist messages are consent-aware and avoid double-booking.
- 4.3: personalised recommendations respect family-profile privacy; reminders are opt-in and dates are configurable.
- 4.4: leader analytics use appropriate permissions; boosts are clearly labelled and don't compromise relevance.
- 4.5: email logs avoid exposing private family details; users can control marketing preferences separately from transactional messages.
- 4.6: ticket allocation and QR check-in prevent duplicate entry; event-specific roles are scoped.
- 4.7: audit logs and exports are access-controlled, retained appropriately and support safe migration.
- 4.8: rainy-day and weekend suggestions use verified indoor tags and actual session dates; no invented weather or availability; free and low-cost filters are accurate; suggestions are useful without AI.

## Priority / sequencing

1. Finish and test the 4.0 core launch foundation first (especially booking, permissions, consent, directory and leader workflows).
2. Start 4.1 and 4.2 when the core is stable; these have the strongest reliability and leader time-saving benefits.
3. Develop 4.3–4.5 incrementally after the underlying services are proven.
4. Align 4.6 with the Events templates roadmap; leave optional monetisation and advanced analytics until later.

## Decision register (to confirm later)

- Whether listing boosts are part of Leader Pro or a separate paid option.
- Which legacy Planner Pro features should remain free versus premium.
- Waiting-list release rules, expiry and consent wording.
- Notification channels and opt-in defaults.
- Events feature split between the initial 4.0 core templates and later 4.0 enhancements.
- Whether public leader analytics should include views, enquiries, bookings or all three.

## Status

**Unified roadmap captured.** Core functionality is in mixed states of implementation and QA; enhancements are proposals. This document does not authorise schema changes or deployment.
