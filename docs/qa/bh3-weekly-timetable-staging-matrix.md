# BH3 weekly timetable — staging acceptance and security tests

Tracks #34. This is a manual QA specification, **not evidence of a browser test**.

## Preconditions

- Use only `staging.bubbahub.co.uk` with a disposable draft activity and test organiser/venue.
- Record plugin commit SHA, tester, browser and date. Do not use production data.
- Prepare two leader accounts owning different organisers, plus an administrator and a logged-out browser.

## Wizard timetable cases

| Case | Action | Expected result |
| --- | --- | --- |
| T01 | Enable Monday; add two slots with distinct times | Both slots appear in review and persist after draft save/reload |
| T02 | Disable a weekday containing slots | Disabled day does not create active sessions |
| T03 | Add a second slot then remove the first | Only remaining slot persists; no stale hidden values |
| T04 | Enter end time equal to start time | Server rejects the invalid row without partial write |
| T05 | Enter end time earlier than start time | Server rejects the invalid row without partial write |
| T06 | Leave one enabled slot missing a start/end time | Clear validation error; no malformed session |
| T07 | Select a venue not attached to the activity | Server rejects or explicitly validates venue assignment |
| T08 | Toggle term-time only per slot | Each saved weekly row retains its own term-time flag |
| T09 | Save, reload, edit one slot and save again | Existing row updates without duplicating other rows |
| T10 | Submit more than 100 weekly rows | Server enforces documented row limit safely |
| T11 | Submit a day value outside the allowed weekday set | Server rejects invalid day |
| T12 | Submit malformed time strings via request tampering | Server rejects invalid time |

## Permission and boundary cases

| Case | Action | Expected result |
| --- | --- | --- |
| S01 | Submit without nonce / with expired nonce | Request denied; no database changes |
| S02 | Logged-out POST to save endpoint | Request denied |
| S03 | Leader A edits Leader B's draft/session ID | Request denied; no database changes |
| S04 | Leader A supplies Leader B's organiser ID | Server validates ownership, never trusts posted ID |
| S05 | Leader A submits a published activity ID | Draft-only route denies changes |
| S06 | Request on a non-staging host | Staging-only functionality remains unavailable |
| S07 | Inject HTML/SQL-like text into venue/session inputs | Input validated, output escaped, SQL parameterised |
| S08 | Force an invalid venue ID | Request denied without orphan links |

## Verification record

- PHP lint: **not run** (documentation-only PR).
- JavaScript syntax: **not run** (documentation-only PR).
- Plugin packaging: **not run** (documentation-only PR).
- Browser QA: **not run**.
- CI: check the pull request's GitHub Actions results before merge.
- Release: staging only; do not deploy to live.

Any failing S-case blocks merge of the corresponding functional implementation. Record the failed request, expected result, actual result and a sanitised reproduction before fixing.
