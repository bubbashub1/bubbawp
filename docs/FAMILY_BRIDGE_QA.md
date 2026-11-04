# Family identity bridge QA — staging

This checklist tests the actual existing `bh_wp_resolve_legacy_identity()` MU plugin contract. It must be run with test accounts and without publishing personal information.

| Case | Expected family resolver result |
| --- | --- |
| Anonymous WP visitor | 0 |
| FluentAuth WP user without legacy mapping | 0 |
| Verified family account with active legacy status, matching email, bh_family role, unique bh_legacy_user_id | Positive legacy family ID |
| WP leader mapped to active leader with bh_leader role | 0 |
| WP admin without family mapping | 0 |
| Duplicate legacy mapping across two WP accounts | 0 |
| Multiple bh_legacy_user_id values on one WP user | 0 |
| Inactive legacy account | 0 |
| Mismatched email or role | 0 |
| Missing MU bridge function | 0 |
| Missing bh_users table | 0 |
| WordPress logout or expired cookie | 0 |

## Operator steps
1. Confirm both `bubbahub-login-bridge-foundation.php` and `bubbahub-login-bridge-preflight.php` are installed in staging MU plugins.
2. In WP admin, open **Bubba Hub → Login Bridge Checks** (the submenu is registered under `bubba-hub`).
3. Use test family/leader accounts; do not paste account emails, child details or session tokens into issue reports.
4. Verify login via FluentAuth creates a normal WordPress authenticated session.
5. Check the existing mapping only; do not edit `bh_legacy_user_id` manually in production.
6. Verify legacy account data uses the same database as the MU plugin or remains safely unavailable.
7. Confirm Family Hub preview still shows no private records and does not mutate tables.

## Gate for next PR
Implement server-side, family-owned, read-only repository methods for saved activities, planner and child profiles **only after** the identity bridge has passed the above tests. All queries must use the verified server-derived legacy family ID. Ensure private responses are never publicly cached.
