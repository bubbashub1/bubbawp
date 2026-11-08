# Bubba Hub — Master Work Checklist

> **Single source of truth for project scope and progress.**
> Repository: `bubbashub1/bubbawp` · Product: **Bubba Hub Manager 3.0**
> Status: **EXISTING IMPLEMENTATION / STAGING PARITY IN PROGRESS** · Last reviewed: 2026-11-08

## How to use this file

- `[ ]` Not started · `[x]` Completed **and verified** · `[~]` In progress (replace with `[ ]` or `[x]` before closing a stage).
- A task is only complete when code, tests, documentation and any required staging checks are finished.
- Work on **one active stage** at a time. Add unrelated ideas to **Parking Lot**, not the current stage.
- Every change must reference a checklist ID (e.g. `F-03`) in its PR or commit.
- Update **Current Focus**, **Decisions**, **Blockers** and **Change Log** after meaningful work.
- A stage advances only when its **exit gate** passes. Never claim deployment or QA without evidence.
- **Scope changes:** propose → record in Parking Lot → evaluate cost/dependencies → explicitly approve → schedule.

## Current focus (2026-11-08)

- **Active workstream:** Whole-plugin migration from Fluent Forms to Ninja Forms, with BubbaWP remaining the system of record.
- **Main repository:** `bubbashub1/bubbawp`. `bubba4` is being retired only after comparison; `bubbaplugin` is legacy reference.
- **Theme:** Preserve existing `bubbatheme`; do not rebuild theme from scratch.
- **Latest verified GitHub milestone:** Events Manager Free/Ninja Forms read-only diagnostics merged in [PR #198](https://github.com/bubbashub1/bubbawp/pull/198), commit `4255bd0`. PHP lint passed before merge.
- **Staging QA:** Booking diagnostics screen and Ninja Forms booking adapter are not yet verified end-to-end. No claim of a working booking/Stripe integration.
- **Next task:** NF-MIG-01, finish a read-only audit of every active form workflow and confirm Ninja Forms forms, field keys/types, installed version and submission payloads on staging. Booking Form ID 2 and its visible labels are confirmed; internal keys, payload and dynamic attendee options remain unverified. BubbaWP remains authoritative; Ninja Forms is the form interface. Events Manager is excluded.
- **Preservation:** Keep all existing submissions, workflow records, Stripe safeguards, family profiles, sessions, leader dashboards, SQL tables and data. Replace active form integrations only after workflow-specific staging parity; no historical data deletion.
- **Production changes:** None authorised by this checklist update.

### Current booking integration gates

- [x] **B-NF-00** Confirm architecture: BubbaWP owns booking records and lifecycle; Ninja Forms is the family-facing form; Events Manager is excluded from the booking path.
- [ ] **B-NF-01** Confirm the active Ninja Forms booking form ID, field keys/types and submission payload on staging; inspect installed Ninja Forms version and supported hooks.
- [ ] **B-NF-02** Build the Ninja Forms booking adapter in BubbaWP behind a staging-only feature flag, disabled by default; isolate legacy booking verification for rollback/history only.
- [ ] **B-NF-03** Reuse BubbaWP's existing session validation, capacity reservation, duplicate protection, native child-profile ownership checks and consent writer; do not duplicate booking logic or trust browser-supplied profile IDs.
- [ ] **B-NF-04** Verify the adapter can safely associate the Ninja Forms submission with wp_bh3_bookings without assuming Fluent Forms storage or changing the existing unique submission field semantics.
- [ ] **B-NF-05** Validate free bookings, capacity/concurrency, duplicate submissions, recurring dates, confirmation, cancellations and expiry behaviour.
- [ ] **B-NF-06** Validate the existing Stripe test checkout path, one payment, fee disclosure, leader price, webhook replay, refunds and payout accounting; keep live payments disabled.
- [ ] **B-NF-07** Connect canonical booking records to Leader Portal and My Hub with correct permissions and privacy.
- [ ] **B-NF-08** Run member/guest/leader end-to-end staging QA, backup/rollback and obtain explicit approval before retiring the legacy booking integration.

**Architecture rule:** Events Manager is not a booking dependency and must not create duplicate activity, session or reservation records.

### Implementation status audit (not staging acceptance)

| Workstream | Existing code/work | Outstanding acceptance |
| --- | --- | --- |
| Plugin foundation | Existing modular BubbaWP bootstrap, modules and CI | Full staging parity and activation checks |
| Theme | Existing block-theme and BubbaTheme work | Editor/mobile visual QA |
| Directory and listings | Existing custom activity/venue/organiser modules | Search, permalink, schedule and mobile QA |
| Leader Portal | Existing front-end/dashboard code | Role, save and booking integration QA |
| Family Hub | Existing native child/bump, favourites, planner code | Ownership, privacy and end-to-end QA |
| Booking | Existing booking lifecycle, checkout and Stripe safeguards | Ninja Forms Form 2 adapter, free-booking support, provider-aware provenance and payment parity |
| Events/Expo | Existing earlier work to inventory | Ticket/QR and event integration QA |
| Support | Existing support modules and native Ask Support storage | Ninja Forms adapter, enquiry inbox, moderation, privacy and audit QA |

**Rule:** Existing code does not mean a completed feature. Do not check off historic stages without source, CI and staging evidence. Avoid duplicate rewrites.

## Non-negotiable rules

- [ ] **R-01** Never modify the existing live website during planning or development without explicit approval.
- [ ] **R-02** Preserve all existing `bh_` tables, columns, keys, IDs, relationships and data; no schema alteration without explicit approval.
- [ ] **R-03** Preserve existing PHP APIs, account access, URLs and behaviour via compatibility adapters until replacements are tested and approved.
- [ ] **R-04** Build **our own installable WordPress plugin**. GeoDirectory, Directorist, Amelia, LatePoint, Bookwhen and ClassForKids are **feature inspiration only**, not dependencies; do not copy proprietary code or designs.
- [ ] **R-05** No AI APIs, paid plugins, new paid services or recurring service costs in this development phase.
- [ ] **R-06** Prefer existing hosting, WordPress core and free/open-source tools after licence and limits review. **New theme must not depend on Astra, Elementor or paid builders**; existing Elementor content remains supported during legacy migration where necessary.
- [ ] **R-07** Use staging/test fixtures first; no live data writes from the new plugin.
- [ ] **R-08** Privacy and child safety by design: minimal data, parent-controlled child profiles, private memories, access controls and retention policies.
- [ ] **R-09** Mobile-first for families, leaders and administrators.
- [ ] **R-10** All production releases require tests, backup/rollback plan, approval and recorded evidence.

## Stage T0 — Theme first: design and layout baseline

**Approved sequence:** Theme first → governance/legacy audit → plugin foundation → feature modules. Theme work may use placeholder blocks and sample content, but must not touch live or connect to existing production data. The theme design must be approved before feature-specific layouts are built.

- [x] **T0-00** Approve theme-first development order.
- [ ] **T0-01** Create an installable native WordPress block theme with theme.json, global tokens, header/footer, homepage and core templates. **In progress:** theme files and templates committed; installability not yet verified.
- [ ] **T0-02** Review desktop/mobile look, brand colours, typography, navigation, card system and page widths; refine to approval. **Draft brief:** `docs/THEME_DESIGN_BRIEF.md`; awaiting visual review.
- [x] **T0-03** Build reusable Site Editor patterns for hero, discovery placeholder, categories, featured cards, family and leader CTAs. **Verified in source:** patterns committed; actual Site Editor rendering remains in T0-04.
- [ ] **T0-04** Verify editor customisation, responsive behaviour, accessibility and graceful placeholder behaviour without plugin.
- [ ] **T0-05** Produce independent theme ZIP and test activation on isolated/staging WordPress; document evidence. **Partially verified:** GitHub Actions run 41639189965 passed and uploaded `bubba-hub-theme` artifact; WordPress activation not tested.
- [ ] **T0-06** Approve design baseline and component contracts for Directory, Leader Portal and My Hub.
- **Exit gate:** visual direction approved, install/activation and mobile QA evidenced; then proceed with Stage 0 inventory and plugin work.

## Stage 0 — Governance, inventory and design

- [x] **G-01** Create this master checklist in the new repository.
- [ ] **G-02** Define repository layout, branch/PR conventions, release versioning and CI.
- [ ] **G-03** Document full website sitemap, modules, ownership and user journeys.
- [ ] **G-04** Audit existing `bubbaplugin` source, open PRs, MU plugins, PHP APIs and WordPress screens.
- [ ] **G-05** Obtain **read-only** staging inventory of installed plugins, pages, posts, Elementor templates, widgets and registered admin menus.
- [ ] **G-06** Inventory actual existing database tables and relationships read-only, including any separate WP/application databases.
- [ ] **G-07** Create legacy-to-3.0 compatibility map: endpoints, auth, roles, IDs, permalinks and dependencies.
- [ ] **G-08** Create risk register, privacy/security requirements, rollback approach and test fixtures.
- [ ] **G-09** Write architecture decision records for directory, booking, payments and My Hub.
- [ ] **G-10** Approve MVP boundaries and deferred features.
- [x] **G-11** Select architecture: fully custom native WordPress block theme, separately installable from the Bubba Hub Manager plugin.
- [ ] **G-12** Specify block theme design system, template coverage, editor patterns and legacy Elementor transition.
- **Exit gate:** architecture, schema constraints, migration strategy and test plan reviewed; no unknown critical data dependency.

## Stage 1 — Installable plugin and theme foundations

- [ ] **F-01** Create WordPress plugin bootstrap with version, activation/deactivation and safe uninstall policy.
- [ ] **F-02** Create modular architecture and feature flags; disabled modules must not damage data.
- [ ] **F-03** Add admin dashboard, settings, capabilities and audit logging.
- [ ] **F-04** Add read-only data repositories and adapters to existing `bh_` tables/PHP APIs.
- [ ] **F-05** Add REST routes with authentication, permissions, validation and CSRF protections.
- [ ] **F-06** Add native WordPress dynamic blocks (server-rendered where appropriate), block patterns and a shared design-token contract; Elementor compatibility is a migration adapter only.
- [ ] **F-07** Add PHPUnit/static checks, security checks and reproducible plugin ZIP build.
- [ ] **F-08** Install on isolated/staging WordPress; verify activation, deactivation and no database mutations.
- [ ] **T-01** Create separate installable theme at `theme/bubba-hub/` with `style.css`, `theme.json`, `templates/`, `parts/`, `patterns/` and theme bootstrap as needed.
- [ ] **T-02** Implement native Site Editor global styles: colours, typography, spacing, buttons and responsive layouts without paid dependencies.
- [ ] **T-03** Implement header, footer, navigation, homepage, page, archive, search, 404 and relevant single templates.
- [ ] **T-04** Provide theme patterns for homepage hero, category navigation, directory entry, leader CTA, family CTA and event promotions.
- [ ] **T-05** Integrate dynamic directory/booking/family blocks from plugin; theme remains presentation-only and usable when plugin disabled.
- [ ] **T-06** Create independent plugin and theme ZIP packaging with versioned release artefacts.
- [ ] **T-07** Test Site Editor, mobile, accessibility, plugin disabled fallback, SEO/permalinks and legacy Elementor content rendering on staging.
- **Exit gate:** clean independent theme/plugin install/disable, Site Editor functionality, passing CI, documented adapters, no live changes.

## Stage 2 — Directory and discovery

- [ ] **D-01** Activity, organiser/business and venue profiles from existing records.
- [ ] **D-02** Unified search selector: Activities, Events, Businesses/Organisers.
- [ ] **D-03** List/map/calendar views with shared filters and mobile layouts.
- [ ] **D-04** Postcode, Near Me, distance/radius search with free/approved location solution.
- [ ] **D-05** Filters: age, category, date, time, price/free, accessibility, term time and availability.
- [ ] **D-06** Venue/organiser linking, duplicate prevention and location management.
- [ ] **D-07** Native Gutenberg dynamic blocks: search, cards, map, timetable and organiser profile; optional legacy Elementor compatibility adapter.
- [ ] **D-08** SEO-friendly activity URLs, redirects and compatibility tests.
- [ ] **D-09** Featured placements clearly marked as sponsored (monetisation later).
- **Exit gate:** search/filter/map consistency, mobile QA, URL parity and no duplicated master records.

## Stage 3 — Leader Portal, listings and timetables

- [ ] **L-01** Modern leader dashboard and verified Free/Pro permissions.
- [ ] **L-02** My Listings: search, add, edit, claim, close/unpublish and restore where supported.
- [ ] **L-03** My Venues: existing venue lookup, map, location assignment and removal.
- [ ] **L-04** Timetable editor: recurring sessions, dates, exceptions, end times, capacities and venue.
- [ ] **L-05** Importer: URL/manual timetable workflows, supported-site evidence and review-before-save.
- [ ] **L-06** Contact details, organiser profile and class branding.
- [ ] **L-07** Admin approval, claim invitation status, audit trail and duplicate protection.
- [ ] **L-08** Staging QA for roles, saves, permissions and legacy compatibility.
- **Exit gate:** leader can maintain listings and schedules without GitHub or database editing.

## Stage 4 — My Hub essentials (no AI)

- [ ] **M-01** Family dashboard and private child profiles/preferences.
- [ ] **M-02** **Inspire Me** rule-based matching (age, location, date, budget, interests).
- [ ] **M-03** **Sibling Match** across multiple children's age ranges.
- [ ] **M-04** Family Planner: saved, visited, scheduled and calendar export.
- [ ] **M-05** **What Can We Do Today?** using verified schedules/hours.
- [ ] **M-06** Rainy Day Rescue: curated offline/at-home ideas.
- [ ] **M-07** Family privacy controls, account access and data deletion/export pathways.
- **Exit gate:** family journey works with existing data, no AI cost, child data access tested.

## Stage 5 — Bubba Hub bookings (BubbaWP authority + Ninja Forms interface; retain existing checkout until parity)

- [ ] **B-01** Design booking data model against existing tables; propose any **new** tables separately for approval, never alter existing tables implicitly.
- [ ] **B-02** Single-session free/paid reservation flow.
- [ ] **B-03** Full-course/term free/paid enrolment flow.
- [ ] **B-04** Capacity, sibling places, overbooking prevention and concurrency tests.
- [ ] **B-05** Automatic confirmation OR leader approval per listing/course.
- [ ] **B-06** Booking states: reserved, pending approval/payment, enrolled, waitlisted, cancelled, attended/no-show as appropriate.
- [ ] **B-07** Leader booking table: first name, surname initial, child name/age, email, phone subject to privacy permissions.
- [ ] **B-08** Registers, waitlists, cancellations and reminders via existing mail infrastructure.
- [ ] **B-09** My Hub booking history and upcoming classes.
- [ ] **B-10** External booking link fallback; no commission on bookings outside Bubba Hub.
- **Exit gate:** free bookings work end-to-end on staging; paid paths remain disabled until payment compliance/integration tests pass.

## Stage 6 — My Hub experiences

- [ ] **X-01** **Little Explorers Passport** with private stamps and non-competitive achievements.
- [ ] **X-02** Printable certificates and local adventure challenges.
- [ ] **X-03** **Our Little Memories**: private notes/photos, consent, retention and storage caps.
- [ ] **X-04** Holiday planner and family activity budget.
- [ ] **X-05** Optional weekly family digest with consent/unsubscribe.
- [ ] **X-06** Future candidate: family trails, local perks, birthday planning and private Family Circle.
- **Exit gate:** privacy, accessibility, storage and parental-control QA passed.

## Stage 7 — Revenue and payments (design now; paid processing later)

- [ ] **P-01** Implement and validate **one disclosed parent-paid Bubba Hub booking fee** that covers platform and estimated processing costs; leader receives the advertised class price. Actual fees, refunds and Stripe payouts require staging reconciliation.
- [ ] **P-02** Pricing calculator: target leader net → suggested advertised class price, with clearly estimated processing costs.
- [ ] **P-03** Payment architecture and legal/compliance review; evaluate Stripe Connect without activating costs in development.
- [ ] **P-04** Commission ledger, payout/refund/dispute handling and reconciliation design.
- [ ] **P-05** Free bookings incur zero Bubba Hub commission; external bookings are not commissionable.
- [ ] **P-06** Leader Pro subscription feature gating; price and launch terms **not yet decided**.
- [ ] **P-07** Featured listings, sponsors and promotional reporting.
- [ ] **P-08** End-to-end payment QA and explicit approval before any live transactions.
- **Exit gate:** calculations, reconciliation, fees, permissions and legal responsibilities verified; no unapproved charges.

## Stage 8 — Events, communications and administration

- [ ] **E-01** Expo visitor tickets, timed entry, QR check-in and capacity.
- [ ] **E-02** Exhibitors, sponsors, floor plan and event reporting.
- [ ] **E-03** Claim emails, notification templates, reminders and deliverability tools.
- [ ] **E-04** Volunteers, team hub, support/guidance and newsletters.
- [ ] **E-05** Admin activity/venue/organiser management, bulk actions and data quality.
- [ ] **E-06** Analytics, exports, audit logs, backup/restore procedures.
- [ ] **E-07** Review raffle/donation legality before enabling any relevant paid feature.
- **Exit gate:** admin and event parity, role-based QA and no regression in existing services.

## Stage 9 — Consolidation and launch

- [ ] **Z-01** Test install/upgrade paths and legacy coexistence.
- [ ] **Z-02** Test performance, accessibility, mobile, security and privacy.
- [ ] **Z-03** Test critical journeys: discovery → listing → booking → family/leader dashboards.
- [ ] **Z-04** Verify data parity, links, authentication, emails, schedules and reports.
- [ ] **Z-05** Backup and rehearse rollback; document deployment runbook.
- [ ] **Z-06** Obtain explicit live-release approval; deploy gradually with monitoring.
- [ ] **Z-07** Retire legacy interfaces only after verified feature parity and approval.
- **Exit gate:** launch acceptance signed off and rollback demonstrably works.

## Parking lot — not approved for current-stage development

- Native mobile apps and push notifications beyond existing infrastructure
- AI recommendations, AI OCR or any paid API
- Paid third-party booking/directory plugin integrations
- Instalments, proration, make-up classes and complex course transfers
- Family Circle social/sharing features
- Rewards with monetary value or public child leaderboards
- Additional commerce beyond agreed booking/Expo/gifts roadmap
- Any other idea raised mid-sprint: **record here before implementation**

## Decisions register

| ID | Decision | Status |
| --- | --- | --- |
| ADR-001 | One **Bubba Hub-owned installable WordPress plugin** with modular components | Agreed |
| ADR-002 | Existing `bh_` schema/IDs/relationships and PHP APIs preserved | Agreed |
| ADR-003 | Custom activity directory remains native; Events Manager Free was previously considered as a booking dependency | Superseded 2026-11-08 |
| ADR-004 | No AI and no new paid dependencies in current development | Agreed |
| ADR-005 | Bookings support **individual sessions and full courses/terms** | Agreed |
| ADR-006 | Single parent-paid booking fee; leader receives advertised class price (exact fee calculation to be validated) | Superseded 2026-11-08 |
| ADR-007 | Show one clearly labelled Bubba Hub Booking Fee and full total before payment | Superseded 2026-11-08 |
| ADR-008 | Leader-facing take-home pricing calculator | Agreed |
| ADR-009 | My Hub includes Inspire Me, Planner, Sibling Match, Passport and Memories | Agreed |
| ADR-010 | Stripe Connect/payment service configuration | Research later; not selected/activated |
| ADR-011 | Pro subscription pricing and commission variations | Undecided |
| ADR-012 | Exact new tables needed for new features | Pending schema audit and approval |
| ADR-013 | **Approach A:** fully custom native WordPress **block theme**, no Astra/Elementor dependency; separate theme ZIP and plugin ZIP | Agreed 2026-11-03 |
| ADR-014 | WordPress Site Editor, `theme.json`, templates, parts and patterns for visual editing; plugin owns business logic and dynamic blocks | Agreed 2026-11-03 |
| ADR-015 | Legacy Astra/Elementor content must be inventoried and transitioned on staging; no live theme switch until approved | Agreed 2026-11-03 |
| ADR-016 | **Theme-first sequencing:** establish and approve design system before implementing directory, leader and family features | Agreed 2026-11-03 |

### Decisions updated 2026-11-08

| ID | Decision | Status |
| --- | --- | --- |
| ADR-017 | `bubbawp` is the single development repository; retire `bubba4` after preservation audit | Agreed |
| ADR-018 | Events Manager Free is the booking/reservation authority | Superseded 2026-11-08; excluded from booking architecture |
| ADR-019 | Ninja Forms Free is the family-facing booking form interface; BubbaWP remains authoritative for validation, reservations, attendees, consent and lifecycle | Superseded 2026-11-08; replaced by ADR-023 |
| ADR-020 | Existing native `bh_children` and `bh_bumps` profiles remain authoritative | Agreed |
| ADR-021 | Preserve legacy form submissions and existing checkout records until a tested replacement and approved cutover | Superseded 2026-11-08 by ADR-024 |
| ADR-022 | No paid plugin or new AI dependency; reuse existing free plugins and code | Agreed |
| ADR-023 | BubbaWP owns the canonical booking system; Ninja Forms is the form interface/transport only. No second booking database or Events Manager booking layer. | Agreed 2026-11-08 |\n| ADR-024 | Remove active Fluent Forms dependencies throughout BubbaWP and replace form workflows with Ninja Forms/native BubbaWP. Preserve historical submissions and records; do not delete data or deactivate plugins during migration. | Agreed 2026-11-08 |

## Work item template

Copy for each substantial issue/PR:

```md
### [ID] Task title
**Stage:** 
**Status:** Not started / In progress / Blocked / QA / Done
**Goal:** 
**In scope:** 
**Out of scope:** 
**Existing systems affected:** 
**Database impact:** None / Read-only / New schema proposal (approval required)
**Security/privacy impact:** 
**Acceptance criteria:**
- [ ] Functionality implemented
- [ ] Automated checks pass
- [ ] Staging/manual QA evidenced
- [ ] Legacy compatibility verified
- [ ] Docs and master checklist updated
**PR / commit:** 
**Evidence:** 
**Blockers / next action:** 
```

## Session handover template

```md
**Date:**
**Active stage / task ID:**
**Completed and verified:**
**PRs / commits:**
**Testing performed (and not performed):**
**Blockers:**
**Next single task:**
**Scope changes parked:**
```

## Change log

| 2026-11-08 | Reconciled current work against existing BubbaWP source and PR #198; updated booking, forms, fees and single-repository decisions without claiming staging QA | PR #198; `docs/EVENTS_MANAGER_NINJA_FORMS_MIGRATION.md` |
| 2026-11-08 | Confirmed BubbaWP + Ninja Forms booking architecture; removed Events Manager from booking path and defined B-NF adapter gates. No implementation or staging QA claimed. | ADR-023; docs/EVENTS_MANAGER_NINJA_FORMS_MIGRATION.md |\n| 2026-11-08 | Approved whole-plugin Fluent Forms retirement; inventoried Support, Ask a Question, enquiries, booking, admin diagnostics and tests. Historical records remain preserved. | ADR-024; docs/NINJA_FORMS_MIGRATION_INVENTORY.md |

| Date | Change | Evidence |
| --- | --- | --- |
| 2026-11-03 | Initial master checklist created; no plugin implementation or staging QA claimed | This file |
| 2026-11-03 | Approved custom block theme approach A; added G-11/G-12 and T-01–T-07, revised F-06/D-07 and decisions | `docs/ARCHITECTURE.md` |
| 2026-11-03 | Reordered roadmap to theme-first Stage T0; design approval precedes feature modules | ADR-016 |
| 2026-11-03 | Committed first native theme scaffold: style.css, theme.json, header/footer, index/front-page/page and three patterns; not yet tested | `theme/bubba-hub/` |
| 2026-11-03 | Added single/archive/search/404 templates, theme bootstrap, responsive/focus CSS and test notes; no staging QA yet | `theme/bubba-hub/README.md` |
| 2026-11-03 | Added GitHub Actions static checks and theme ZIP packaging, plus staging/browser QA checklist; initial run failed, subsequent run passed | `.github/workflows/theme-checks.yml`, `docs/THEME_QA.md` |
| 2026-11-03 | Verified successful GitHub Actions theme ZIP artifact (run 41639189965); drafted visual design brief; staging/browser QA still outstanding | `docs/THEME_DESIGN_BRIEF.md` |
| 2026-11-03 | Added category grid, featured placeholder, event promo and directory shell patterns; homepage updated; browser review still pending | `theme/bubba-hub/patterns/` |
| 2026-11-03 | Confirmed theme build passed run 41642060861; expanded static validation for pattern and template-part references, CI result pending | `scripts/validate-theme.php` |
