# Leader booking actions — implementation gates

Status: design and acceptance contract only. No live mutation routes are authorised by this document.

## Scope

Add leader-facing actions to the staging booking dashboard only after each gate below is implemented and tested. Existing `BH_Marketplace_Settings_3::transition_booking_status()` is **administrator-only** and must not be exposed directly to leaders or public forms.

## Ownership and authorisation

- Require an authenticated user, a valid action-specific WordPress nonce, and a server-side organiser ID derived from the authenticated account (never from a submitted organiser ID).
- Load the booking from `{prefix}bh3_bookings` by numeric ID, verify its organiser matches the authenticated leader's authorised organiser, and verify the session and occurrence belong to that organiser.
- Administrator staging preview of another organiser is **read-only**; preview selection must not grant mutation privileges.
- Recheck ownership and transition validity under the same booking-level lock used by payment confirmation; do not rely on a prior page render.
- Use POST for mutation; reject GET, unauthorised requests, invalid nonces and replayed or stale transitions.

## Transition policy

- `reserved -> confirmed`: only after verified payment; leaders must never mark a pending payment as paid.
- `confirmed -> enrolled`: authorised leader action after checking that the booking belongs to the leader.
- `reserved -> expired`: only if pending payment and the UTC expiry time has elapsed; ideally controlled by a scheduled expiry process, not an arbitrary leader action.
- `reserved/confirmed/enrolled -> cancelled`: needs a defined cancellation/refund and notification policy before enabling for paid bookings. Changing status alone does **not** refund payment.
- Disallow other transitions; repeated same-state requests must be idempotent.

## Privacy and display

- Show only information necessary to administer a booking, with role-appropriate access.
- Never include customer or child personal details in public cache, URLs, analytics events, or unauthorised error messages.
- Keep read-only calendar totals conservative; invalid statuses and unpaid expired holds must not silently free capacity.

## Staging acceptance tests

1. Leader A cannot modify Leader B's booking even when supplying a valid-looking ID and nonce.
2. Unauthenticated, GET, missing nonce, invalid nonce, and stale nonce requests fail without mutation.
3. Admin preview of Leader B does not enable leader mutation of Leader B's records.
4. Pending payment cannot be confirmed or enrolled through a bypass.
5. Concurrent webhook confirmation and expiry/cancellation cannot produce contradictory booking/payment states.
6. Paid cancellation requires a documented refund decision and an auditable payment reconciliation path.
7. Double-submit and replay leave a single valid final state.
8. Capacity totals stay correct for reserved, confirmed, enrolled, cancelled and expired records.
9. No PII appears in cross-organiser responses or logs.
10. Production host and disabled feature flag reject every mutation.

## Rollout order

1. Add a dedicated staging-only organiser-authorised transition service and automated tests.
2. Add nonce-protected POST handlers for a minimal enrolment action.
3. Add leader UI controls with confirmation and clear status feedback.
4. Define and implement cancellation/refund policy before enabling cancellation of paid bookings.
5. Validate end-to-end on staging, then separately review production readiness.
