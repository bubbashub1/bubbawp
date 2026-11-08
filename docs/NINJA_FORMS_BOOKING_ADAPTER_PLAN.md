# BubbaWP booking forms: Ninja Forms adapter plan

## Decision (2026-11-08)

- BubbaWP is the sole booking authority and owns activity/session validation, capacity, reservations, attendees, consent snapshots, booking states and payment orchestration.
- Ninja Forms is the family-facing booking form interface.
- Stripe remains in the existing BubbaWP payment layer and stays disabled unless all existing staging-only test gates are satisfied.
- Events Manager is not part of the core booking architecture. Do not mirror BubbaWP activities or sessions into a second event store.
- Existing Fluent Forms 7/8 forms, submissions and checkout remain intact until a replacement passes staging parity and the owner explicitly approves cutover.
- Native BubbaWP family profiles remain authoritative. Do not recreate child profiles in Ninja Forms.

## Source audit findings

1. BH_Marketplace_Settings_3::register() currently registers Fluent Forms field filters and the fluentform/submission_confirmation hand-off.
2. filter_form7_checkout_confirmation() reads the saved Fluent Forms confirmation payload, then calls the existing bound test-checkout route.
3. inspect_booking_form_submission() and inspect_booking_form_fields() read the Fluent Forms submissions table directly and verify form ID, submission owner, freshness, session, date, places, parent account email and booking consent.
4. inspect_booking_form_attendees() reads the Fluent Forms JSON response for selected native family-profile IDs and child-sharing consent, then validates profile ownership in BubbaWP.
5. bind_parent_submission_to_booking() currently expects a Fluent Forms submission and records the form/submission IDs in bh3_bookings; it writes attendee and versioned consent rows transactionally.
6. The bound checkout route additionally requires the existing staging-only booking, reservation, Stripe test checkout, webhook and test-confirmation feature constants.
7. The existing wp_bh3_bookings, wp_bh3_booking_attendees, and wp_bh3_booking_consents schemas have already been inspected on staging. No schema change is proposed by this plan.

## Ninja Forms integration evidence

The official [Ninja Forms WP Hook documentation](https://ninjaforms.com/docs/wp-hook/) says the configured action invokes a WordPress action with a form_data array containing the form ID, submitted fields and form settings. It also states this is an action, not a filter, so it cannot rewrite submitted data. The documentation does not establish a persisted submission ID in that payload.

The [Ninja Forms forms API](https://developer.ninjaforms.com/codex/forms/) and [submissions API](https://developer.ninjaforms.com/codex/submissions/) document form/field/action model access and saved-submission model access. These APIs must be verified against the exact staging version and actual save/action order before implementation relies on them.

## Implementation sequence

### B-NF-01 — Provider-neutral booking input

- Extract a narrow validation contract that accepts trusted, normalized form values and a verified form provenance record.
- Preserve all current Fluent Forms validation and booking behaviour through a compatibility adapter.
- Do not duplicate capacity, booking, attendee, consent, Stripe or notification logic.
- Preserve current form ID 7/8 configuration and field mappings; do not guess the new Ninja Forms form ID or field keys.
- Do not store full form payloads in BubbaWP booking tables.

### B-NF-02 — Staging form/API verification

- Add a read-only, administrator-only staging diagnostics panel that discovers the installed Ninja Forms version, form IDs/titles, field IDs/keys/types and configured action types without displaying submission contents or personal data.
- Confirm installed Ninja Forms version from staging.
- Confirm the exact form ID, field keys and field types for the current form (the visible “Who is attending?” label is not itself a field key).
- Confirm whether Record Submission is enabled and how to retrieve the saved submission ID at the chosen custom action timing.
- Confirm the persisted submission is linked to the authenticated WordPress user; do not trust an ID supplied by the browser.
- Confirm action ordering and error/redirect handling. A form success message must not imply a booking exists if BubbaWP validation or reservation fails.

### B-NF-03 — Isolated adapter

- Add a separate adapter class/module, loaded only on staging.bubbahub.co.uk and only when a dedicated constant such as BH3_ENABLE_NINJA_BOOKING_ADAPTER === true is explicitly set.
- Default the feature flag to off; do not enable it in code or site configuration as part of this PR.
- Restrict the adapter to one explicitly configured form ID.
- Resolve the server-stored Ninja Forms submission and current user; verify ownership, freshness and exact submitted values.
- Use a unique, stable submission identity for idempotency. Do not overload the existing unique booking_submission_id with a Ninja Forms ID until cross-provider collision semantics are designed. Prefer a separate, explicitly reviewed provenance mapping if the existing column cannot safely represent both providers.
- Re-validate session, occurrence date, places, account email, consent and native family-profile ownership server-side.
- Derive activity, organiser, price and commission from BubbaWP, never from browser fields.
- Call the existing canonical reservation/payment service only after all checks pass.

### B-NF-04 — Test gates

- Unit tests for malformed fields, expired/missing submissions, wrong owner, altered session/date/places, missing consents, foreign child IDs, duplicate submissions and unsupported recurrence.
- Verify free booking and paid test checkout separately.
- Verify capacity contention and idempotency under duplicate/replayed submission actions.
- Verify that form failure does not create a booking; booking failure does not report a successful reservation; payment failure leaves only the documented expiring hold.
- Confirm leader and family views use canonical BubbaWP booking records.
- Test email, privacy export/erasure, cancellation, refund and rollback behaviour.

## Explicit non-goals

- No Events Manager event/session mirror.
- No new booking database or Ninja Forms payment action.
- No change to native child/family profiles.
- No schema migration or database write in this planning step.
- No disabling, deleting or altering Fluent Forms forms, submissions or existing checkout.
- No live-site activation, payment or deployment.

## Current status

Architecture agreed and source/API audit started. The current code is still Fluent Forms-specific. The Ninja Forms adapter is not implemented or activated. Exact staging plugin version, form ID/field keys and saved-submission timing remain to be verified.