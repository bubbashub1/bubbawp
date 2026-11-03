# Bubba Hub 3.0 — legacy migration compatibility map

Scope: source inspection of `bubbashub1/bubbaplugin` main and `bubbashub1/bubbawp` main on 2026-11-03. This is not a declaration of parity.

## Verified source architecture
- Legacy `README.md`: standalone directory, activity detail, planner, My Hub, accounts, class leader, admin. Legacy production workflow deploys standalone application to public_html. **Never transplant this workflow or run it on the staging WordPress repository.**
- Legacy `api/db.php`: PDO connection and standalone `api/config.php` / wp-config parsing. WordPress integration should use `$wpdb`, WP authentication and capability checks; do not copy the credential parser or direct public API endpoints.
- Legacy `api/activities.php`: standalone JSON response, filtering/pagination, clean-slug resolution, `bh_activities` queries, dynamic schema checks.
- Legacy `database/schema.sql`: `bh_organisers.user_id`, `organisation_name`, `email`, `website`; `bh_venues.activity_id` and `venue_name`; `bh_sessions.venue_id`. These do **not** match new WordPress `wp_organisers`, `wp_activity_venues` and `wp_sessions.activity_id` schema.
- New WordPress modules: activities, organiser, venue, activity-venue and session admin, linked user metadata and My Listings.

## Migration approach
| Feature | Existing source | Adaptation | Priority |
|---|---|---|---|
| Activity directory | legacy `api/activities.php` | Implement read-only `$wpdb` query service for new tables; search, filters and paging; do not copy raw SQL | P0 |
| Activity detail | legacy standalone activity pages | Read-only WP activity detail service, organiser, venues and sessions; theme consumes it | P0 |
| Leader access | legacy leader portal | WP user and `bh3_organiser_id` meta, ownership checked on every POST | P0 |
| Activity editing | new BH3 draft editor | permit linked owner, lock organiser ID, validate ownership on save | P0 |
| Venues and timetable | new BH3 admin modules | owner-scoped management with explicit activity-venue relations | P0 |
| Planner / My Hub | legacy standalone | WP auth and separate family data migration; defer until P0 tested | P1 |
| Booking / payments | legacy standalone | security, financial and permission audit; do not bulk-enable | P2 |
| Admin and notifications | legacy standalone | audit side effects, cron, mail, CSRF before adapting | P2 |

## Safe bulk-migration boundaries
1. Reuse HTML/CSS, layout and pure formatting/validation functions first.
2. Convert API handlers into WP service methods, then add explicitly registered WP REST routes with permission callbacks where needed.
3. Map fields and relations explicitly; never assume `bh_*` and `wp_*` have interchangeable columns.
4. Keep staging-only flags, no automatic DDL, no live imports, no production deploy.
5. Verify create/read/edit for two organisers and two users, including cross-organiser denial.
6. Only after passing automated checks and staging browser QA, connect the new theme and enable public read-only pages.

## Known limitations
- The current WP plugin does not yet replicate the standalone app.
- Current `My Listings` is read-only for class leaders.
- Theme's activity design preview is not yet connected to new activity data.
- Legacy `bh_*` records have not been migrated to the new `wp_*` tables.
- Full legacy file inventory and exhaustive field parity still need auditing.
