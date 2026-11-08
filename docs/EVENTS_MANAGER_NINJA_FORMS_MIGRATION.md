# BubbaWP + Ninja Forms booking integration

## Decision (2026-11-08)

BubbaWP is the **single source of truth** for activities, sessions, availability, capacity, reservations, attendees, consent, payment state and booking lifecycle.

Ninja Forms is the **family-facing booking form interface**. Its submission is input to BubbaWP; it is not a second booking database and does not independently confirm a reservation.

**Events Manager is excluded from the booking architecture.** Do not map BubbaWP activities/sessions into Events Manager or use Events Manager reservations.

## Responsibilities

| Component | Responsibility |
|---|---|
| BubbaWP | Validate session/occurrence, calculate price, reserve capacity, prevent duplicates, validate parent-owned family profiles, write attendees and versioned consents, own booking/payment status and lifecycle |
| Ninja Forms | Render and collect the family booking form; provide a supported submission hook/payload to the BubbaWP adapter |
| Stripe | Existing BubbaWP-controlled payment flow; test mode only until all payment and reconciliation gates pass |
| Events Manager | Not used by the booking flow |

## Confirmed staging form details (2026-11-08)

The user confirmed the numeric form ID from the WordPress builder URL: **Ninja Forms Form ID 2**, titled **Booking Form**.

Visible fields confirmed from the staging form screenshots:

| Visible label | Visible type / behaviour | Notes |
|---|---|---|
| Parent / Guardian Name | Text; required | Asterisk visible |
| Email | Email/text; no required asterisk visible | Existing BubbaWP validator currently requires it and requires it to match the signed-in WordPress account email |
| Phone | Phone/text; no required asterisk visible | Optional in the visible form |
| Who is attending? | Checkbox group; required | Current preview shows “Test Child” and “Test Pregnancy Profile”; not yet proven to be dynamic profile options |
| Additional Information | Paragraph / textarea | Optional in the visible form |
| Share selected attendee details with the class leader for the purpose of managing my booking | Required checkbox | Must be stored as its own explicit consent |
| Agree to the Bubba Hub Booking Terms & Conditions | Required checkbox | Must be stored as its own explicit consent |
| Submit Test Enquiry | Submit button | The label alone does not establish whether this is an enquiry or a booking; the server must decide the workflow |

**Still unverified:** Ninja Forms field keys, internal field types/IDs, saved submission structure, installed Ninja Forms version, whether attendee options are dynamically populated from the signed-in parent's profiles, and the exact after-submission payload on this installation. Do not infer keys from labels or assume the two visible test options are live profile records.

## Existing functionality to preserve

- Existing native activity and wp_sessions records and IDs.
- Native family/child/pregnancy profiles and parent ownership checks.
- Existing wp_bh3_bookings, wp_bh3_booking_attendees and wp_bh3_booking_consents structures; no schema changes without separate approval.
- Existing capacity locking, duplicate protection, booking lifecycle and payment validation.
- Existing Stripe test checkout and webhook safeguards.
- Existing Leader Portal and My Hub integrations.
- Existing legacy form submissions and associated booking records must remain preserved and auditable. They are historical data, not the target form platform. Any read-only legacy compatibility path must be isolated from new submissions.
- All existing database tables, records and activity links; no automatic migration or deletion.

## Current source audit

- The existing booking checkout currently has a Fluent Forms-specific submission-confirmation hook. This is a legacy integration to be replaced by the Ninja Forms adapter; do not remove it until the replacement is verified.
- Existing dynamic attendee choices are injected through provider-specific legacy render filters. That code does **not** populate Ninja Forms fields. The native profile writer is account-scoped and supports child/pregnancy types, but the current Ninja Forms test choices are not proven to be dynamic profile options. The adapter must retrieve profile IDs server-side for the logged-in parent and validate ownership again at submission; it must not trust labels or browser-supplied IDs.
- The existing submission inspection reads from the legacy provider submissions table; the binding path validates that exact stored submission and then writes attendees/consents to BubbaWP tables. Replace this with verified Ninja Forms submission handling while retaining historical provenance.
- The existing booking writer/binding path has submission-scoped and booking-scoped locking/idempotency checks. Its current provenance field is booking_submission_id, with a unique index in the staging schema.
- The existing Form 7 contract, binding validator and owner resolver are explicitly legacy Form 7-specific: they hard-code Form ID 7 and provider field names such as booking_reference, family_profile_ids and consent keys. They are read-only/pure helpers, not a drop-in Form 2 integration. A safe refactor should separate provider/form identity from a provider-neutral normalized booking contract, preserve Form 7 behaviour, and only then add a Form 2 mapper.
- The existing profile ownership reader and native profile manager use wp_bh_family_profiles scoped by authenticated user_id, with profile type, display name, profile date and gender. The native profile module also hooks maybe_add_gender_column to admin_init and can execute ALTER TABLE automatically on staging admin page loads when gender is missing. This is an implicit schema mutation and must be removed from normal page-load behaviour through a separately reviewed, explicit migration before further family schema changes. Do not trigger it deliberately, silently substitute legacy child/bump tables or migrate profile data.
- The current Form 7 field validator requires parent name and parent email, and requires the email to match the signed-in WordPress account. The visible Ninja Forms screenshot does not show Email as required. This mismatch must be resolved deliberately; do not weaken account verification or rely on an optional unverified email.
- The existing reserve_occurrence() path is staging-gated and calls quote_session(); quote_session() currently returns free_session for a zero-price session. Therefore the present reservation primitive is not yet a complete free-booking path. Do not claim free bookings are supported until this is addressed and tested.
- The current Stripe test-checkout guard expects booking_form_id and booking_submission_id when form binding is enabled. A Ninja Forms submission ID must not be written into the legacy provenance contract without a provider-aware design; numeric IDs from different form providers can collide.
- Ninja Forms documents ninja_forms_after_submission and custom action hooks that receive form data containing form ID, fields and settings. This confirms a supported integration entry point, but does not prove the exact installed version, field mapping, persistence model or compatibility with BubbaWP's current Fluent Forms-specific submission reader.
- The staging schema supplied on 2026-11-08 confirms wp_sessions, wp_bh3_bookings, wp_bh3_booking_attendees and wp_bh3_booking_consents. No table alteration is authorised.

## Implementation sequence

1. Confirm Form ID 2's exact field keys/types, installed Ninja Forms version and actual after-submission payload on staging. Confirm whether the attendee checkbox options are populated from the authenticated user's native family profiles. Do not require the user to find hidden field keys manually if a safe read-only inspection route can retrieve them.
2. Implement a separate BH_Ninja_Forms_Booking_Adapter class in BubbaWP. Keep it behind a staging-only feature flag that defaults to disabled.
3. Refactor only as needed to let the existing validated booking service consume a normalized, trusted submission payload from either supported form provider. Do not duplicate capacity, pricing, attendee, consent or Stripe logic. Separate form-provider verification from canonical booking binding so the core no longer depends on a legacy provider submissions table for new Ninja Forms submissions.
4. Preserve form provenance and idempotency. Do not assume a Ninja Forms submission ID can safely be written into the existing booking_submission_id field until uniqueness/collision semantics and provider identity have been designed and tested. If a separate provider-aware mapping is required, propose it separately; do not alter schema implicitly. Keep Stripe test checkout disabled until its binding guard accepts the verified provider-aware record.
5. Resolve the parent email requirement consistently between form UI and server validation; preserve authenticated-account verification.
6. Test invalid/missing fields, stale form context, wrong session/date/places, guest/member permissions, forged or foreign child profile IDs, consent refusal, duplicate callbacks, concurrent final-place attempts, and failed payment hand-off.
7. Verify free booking flow and Stripe test flow on staging. Keep live payments disabled.
8. Compare leader/family views, confirmation emails, privacy export/erasure, cancellations and rollback.
9. Only after all gates pass, request explicit approval to retire the legacy runtime integration. Do not deactivate/uninstall the old form plugin or delete its records as part of this code migration.

## Safety constraints

- No live changes, no production writes, no live payment activation.
- No changes to existing tables or records, no data migration, no deletion and no plugin deactivation.
- Do not trust browser-supplied session, price, organiser or family profile IDs; validate server-side against authenticated ownership and the signed session context.
- Never create a booking from an enquiry-only submission.
- If the Ninja Forms payload or stored submission cannot be securely tied to the authenticated parent, stop the booking and return a safe error.
- Do not send child medical/emergency details in ordinary notification emails.

## Current status

**Whole-plugin migration approved; adapter not yet implemented.** Form ID 2 and visible labels have been confirmed from the staging builder. Field keys, internal types, saved payload, installed version and dynamic attendee-option behaviour remain unverified. No code changes, database writes or staging booking tests are claimed by this document.
