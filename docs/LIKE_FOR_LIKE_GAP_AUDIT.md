# Bubba Hub 3.0 — Like-for-like gap audit (baseline)

**Date:** 2026-11-03  
**Decision:** Parity-first. No further feature expansion until baseline is agreed.  
**Sources inspected:** GitHub `bubbashub1/bubbaplugin` main recursive file tree (577 entries); `bubbashub1/bubbawp` main recursive file tree (42 entries); 3.0 architecture, master checklist, theme QA and theme README.  
**Evidence level:** Source-path inventory and selected 3.0 source review only. A file's existence **does not prove the feature works**. No authenticated live/staging browser test, database schema inspection or API contract test has been performed.

## Status legend
- **Source exists**: relevant legacy code/files found, runtime unverified.
- **Design only**: 3.0 template/pattern exists, but not backed by production data.
- **Missing**: no corresponding functional 3.0 module found in the repository.
- **Needs runtime audit**: real functionality, data and user journey must be checked read-only.

## Parity matrix

| ID | Area | Legacy source evidence | 3.0 status | Gap / required acceptance |
| --- | --- | --- | --- | --- |
| P-01 | Brand, header, footer, homepage | `index.html`, `assets/css/`, existing WordPress Astra/Elementor | Design only: block templates, patterns, CSS | Confirm approved logo/colours and match key desktop/mobile journeys; test Site Editor |
| P-02 | Activities directory, filters and sorting | `directory.html`, `api/activities.php`, directory CSS | Missing dynamic directory; visual placeholder only | Search/filter parity including age, category, day, price/free, term time, region/town, session, sort |
| P-03 | List, map and calendar | `directory.html`, `map.html`, `calendar.html` | Missing | Shared results, markers, calendar sessions, print, responsive layout |
| P-04 | Postcode, Near Me and geolocation | `directory.html`, `api/geocode.php` | Missing | Permission handling, radius, postcode validation and location privacy |
| P-05 | Activity detail, gallery, timetable and actions | `activity.html`, `assets/css/activity-v2.css` | Design-only `activity-preview` | Real data, up to 10 images, price/age/venue, map then timetable, directions, links, booking destination, save/planner/visited/compare, claim/report |
| P-06 | Organisers, businesses and venues | `organiser.html`, `venues.html`, `venue.html`, organiser/admin APIs | Missing | Search, linking, categories, location, profile and duplicate handling |
| P-07 | Family sign-in, profiles and My Hub | `auth.html`, `my-hub.html`, `account/`, `api/auth.php`, `api/my-hub.php` | Missing | Preserve sessions, roles, privacy, saved activities, child profiles, preferences |
| P-08 | Planner and Planner Pro | `planner.html`, `planner-pro.html`, `api/planner.php`, `api/planner-pro.php` | Missing | Save, calendar, print/export, conflicts and entitlement checks |
| P-09 | Leader dashboard, classes, venues, schedules | `leader/classes.html`, `leader/venues.html`, `leader/schedule.html`, `api/leader-portal.php` | Missing | Free/Pro access, list/search/edit, close/unpublish, venue lookup, schedule CRUD |
| P-10 | Listing wizard, imports and approvals | `admin/admin-activities-add-listing.html`, `api/leader-listing-import.php`, `api/admin-approval-centre.php` | Missing | Existing records editable; review/save/import without duplicates or silent data loss |
| P-11 | Claims and email invitations | `api/claim-listing.php`, `api/admin-claim-invitations.php`, `api/claim-invitations-cron.php` | Missing | Invite status, duplicate suppression, approval, email delivery and audit |
| P-12 | Admin dashboards, settings and location tools | `admin/` (51 entries), `api/admin-activities.php`, `api/admin-location-manage.php` | Missing | Admin permissions, bulk actions, venue merge, reporting, settings and audit |
| P-13 | Events, expo, tickets, QR, exhibitors, raffle, sponsors | `events.html`, `expo.html`, `admin/expo-*.html`, `api/expo-*.php` | Missing | Event discovery, visitor registration, QR/check-in, exhibitors, sponsors and existing legal/payment controls |
| P-14 | Bookings and reservations | `booking.html`, `booking-manager.html`, `api/reservations.php` | Missing | Audit current behavior before deciding what is existing parity vs future booking roadmap |
| P-15 | Volunteers, support, newsletter and communications | `volunteer.html`, `team-hub/`, `support/`, `api/newsletter.php`, `api/mailer.php` | Missing | Role-based access, support flows, subscriptions, email and opt-in/opt-out |
| P-16 | Legacy URLs, redirects and SEO | `.htaccess`, `activity.html`, `directory.html`, `sitemap.html` | Missing compatibility layer | Test old `?id=`/`?slug=` links and intended `/activity/<slug>`; avoid 404 and duplicate canonical URLs |
| P-17 | Data integrity and API compatibility | `api/db.php`, `database/`, `migrations/`, `wp-content/mu-plugins/` | Missing adapters | Read-only inventory of `bh_` tables, IDs, joins, user mapping, roles and endpoints; no schema/data writes |
| P-18 | Deployment, safety and rollback | Legacy deploy workflows; 3.0 theme ZIP workflow | Partial: static checks and ZIP, no WP activation evidence | Isolated install, backup, rollback, performance, security and browser QA |

## Priority and sequence

### Gate A — inventory and contracts (must finish before feature coding)
- [x] Compare repository-level source trees and identify major functional gaps.
- [ ] Inspect legacy entry points and key PHP APIs for actual data contracts and dependencies.
- [ ] Read-only audit of existing WordPress pages, active plugins/MU plugins, menus and roles.
- [ ] Read-only schema inventory of actual `bh_` tables and relationships.
- [ ] Record current URLs, screenshots and acceptance journeys on live/staging without modifying data.
- [ ] Categorise each feature as **must match at launch**, **safe to improve**, or **future feature**.
- [ ] Approve a complete parity matrix with user.

### Gate B — theme and integration foundation
- [ ] Verify theme ZIP install/activation and editor/mobile QA on isolated WordPress.
- [ ] Confirm legacy content and Elementor transition without dropping content.
- [ ] Create independently installable 3.0 plugin and read-only legacy data/API adapters.
- [ ] Define dynamic block contracts, route aliases, permissions and fallback states.
- [ ] Ensure theme remains usable if plugin disabled; no `bh_` mutations on activation.

### Gate C — like-for-like user journeys
- [ ] Family: find activity → filter/map/calendar → activity → save/planner → account.
- [ ] Leader: sign in → claim/list/edit → update venue/timetable → publish/unpublish.
- [ ] Admin: manage listings/venues/users → approvals/invitations → email/audit.
- [ ] Event visitor: discover event → register → receive ticket → QR check-in.
- [ ] Verify mobile, keyboard, errors, role separation, performance and data parity.

### Gate D — release readiness
- [ ] Automated tests + manual staging evidence for every launch-critical journey.
- [ ] Full record count and relationship reconciliation; no missing records.
- [ ] URL redirects, search indexing, analytics and emails verified.
- [ ] Document backups, rollback, cutover and explicit approval before any live changes.

## Explicit decisions
1. **Parity before enhancements:** the current production experience is the functional reference, not the 3.0 placeholder patterns.
2. **Do not rebuild blindly:** reuse or adapt existing code and data contracts where safe; audit first.
3. **No false completion:** code-path presence is not proof of runtime functionality.
4. **No live deployment or data migration without explicit approval.**
5. **Keep the new block theme presentation-only; functionality belongs in the separate plugin.**

## Immediate next audit
Inspect `api/activities.php`, `api/auth.php`, `api/db.php`, `api/planner.php`, `api/leader-portal.php`, `api/reservations.php` and the legacy route rules. Produce a contract map (inputs, outputs, auth, data tables, route aliases), without copying secrets or making live writes.
