# BubbaWP staging prototype — installation and acceptance checks

Prototype source: booking preview branch at commit 4508f726eff89895f57a21fd1ec137c464aadab6. This branch is **not a production release**. Keep production untouched.

## Before installing
- Take a staging files + database backup and record the currently installed plugin version.
- Download the **bubba-hub-manager** ZIP from successful GitHub Actions run 42052469336 (artifact 13427042722); GitHub Actions artifact is a ZIP containing the packaged plugin ZIP. Extract the inner plugin ZIP before using WordPress Plugins > Add New > Upload Plugin.
- Confirm BubbaTheme staging uses the merged theme changes from PR #25; otherwise the public session presentation may differ.
- Do not enable BH3_ENABLE_BOOKING_RESERVATIONS, BH3_ENABLE_STRIPE_TEST_CHECKOUT, BH3_ENABLE_BOOKING_FORM_BINDING, BH3_ENABLE_PARENT_TEST_BOOKING, BH3_ENABLE_STRIPE_TEST_WEBHOOK, BH3_ENABLE_TEST_BOOKING_CONFIRMATION, BH3_ENABLE_UNLINKED_BOOKING_TEST_UI, or BH3_ENABLE_STRIPE_TEST_ONBOARDING.
- Verify no live Stripe keys or live payment endpoints are configured.

## Smoke-test pages
1. /leader/ — leader dashboard loads without fatal errors.
2. /leader/bookings/ — [bh_leader_booking_dashboard] renders calendar/list, empty state, and mobile layout.
3. /leader/timetable/ — [bh_leader_timetable] loads with a leader account linked by bh3_organiser_id.
4. /leader/new-activity/ — inspect wizard Step 6, note any session selection gaps.
5. /my-hub/ — existing family profile, favourites, and planner unaffected.
6. /activity/<known-slug>/ — timetable renders, no Book/Enquire button without a valid URL.
7. /auth — existing sign-in continues to work.
8. WordPress admin > Bubba Hub — menus load; check PHP/server logs.

## Leader session acceptance
- Add/edit one weekly session with venue, dates, time, title, term time; reload and verify persistence.
- Assign to two listings belonging to same organiser; verify one calendar occurrence and correct listing associations.
- Remove one secondary assignment without deleting primary session.
- Attempt cross-organiser listing assignment and session edits with test accounts; must return 403 without database writes.
- Invalid time/day/date and invalid capacity/price must be rejected.
- Check session deletion leaves no stale assignments or booking references; do not test deletion with real bookings.

## Booking prototype limits
- Calendar and session editing are reviewable; payments, guest booking, booking-form-to-checkout handoff, consent lifecycle and leader notifications are not approved for use.
- The plugin's default feature constants are false, but some staging shortcodes/modules register independently; check pages before sharing prototype links.
- Schema setup must be performed only by an administrator on staging after backup and separate review. No automatic migrations.
- Record screenshots and errors (page URL, account role, action, expected/actual result) before any rollout.

## Release gates
- [x] PHP lint, BH3 validation, plugin foundation checks and ZIP packaging passed on commit 4508f726.
- [ ] WordPress staging install and smoke test.
- [ ] Leader/parent role isolation tests.
- [ ] Schema upgrade compatibility and data integrity.
- [ ] Mobile layout/accessibility checks.
- [ ] Session assignment and deletion consistency checks.
- [ ] Booking/payment, Stripe test webhooks and fee reconciliation.
- [ ] Explicit approval for any merge to main or production activation.
