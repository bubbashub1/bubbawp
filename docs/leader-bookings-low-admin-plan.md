# Leader bookings — low-admin implementation contract

Status: staging-only draft; no public booking endpoint or payment flow is enabled.

## Product rule
A leader must not recreate an activity, venue or timetable to accept reservations. Booking configuration is layered onto the existing activity/session IDs. Use the site's WordPress timezone and date/time settings for display.

## Leader's daily flow
1. **Needs attention** displays pending placement decisions, exceptions and waiting-list offers.
2. **Today's sessions** links directly to a prefilled attendance register.
3. **Bookings** provides a searchable table with first name, surname initial, child name/age, email, phone, session, status and one-click actions.
4. **Bulk actions** permit confirming selected placements and sending reminders, with explicit review before sending.
5. Routine acknowledgement, confirmations and reminders are automated only after the leader enables them.

## Statuses and lifecycle
- pending: reservation received, placement not confirmed.
- confirmed: leader confirms payment or other arrangement; does not imply a payment was processed by Bubba Hub.
- waitlisted: no space, awaiting offer.
- cancelled: reservation cancelled with reason/audit.
- attended / absent: attendance is tracked separately from reservation status to avoid losing enrolment history.

All state transitions require a server-side check of authenticated leader ownership against the activity's organiser_id; never trust organiser_id or status in a form body. Nonces protect requests but do not replace authorisation.

## Capacity and fairness
Count only confirmed reservations against the confirmed-place capacity. When holds or auto-confirm are introduced, use a transaction/locking strategy to prevent simultaneous overbooking. Waitlist offers must have expiry and idempotency keys. Never auto-message families until consent, templates and sending logs are tested.

## Privacy and operations
Collect only information necessary for the booking; restrict access to the owning leader and authorised team; mask family information in dashboard summaries; escape output; validate and sanitise all writes; log status changes. Define retention and deletion rules before public release. Do not store payment card details.

## Delivery order
1. Staging-only storage and identity-scoped attention read model (foundation).
2. Explicit staging schema install control, with backup and verification.
3. Session booking configuration reusing existing timetable IDs.
4. Family self-service reservation form, with duplicate prevention and capacity checks.
5. Leader booking table and bulk confirmation actions.
6. Reminder queue, email logs, cancellation and waiting-list offers.
7. Attendance and reporting.
8. Payment integration as a separately reviewed phase.

## Current code
`class-bh-leader-booking-storage.php` defines two prefixed tables and an opt-in `install()` method, but **nothing calls install()**. It adds no public writes, payment processing, scheduled jobs or emails. `attention_counts()` is owner-scoped and returns zeros until schema installation. The existing `[bh_leader_bookings]` screen is read-only. Do not describe it as a functioning reservation system.
