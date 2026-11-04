# Family account linking — verified legacy bridge contract

## Source reviewed
- `bubbashub1/bubbaplugin/wp-content/mu-plugins/bubbahub-login-bridge-foundation.php`
- `bubbashub1/bubbaplugin/wp-content/mu-plugins/bubbahub-login-bridge-preflight.php`

## Actual existing mapping
The MU plugin exposes `bh_wp_resolve_legacy_identity()`, which returns either a `WP_Error` or an array with `legacy_id`, `wordpress_id`, and `role`. It uses **`bh_legacy_user_id`** user metadata (not the initially proposed `_bh3_verified_legacy_family_id`). It verifies:
- WordPress login, one positive legacy ID and uniqueness across WP users.
- Presence of `bh_users` and required columns.
- Active legacy account and matching account email.
- Matching role: `family` requires `bh_family`; `leader` requires `bh_leader`.

## 3.0 adapter
`BH_Family_Identity_3::current_legacy_id()` delegates to the existing MU bridge and returns a positive legacy ID only when the result is a valid family identity matching the current WP user. It returns 0 on missing bridge, missing mapping, error, leader role, or inconsistent identity. It performs **no writes**.

## Limitations and gates
- The MU bridge is intentionally not wired into legacy API sessions; FluentAuth sign-in does not automatically create `BUBBAHUBSESSID`.
- The preflight page is read-only and displays account readiness; it does not link users.
- Confirm the MU plugin is actually active on the staging site and the preflight results are correct.
- Confirm the legacy table is on the same DB connection; if not, the existing bridge fails closed.
- No family data reads are enabled in the preview yet.
- Do not auto-link by email or write mapping metadata until a separately reviewed, authenticated linking flow exists.
- The role and email checks of the existing MU bridge must not be bypassed.
- Do not expose child details in public endpoints, caches, logs, or admin preflight output.
- Test FluentAuth sign-in, role mapping, account mismatch, duplicate links, logout and account status changes before activating private data access.
