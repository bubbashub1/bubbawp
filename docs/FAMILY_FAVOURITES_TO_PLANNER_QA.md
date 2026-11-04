# Saved favourites → planner / visited

The Family Hub now offers Add to planner and Mark visited for saved activities, only when staging family actions are explicitly enabled.

## Preconditions

- `BH3_FAMILY_PRIVATE_PREVIEW` and `BH3_FAMILY_ACTIONS` must both be true on staging.
- `BH3_FAMILY_LEGACY_ACTIVITY_STATUS_VERIFIED` must only be set after confirming `bh_activities.status` uses `published`/`publish` and its IDs match the saved activity IDs.
- Confirm `bh_planner` columns, unique constraints and defaults on staging before any write test.

## Test cases

1. Signed-out, leader and unlinked accounts cannot submit actions.
2. CSRF nonce is required.
3. Family A cannot add a saved activity belonging only to Family B.
4. Unpublished and missing legacy activity IDs cannot be added.
5. Add to planner creates one row with `visited=0`.
6. Repeating Add to planner does not create a duplicate row.
7. Mark visited on a saved but unplanned activity creates a planner row with `visited=1`.
8. Mark visited on a planned activity updates the existing row.
9. Saved favourites are not deleted by either action.
10. Removing a planner entry does not remove the saved favourite.
11. Existing legacy My Hub and planner continue to display the same records.
12. Verify full-page caching does not cache private family data.

No staging runtime tests have been completed. Keep the feature flags disabled until tests pass.
