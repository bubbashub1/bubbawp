# Leader Portal parity audit — 4 November 2026

Reference: `bubbashub1/bubbaplugin` (standalone legacy) vs `bubbashub1/bubbawp` (WordPress staging).

## Rules
- BubbaWP owns data, permissions, forms and integrations; BubbaTheme owns CSS and layout.
- Preserve Organiser → Activity → Venue → Session relationships; do not copy legacy `bh_*` tables into WordPress.
- Existing staging-only shortcodes and feature flags are not production launch approval.
- Owner-scoped writes, nonce checks, sanitisation and permission checks must be reviewed before public launch.

## Feature inventory

| Legacy reference | WordPress status | Next action |
| --- | --- | --- |
| `leader.html` dashboard | `[bh_leader_dashboard]` exists | Verify totals, links and role behaviour |
| `leader/classes.html` | Draft listing editor and dashboard list exist | Compare create, edit, close/unpublish and search workflows |
| `leader/venues.html` | Frontend venue editor exists | Compare existing venue search, map lookup and deduplication |
| `leader/schedule.html` | Frontend timetable editor exists | Compare venue-linked recurring sessions and timezone formatting |
| `leader/bookings.html` | Separate booking workstream in progress; FEATURE_BOOKINGS false | Audit reservation lifecycle, consent, enrolment, reminders and ownership |
| `leader/profile.html` | `[bh_leader_business]` and storefront preview exist | Review fields, image uploads and directory publication gates |
| `leader/statistics.html` | Not established as parity-complete | Read-only owner-scoped reporting |
| `leader/payments.html` | Not established as parity-complete | Design entitlements and payment integration separately |
| `leader/faqs.html` | Not established as parity-complete | Review Free/Pro limits and moderation |
| `leader/blog.html` | Not established as parity-complete | Review publication and moderation |
| `leader/messages.html` | Not established as parity-complete | Define inbox permissions and notifications |
| `leader/specialisms.html` | Ask Support shortcodes appear on dashboard | Verify specialist onboarding and question routing |
| `leader/support.html` | Support link exists in sidebar | Confirm actual WordPress shortcode/page and help tabs |

## Highest priority
1. Verify the current shortcode and page inventory before adding more sidebar links.
2. Consolidate frontend listing workflows without adding duplicate organisers or venues.
3. Integrate booking lifecycle only after confirming its independent staging implementation.
4. Add role and ownership QA tests, then perform one end-to-end staging review.

This is a code-structure inventory, **not** a claim that each legacy feature is fully implemented or browser-tested.
