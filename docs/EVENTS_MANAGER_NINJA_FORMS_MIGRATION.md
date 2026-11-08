# BubbaWP + Ninja Forms booking integration

## Decision (2026-11-08)

BubbaWP is the **single source of truth** for activities, sessions, availability, capacity, reservations, attendees, consent, payment state and booking lifecycle.

Ninja Forms is the **family-facing booking form interface**. Its submission is input to BubbaWP; it is not a second booking database and does not independently confirm a reservation.

**Events Manager is excluded from the booking architecture.** Do not map BubbaWP activities/sessions into Events Manager or use Events Manager reservations. This avoids duplicate master records and competing booking lifecycles.

## Responsibilities

| Component | Responsibility |
|---|---|
| BubbaWP | Validate session/occurrence, calculate price, reserve capacity, prevent duplicates, validate parent-owned family profiles, write attendees and versioned consents, own booking/payment status and lifecycle |
| Ninja Forms | Render and collect the family booking form; provide a supported submission hook/payload to the BubbaWP adapter |
| Stripe | Existing BubbaWP-controlled payment flow; test mode only until all payment and reconciliation gates pass |
| Events Manager | Not used by the booking flow |

## Existing functionality to preserve

- Existing native activity and wp_sessions records and IDs.
- Native family/child/pregnancy profiles and parent ownership checks.
- Existing wp_bh3_bookings, wp_bh3_booking_attendees and wp_bh3_booking_consents structures; no schema changes without separate approval.
- Existing capacity locking, duplicate protection, booking lifecycle and payment validation.
- Existing Stripe test checkout and webhook safeguards.
- Existing Leader Portal and My Hub integrations.
- Existing Fluent Forms Form 7/8 configuration, submissions, checkout hooks and data until a replacement has passed staging parity and cutover is explicitly approved.
- All existing database tables, records and activity links; no automatic migration or deletion.

## Current source audit

- The existing checkout is wired to Fluent Forms through fluentform/submission_confirmation.
- The existing submission inspection reads from the Fluent Forms submissions table; the binding path validates that exact stored submission and then writes attendees/consents to BubbaWP tables.
- The existing booking writer/binding path has submission-scoped and booking-scoped locking/idempotency checks. Its current provenance field is booking_submission_id, with a unique index in the staging schema.
- The existing reserve_occurrence() path is staging-gated and calls quote_session(); quote_session() currently returns free_session for a zero-price session. Therefore the present reservation primitive is not yet a complete free-booking path. Do not claim free bookings are supported until this is addressed and tested.
- The current Stripe test-checkout guard expects booking_form_id and booking_submission_id when form binding is enabled. A Ninja Forms submission ID must not be written into this Fluent Forms-oriented provenance contract without a provider-aware design; numeric IDs from different form providers can collide.
- Ninja Forms documents ninja_forms_after_submission and custom action hooks that receive $form_data containing form_id, fields and settings. This confirms a supported integration entry point, but does not prove the exact installed version, field mapping, persistence model or compatibility with BubbaWP's current Fluent Forms-specific submission reader.
- The staging schema supplied on 2026-11-08 confirms wp_sessions, wp_bh3_bookings, wp_bh3_booking_attendees and wp_bh3_booking_consents. No table alteration is authorised.

## Implementation sequence

1. Confirm the active Ninja Forms booking form's numeric ID, installed plugin version, exact field keys/types and actual $form_data structure on staging. The public form URL/slug is not assumed to be its numeric form ID.
2. Implement a separate BH_Ninja_Forms_Booking_Adapter class in BubbaWP. Keep it behind a staging-only feature flag that defaults to disabled.
3. Refactor only as needed to let the existing validated booking service consume a normalized, trusted submission payload from either supported form provider. Do not duplicate capacity, pricing, attendee, consent or Stripe logic. First separate form-provider verification from canonical booking binding so the core does not query the Fluent Forms table for Ninja Forms submissions.
4. Preserve form provenance and idempotency. Do not assume a Ninja Forms submission ID can safely be written into the existing booking_submission_id field until uniqueness/collision semantics and provider identity have been designed and tested. If a separate provider-aware mapping is required, propose it separately; do not alter schema implicitly. Keep Stripe test checkout disabled until its binding guard accepts the verified provider-aware record.
5. Test invalid/missing fields, stale form context, wrong session/date/places, guest/member permissions, forged or foreign child profile IDs, consent refusal, duplicate callbacks, concurrent final-place attempts, and failed payment hand-off.
6. Verify free booking flow and Stripe test flow on staging. Keep live payments disabled.
7. Compare leader/family views, confirmation emails, privacy export/erasure, cancellations and rollback.
8. Only after all gates pass, request explicit approval to switch booking forms. Do not deactivate or delete Fluent Forms or its records before approval.

## Safety constraints

- No live changes, no production writes, no live payment activation.
- No changes to existing tables or records, no migration, no deletion and no plugin deactivation.
- Do not trust browser-supplied session, price, organiser or family profile IDs; validate server-side against authenticated ownership and the signed session context.
- Never create a booking from an enquiry-only submission.
- If the Ninja Forms payload or stored submission cannot be securely tied to the authenticated parent, stop the booking and return a safe error.
- Do not send child medical/emergency details in ordinary notification emails.

## Current status

**Architecture agreed; adapter not yet implemented.** The next active task is B-NF-01: verify the exact staging form configuration and payload, then implement the isolated adapter. No code changes, database writes or staging booking tests are claimed by this document.
