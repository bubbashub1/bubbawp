# Leader Hub foundation — staging acceptance plan

## Scope
- Dashboard shortcode: `[bh3_leader_dashboard]`.
- WordPress admin preview: `/wp-admin/admin.php?page=bh3-leader-dashboard`.
- Shared access helper: `BH_Leader_Access_3`.
- No changes to the existing legacy leader portal, user roles, schema, or published activities.
- FluentAuth integration means consuming the normal WordPress authenticated user, not calling undocumented FluentAuth APIs.

## Required checks before merging
1. Run `php -l` on both new PHP files and the changed manager bootstrap.
2. Open the shortcode on staging while signed out; confirm a sign-in prompt and no activity data.
3. Sign in with a WordPress account lacking `bh3_organiser_id`; confirm the unlinked state.
4. Link a staging test account to an existing organiser; confirm only that organiser's activities appear.
5. Verify a nonexistent organiser ID is rejected.
6. Search with ordinary text, apostrophes and HTML-like characters; confirm escaped display and scoped results.
7. Open dashboard on mobile; verify responsive layout, keyboard focus and search.
8. Verify draft links resolve to existing edit, session and venue screens.
9. Verify published activities remain read-only and another organiser's activity ID is rejected by edit/save endpoints.
10. Verify no new database tables or background jobs are created by the foundation.
11. Test FluentAuth login and logout on staging, confirming WordPress sessions are recognised.
12. Confirm no frontend content is exposed on production by this staging-only shortcode.

## Known limitations / next phase
- Existing draft-only edit, timetable and venue handlers still need end-to-end staging QA.
- Published listing editing and close/unpublish need dedicated permission checks and audit history.
- The new dashboard currently reads the fresh BH3 WordPress tables, not legacy `bh_` data.
- Multi-organiser access, claim verification and Free/Pro entitlement checks are not implemented.
- Reservations, payments, reminders and analytics are not implemented.
- No live deployment or database migration has been performed.

## Merge gate
Keep the PR in draft until PHP lint, organiser-isolation testing and staging browser checks pass. Merge to main only after these checks; deploy to staging before any production rollout.

## Legacy account-link workflow
1. On staging, open **Bubba Hub 3.0 → Leader Account Links** as a WordPress administrator.
2. Independently verify the leader's identity and their right to manage the legacy organiser.
3. Enter the **WordPress user ID** and the **legacy bh_organisers ID** (these are not interchangeable).
4. Save and sign in as that leader to view read-only legacy listings.
5. Set the legacy organiser ID to **0** to revoke the mapping; check that legacy listings disappear.
6. Verify a non-admin cannot open the linking page or submit the admin-post action.
7. Verify an invalid organiser ID or missing WordPress user is rejected without modifying existing mappings.
8. Confirm the server error log records IDs for link changes; do not log names or emails.

Legacy listings intentionally remain read-only. Before enabling edits, review all legacy table fields, claim/approval states, schedule schema, and cancellation audit requirements.
