# Family account linking — security and rollout contract

## Implemented in PR #70
`BH_Family_Identity_3::current_legacy_id()` is a **read-only resolver**, not an account-linking workflow. It accepts only an authenticated WordPress user with a positive integer in protected user-meta key `_bh3_verified_legacy_family_id`, verifies that exactly one WordPress account maps to the legacy ID, checks for `bh_users` table existence, and requires legacy status `active`.

No code in this PR writes the mapping or returns child/saved/planner records. The Family Hub feature flag remains disabled, and the preview does not invoke the resolver.

## Requirements before activation
1. Confirm whether `bh_users` is in the same database as the new WordPress install; the resolver currently checks the WordPress connection and deliberately fails closed if not.
2. Audit the existing Login Bridge and FluentAuth account lifecycle, including how WordPress user IDs are provisioned.
3. Implement an authenticated, deliberate linking ceremony (e.g. reauthentication to the legacy account plus confirmation). **Do not link on email equality alone.**
4. Prevent arbitrary modification of `_bh3_verified_legacy_family_id` by ordinary users, REST profile writes, imports or profile forms; verify the WordPress user-meta registration/authorization policy.
5. Protect against duplicate and racing links using transactional or otherwise atomic uniqueness enforcement before enabling mapping writes.
6. Log mapping changes without logging tokens, passwords, child data or other secrets.
7. Ensure linked user ID and status are revalidated on each private request; verify ownership for each child/planner/saved activity query.
8. Add tests for missing tables, cross-database configuration, inactive accounts, duplicate links, changed emails, unlinked users, administrators and session expiry.
9. No migrations or live changes without explicit approval.

## Design limitation
The current resolver uses a user-meta convention, not a complete verified linking implementation. Its presence must not be treated as evidence that FluentAuth, the Login Bridge or legacy family records are integrated.
