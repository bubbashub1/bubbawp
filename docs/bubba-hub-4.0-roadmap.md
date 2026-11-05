# Bubba Hub 4.0 — Future Development Roadmap

Status: **Planned / backlog only**  
Created: 2026-11-05  
Owner: Bubba Hub South West

## Product vision and decision principles

**Vision:** A family-first community platform that helps families discover, connect, plan and be inspired, while enabling activity leaders to manage their services with less administration and without dependence on AI.

**Three product pillars:**
1. **Less AI dependence, more dependable automation:** deterministic workflows, structured WordPress data, reusable templates, clear user controls and optional rather than required AI. Essential browsing, booking, notifications and publishing must work without AI services.
2. **Less administration for leaders:** one mobile-friendly portal for listings, schedules, venues, bookings, messages, reminders and payments; minimise duplicate entry, unnecessary clicks and manual reconciliation.
3. **A useful, connected and inspiring family portal:** personal profiles, discovery, favourites, planning, support, community connections and relevant ideas for family time, with privacy and safeguarding built in.

**Feature prioritisation test:** Does this feature (a) save time, (b) make family life easier, or (c) help families and local leaders connect? Prefer features that measurably achieve at least one. Avoid adding complexity or recurring service costs without a clear benefit.

**Outcome measures to define before delivery:** leader minutes per routine task, percentage of bookings/reminders handled without manual intervention, parent repeat usage, activity discovery-to-booking completion, data freshness, and support/connection engagement. Do not collect unnecessary child or family data.

## Scope and release boundary

Bubba Hub **3.0** remains the WordPress migration, legacy feature parity, security, staging QA, and reliable core Directory, Family Hub, Leader Portal and Booking system. Do not defer critical legacy parity or safety fixes to 4.0 simply because they are unfinished.

Bubba Hub **4.0** is a separate future-development roadmap for enhancements, automation, time-saving workflows, family experience and sustainable revenue. This document is planning only: no feature here is implemented or approved for immediate deployment by virtue of inclusion.

Constraints: prefer existing WordPress infrastructure, no AI or additional paid services at this stage; preserve established data tables and access boundaries; prioritise low admin overhead, mobile usability and privacy.

## Proposed phases

| Phase | Workstream | Candidate features | Dependencies |
| --- | --- | --- | --- |
| 4.1 | Data Quality & Automation | Listing health dashboard, missing fields/schedules, broken booking links, organiser re-verification, duplicate activity/venue/organiser detection, safe merge preview, stale listing flags | 3.0 directory and data integrity |
| 4.2 | Smart Booking & Timetables | Waiting lists, space-available notifications, session cancellation/rescheduling, quick bulk timetable editor, recurring schedule patterns, parent booking history improvements | 3.0 booking consent, enrolment, session and notification QA |
| 4.3 | Family Hub Plus | Planner Pro parity review, age-appropriate recommendations, school application tracker, pregnancy reminders, enhanced personal calendar and booking history | 3.0 family profiles, planner and privacy controls |
| 4.4 | Leader Business Tools | Performance dashboard, leader onboarding checklist, social tools parity review, optional listing boosts, leader articles/FAQs and entitlement rules | 3.0 leader permissions, storefront, memberships |
| 4.5 | Communications Centre | Delivery history, notification preferences, message templates, newsletter roundups, opt-in and unsubscribe controls, automated operational reminders | 3.0 FluentSMTP, consent and verified recipient handling |
| 4.6 | Events & Expo Plus | Recurring events, ticketing enhancements, QR check-in, volunteer tools, exhibitors, sponsor packages, raffle/reporting review | 3.0 Events sector templates and shared booking foundation |
| 4.7 | Platform & Administration | Audit trails, advanced exports, team roles, accessibility/SEN metadata and filters, admin reporting, safe legacy redirect monitoring | 3.0 launch and migration controls |

## Legacy feature parity watchlist

Explicitly compare the old `bubbaplugin` implementations before designing replacements:
- `api/leader-social.php`, `api/leader-boosts.php`, `api/leader-articles.php`, `api/leader-faqs.php`
- `api/planner-pro.php`, `planner-pro.html`
- `api/newsletter.php`, `cron/newsletter.php`, `api/admin-email-centre.php`, `account/notifications.html`
- `api/organiser-terms.php`, admin import/export, organiser claims, listing verification and data export
- Expo visitor tickets, QR check-in, volunteers, sponsors, exhibitors and raffle tools

**Parity rule:** If a legacy feature is essential for the 3.0 launch, keep it in the 3.0 parity backlog; 4.0 may extend it but must not replace the migration requirement.

## Candidate acceptance criteria

- 4.1: admins can identify stale or incomplete listings and review duplicates without automatic destructive merges.
- 4.2: leaders can edit multiple session dates quickly; cancellation/waitlist messages are consent-aware and avoid double-booking.
- 4.3: personalised recommendations respect family-profile privacy; reminders are opt-in and dates are configurable.
- 4.4: leader analytics use appropriate permissions; boosts are clearly labelled and don't compromise relevance.
- 4.5: email logs avoid exposing private family details; users can control marketing preferences separately from transactional messages.
- 4.6: ticket allocation and QR check-in prevent duplicate entry; event-specific roles are scoped.
- 4.7: audit logs and exports are access-controlled, retained appropriately and support safe migration.

## Priority / sequencing

1. Finish and test 3.0 first (especially booking, permissions, consent, directory and leader workflows).
2. Start 4.1 and 4.2 when the core is stable; these have the strongest reliability and leader time-saving benefits.
3. Develop 4.3–4.5 incrementally after the underlying services are proven.
4. Align 4.6 with the Events templates roadmap; leave optional monetisation and advanced analytics until later.

## Decision register (to confirm later)

- Whether listing boosts are part of Leader Pro or a separate paid option.
- Which legacy Planner Pro features should remain free versus premium.
- Waiting-list release rules, expiry and consent wording.
- Notification channels and opt-in defaults.
- Events feature split between the initial 3.0 templates and later 4.0 enhancements.
- Whether public leader analytics should include views, enquiries, bookings or all three.

## Status

**Captured for future development.** No feature PRs, schema changes or deployment work authorised by this roadmap.
