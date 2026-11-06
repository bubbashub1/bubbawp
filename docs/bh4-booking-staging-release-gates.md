# BH4 booking release gate — staging-first

This checklist is a release contract, not evidence that tests passed. No production database or personal records should be altered to complete it.

## Gate 1 — Schema and history
- [ ] Export and securely retain a staging database backup; document rollback.
- [ ] Verify the actual WordPress prefix and physical booking table name from the installed schema.
- [ ] Confirm whether `booking_status` and its index already exist before applying any additive migration.
- [ ] Inspect historical status distribution using aggregate counts only; reconcile old rows before interpreting the default `reserved` as real reservations.
- [ ] Verify unique booking submission and Stripe checkout session indexes; do not remove or rewrite payment history.
- [ ] Run the read-only Booking Structure audit again and attach evidence.

## Gate 2 — Ownership and state transitions
- [ ] Only the owning verified organiser can view and modify their own booking.
- [ ] A family can view only their own booking; guests require a secure, expiring access mechanism.
- [ ] CSRF nonces and server-side capability checks are enforced on all mutations.
- [ ] Explicitly test reserved → confirmed/enrolled, cancellation, expiry and invalid transitions.
- [ ] Capacity checks are atomic under two simultaneous reservation attempts.
- [ ] Booking status and payment status remain independent; Stripe webhooks are idempotent.

## Gate 3 — Consent and communications
- [ ] Fluent Form #7 consent mapping is validated against deployed form field keys.
- [ ] No optional child gender/year-of-birth data is disclosed without consent.
- [ ] Verified organiser email receives only appropriate booking details.
- [ ] Email failures are logged without exposing private content; retries do not duplicate bookings.
- [ ] Booking terms, cancellation/refund wording and enquiry-versus-confirmed language match the user journey.

## Gate 4 — Staging sign-off
- [ ] Test free and paid bookings as guest and logged-in family.
- [ ] Test leader enrollment, reminders, removal, and payout display.
- [ ] Test desktop and mobile, keyboard access, error and empty states.
- [ ] Confirm backups, rollback instructions, and named approval before live migration.

**Release rule:** A merged PR or green unit test alone does not satisfy these gates. Keep live writes disabled until all required staging evidence is reviewed.
