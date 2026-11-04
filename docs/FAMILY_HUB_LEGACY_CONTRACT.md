# Family Hub legacy contract — source audit (2026-11-04)

Source reviewed: bubbashub1/bubbaplugin/api/my-hub.php, api/planner.php, api/auth.php (first 180 lines each). This is a **partial code review**, not runtime verification.

## Identity
- My Hub uses PHP session name `BUBBAHUBSESSID`, cookie-only, HTTP-only, SameSite=Lax, with session key `bh_user_id`.
- Admin-only session can receive an empty, read-only dashboard. It is **not** a family identity.
- Active user status is checked against `bh_users`; the WordPress current user ID is not proven equivalent.
- Planner code starts a PHP session without explicitly setting the same session name; verify session compatibility before migration.
- CSRF token is stored in `$_SESSION['bh_csrf']`. Do not expose it to public pages or cacheable HTML.

## Legacy read contracts
- `GET /api/my-hub.php?view=family` -> `user`, `children`, `bumps`, `csrf`.
- `GET /api/my-hub.php` -> `user`, `children`, `bumps`, `saved`, `planner`, `bookings`, `csrf` (as seen in source).
- `GET /api/planner.php` -> `planned` (id, visited), `hidden_days`, `csrf`.
- All personal-data responses require server-side family authentication and must be `no-store`.

## Existing data model
- `bh_children`: id, user_id, name, gender, photo_path, date_of_birth, created_at, updated_at.
- `bh_bumps`: id, user_id, nickname, photo_path, due_date, created_at, updated_at.
- `bh_saved_activities`: user_id, activity_id, created_at.
- `bh_planner`: user_id, activity_id, visited, created_at, updated_at.
- `bh_hidden_planner_days`: user_id, day_of_week.
- `bh_booking_reservations`, `bh_booking_slots`: optional bookings; join activities and venues.

## Critical migration constraints
1. Legacy `api/my-hub.php` executes `CREATE TABLE IF NOT EXISTS` and potentially `ALTER TABLE` before responding. Do not call it as a read-only adapter until this behaviour is removed or a safe read-only replacement exists.
2. Never directly include legacy `api/db.php` in the new plugin.
3. Use a dedicated identity resolver with explicit, verified session-to-account mapping and a no-admin-impersonation policy.
4. Query all family records with an authenticated, server-derived user ID; never accept a client-supplied owner ID.
5. Keep child names, birthdays, photos, pregnancy data and booking records out of public REST, cached HTML, search indexes and analytics.
6. Preserve canonical activity IDs; do not duplicate activity records.
7. Planner `sync` performs destructive replacement of entries and hidden days; do not invoke it from preview or background reconciliation.
8. Confirm table presence, indexes, session naming, permissions and sanitized sample responses on staging before enabling real data.

## Implementation acceptance tests
- Anonymous request -> no family data.
- Admin-only session -> no child data and no family mutations.
- Cross-family ID tampering -> 403 or 404.
- Missing optional bookings tables -> dashboard remains usable.
- Read-only preview -> zero SQL writes.
- Browser back/refresh and legacy session continuity.
- Signed-out, expired session, inactive account and CSRF failure.
- Mobile, keyboard, screen reader and non-cached authenticated responses.

## Next implementation
Build a staging-only identity and read-only repository adapter **after** confirming session compatibility and database permissions. Keep the current shortcode in preview mode until then.
