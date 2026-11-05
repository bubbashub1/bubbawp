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

## Marketplace commission and leader payout requirements
- Parents pay for a dated session booking. Leaders receive the net proceeds and Bubba Hub retains a configurable commission. Persist gross, commission, processor fee, leader net, currency, refunds, and payment/payout status in auditable records.
- Leader Account Settings offers Stripe Connect onboarding, verified PayPal payout details, or Invoice/manual settlement. A selected preference is not a verified payment destination.
- Stripe Connect is the preferred automated marketplace route. PayPal and Invoice require separate settlement and reconciliation workflows.
- Decide commission percentage, processing fee allocation, payout timing, refunds/chargebacks, VAT and the meaning of invoice settlement before taking real payments.
- Calculate amounts and payout destinations server-side; verify provider webhooks idempotently. Never trust browser-supplied prices.
- Provide leader payout statements and admin reconciliation; keep unconfigured payout routes disabled.

## Must complete before main merge
- Integrate session selection/creation into listing wizard Step 6.
- Public listing session display must include shared sessions with correct ownership/status checks.
- Fluent Form 6 enquiry: opt-in only, verified activity/session/date, explicit consent, enquiry is not a booking.
- Fluent Form 7 booking/payment: configurable ID (bh3_booking_form_id default 7), server-verified price and session, idempotent payment confirmation, capacity per dated occurrence, cancellation/refund handling; no confirmation on form submission alone.
- Verify no conflict with existing booking shortcodes and older booking PRs.
- Run PHP lint, WordPress integration tests, accessibility and regression checks on staging.
- Obtain explicit approval before merging to main or deploying to live.

## Marketplace implementation progress (2026-11-05)
- Staging-only `BH_Marketplace_Settings_3` is registered from the manager bootstrap.
- Admin: Bubba Hub 3.0 > Marketplace Commission; default 3%, editable 0–100%, stored as `bh3_commission_percent`.
- `BH_Marketplace_Settings_3::split_pence($gross_pence)` returns gross, commission and leader amount before fees in integer pence. It does not charge or pay anyone.
- Leader preference shortcode: `[bh_leader_payout_settings]`; stores `bh3_payout_method` user meta for linked leaders. Stripe/PayPal/Invoice are preferences only, NOT connected payout methods.
- NEXT: integrate payout settings into leader Account Settings, verify organiser ownership and connect-account onboarding, add secure server-created Stripe Checkout and Connect webhooks, booking capacity locks, refunds and payout ledger. Do not process payments through Fluent Forms Free Stripe field (avoids 1.9% extra Fluent Forms fee).
- Block live checkout until Stripe credentials, charge model, processing-fee allocation, leader verification, tax/VAT and refund policy are confirmed and integration tests pass.
