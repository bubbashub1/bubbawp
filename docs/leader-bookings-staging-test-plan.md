# Leader bookings and timetable — staging release gates

Status: NOT READY TO MERGE TO MAIN. This branch is a staging-only preview.

## Setup
- Back up staging database and confirm wp_ prefix tables are present.
- Admin: Bubba Hub 3.0 status > install/update schema on staging (explicit, never automatic); verify session_listing_links table exists. Do not install on live.
- Confirm leader account is linked by bh3_organiser_id and has two draft/published listings.
- Ensure /leader/bookings/ contains [bh_leader_booking_dashboard] and /leader/timetable/ contains [bh_leader_timetable].

## Leader checks
- Add weekly session from calendar; set title, times, dates, venue, term time, enquiry, booking price/capacity.
- Assign one session to two owned listings; edit once; verify calendar displays one occurrence and both assignments persist.
- Remove one assignment; verify primary listing remains and no session is deleted.
- Try assigning another organiser's listing via forged POST; must fail before any session write.
- Test published and draft listings, mobile viewport, filters by name, listing, bookings and enquiries.
- Check a single-date session and weekday mismatch validation.
- Delete session and verify linked assignment rows and option metadata are removed.

## Must complete before main merge
- Integrate session selection/creation into listing wizard Step 6.
- Public listing session display must include shared sessions with correct ownership/status checks.
- Fluent Form 6 enquiry: opt-in only, verified activity/session/date, explicit consent, enquiry is not a booking.
- Fluent Form 7 booking/payment: configurable ID (bh3_booking_form_id default 7), server-verified price and session, idempotent payment confirmation, capacity per dated occurrence, cancellation/refund handling; no confirmation on form submission alone.
- Verify no conflict with existing booking shortcodes and older booking PRs.
- Run PHP lint, WordPress integration tests, accessibility and regression checks on staging.
- Obtain explicit approval before merging to main or deploying to live.
