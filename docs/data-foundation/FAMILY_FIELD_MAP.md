# Family Hub — field-by-field implementation contract
Status: draft, not staging verified. WP account identity is canonical; do not equate legacy bh_users.id and WP users.ID.

| Concept | Native table / field | Legacy source | Decision |
|---|---|---|---|
| Profile identity | bh_family_profiles.id, user_id | bh_children.id, bh_bumps.id | Verify provenance/mapping before import |
| Type | bh_family_profiles.profile_type | children vs bumps | Enumerate child/bump; avoid mixing IDs |
| Display name | bh_family_profiles.display_name | bh_children.name, bh_bumps.nickname | Restrict to owner |
| Child birth date | bh_family_profiles.profile_date | bh_children.date_of_birth | Define privacy and precision for UI; do not disclose by default |
| Bump due date | bh_family_profiles.profile_date | bh_bumps.due_date | Interpret according to profile_type |
| Gender | proposed/possibly separately added bh_family_profiles.gender | bh_children.gender | Verify actual staging column; explicit versioned migration, not implicit admin_init |
| Preferences | bh_family_preferences.preferences_json | bh_user_preferences categories, days, region, town, free-only, max price | Document JSON schema and migration rules |
| Saved | bh_saved_activities.user_id, activity_id | bh_saved_activities | Enforce unique user/activity |
| Planner | bh_planner.user_id, activity_id, session_id, planned_date, visited | legacy bh_planner activity_id, visited | Distinguish planned occurrence from general favourite |
| Reminders | bh_family_reminders.user_id, profile_id, profile_type, title, reminder_date | new | Prevent duplicates; support school/pregnancy templates |

## Native profile form (no duplicate Fluent Form planned)
Required: profile type, display name, child DOB or bump due date. Optional: gender; privacy and sharing controls. Always validate current WP user and ownership server-side. The booking enquiry form may show owned profiles, but never exposes them to other users.

## Routes / shortcodes
- /my-hub/ — `[bubbahub_family_hub]`
- /my-hub/profile/ and /my-hub/children/ — profile editor/list; verify shortcode assignment
- /my-hub/preferences/ — `[bubbahub_family_settings]` (verify)
- /my-hub/planner/ — planner/calendar (verify exact shortcode)
- /my-hub/favourites/ — `[bubbahub_family_favourites]` (verify)

## Acceptance tests
Only owner can list/edit/delete; bump and child validation differ; profile IDs cannot be spoofed; planner dates use WP timezone; no duplicates in saved items; legacy identity migration produces verified mapping; gender is not shared without specific consent; reminder milestones show correct date calculations.

## Staging gate
Inspect real DDL and schema version, shortcode registrations, WP page content, existing migration code and family data counts. Prepare versioned schema changes only for missing fields.
