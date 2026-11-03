# Legacy compatibility contract — initial source audit

**Status:** Read-only source review, not live API verification. 2026-11-03.

## Endpoint inventory

| Endpoint | Source size | Referenced tables | Critical behavior |
| --- | ---: | --- | --- |
| `/api/activities.php` | ~21 KB | `bh_activities`, `bh_activity_schedules`, `bh_venues`, `bh_activity_venues`, `bh_sessions`, `bh_organisers` | Public search, paging, filtering, slug/id/pretty_slug lookup, venue/schedule joins |
| `/api/planner.php` | ~7 KB | `bh_users`, `bh_planner`, `bh_hidden_planner_days`, `bh_activities` | Authenticated family planner with writes; **never call writes from 3.0 foundation** |
| `/api/leader-portal.php` | ~48 KB | `bh_users`, `bh_organisers`, `bh_activities`, `bh_venues`, `bh_sessions`, bookings/subscriptions and more | Leader access, listings, timetables, bookings and mutations; requires role and entitlement parity |
| `/api/reservations.php` | ~5.6 KB | `bh_activities`, `bh_organisers`, `bh_children` | Request/consent/email workflow; not proof of a fully implemented booking engine |
| `/api/auth.php` | large | Not exhaustively audited | Custom authentication, rate limits and account behavior; must not replace with WP login blindly |
| `/api/db.php` | reviewed | PDO connector | Separate app DB config or WP config fallback; **also contains a table-creation helper**, so never include it blindly in new plugin |

## Activities public request keys found in source
`page`, `per_page`, `search`, `slug`, `id`, `pretty_slug`, `organiser_slug`, `category`, `town`, `region`, `day`, `min_price`, `max_price`, `min_age`, `max_age`.

The `pretty_slug` path detects ambiguous title slugs and may return HTTP 409, while unknown titles return 404. A 3.0 adapter must not silently resolve to a different activity.

## Compatibility policy
1. No legacy endpoint is automatically included or executed by the 3.0 plugin.
2. Existing `bh_` records, IDs and joins remain authoritative; no copied master data.
3. Define a separate read-only adapter with an explicitly configured source, not hardcoded production credentials or host.
4. No public cross-origin proxy, no arbitrary user-supplied URL, and no unauthenticated write operations.
5. Authentication/role mapping and data-table availability must be verified on isolated staging before enabling adapters.
6. Add contract tests for pagination, filter semantics, missing/ambiguous slugs, timetable joins, empty results and error states.
7. Preserve old routes until redirect/canonical tests demonstrate parity.

## Open evidence requests
- Actual read-only schema inventory including table columns and relationships.
- Sanitised sample JSON responses for activity list/detail and error cases.
- Current user session/cookie and WordPress role mapping.
- Browser checks for the family, leader and admin journeys.
- Confirm whether the new staging WP database contains the same `bh_` tables or needs a read-only integration boundary.

**Next step:** build a disabled-by-default, explicitly configured read-only directory adapter and fixture tests. Do not deploy to production.
