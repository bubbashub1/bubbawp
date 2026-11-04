# Leader Close: staging verification

Scope: new WordPress-prefixed `activities` table only. Do not test against legacy `bh_` tables or production.

## Preconditions

- Back up staging and confirm the BH3 schema is installed.
- Create two separate organisers and two WordPress users; link each user to their own organiser through the staging linking screen.
- Create a published activity for each organiser and a draft activity for the first organiser.

## Checks

1. Sign in as organiser A. Confirm only A's activities appear in `[bh3_leader_dashboard]`.
2. Confirm **Close listing** appears on A's published activity and requires the confirmation checkbox.
3. Submit the action. Confirm A's activity changes from `published` to `draft`; its activity ID, sessions and venue links remain intact.
4. Confirm the closed activity is absent from the BH3 public directory and activity detail. The directory and detail query filters should exclude `draft`.
5. Confirm the closed activity appears with draft editing controls in the leader dashboard.
6. Sign in as organiser B. Attempt a forged close request for A's activity with B's nonce. It must fail ownership validation and leave the row unchanged.
7. Attempt a GET request, missing nonce, missing confirmation, and duplicate close. Each must fail without changing another record.
8. Confirm unlinked WordPress users cannot close any listing.
9. Confirm production does not expose the action because the handler is staging-gated.
10. Check PHP lint on the changed files and inspect the WordPress debug log before merge.

## Release gate

Do not merge or deploy to production until all checks pass on staging. Feature flags remain unchanged.
