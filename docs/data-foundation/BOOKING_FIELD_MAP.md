# Bookings & Consent — field-by-field implementation contract
Status: draft, not staging verified. Confirm all DDL and Fluent Forms export keys before migration.

## Authority
- Booking: `{prefix}bh3_bookings`; attendee: `{prefix}bh3_booking_attendees`; consent: `{prefix}bh3_booking_consents`.
- Legacy `bh_booking_sources`, `bh_booking_slots`, `bh_booking_reservations`, `bh_consent_profiles`, `bh_booking_consent_snapshots` remain source/reference, not automatic second writers.
- `{prefix}bh3_stripe_events` for payment webhook idempotency.
- Do not infer enrolled/confirmed from paid; enquiry is not booking.

| Concept | Native field or location | Legacy/source | Decision |
|---|---|---|---|
| Booking identity | bh3_bookings.id, public_reference | bh_bookings.id / bh_booking_reservations.id | Keep verified external-to-native mapping, never equate IDs |
| Session/occurrence | session_id, occurrence_date | bh_booking_slots.starts_at, bh_sessions | Choose canonical occurrence and timezone before sync |
| Activity/organiser | activity_id, organiser_id | bh_activities, bh_organisers | Server-side resolve and verify ownership |
| Customer | customer_user_id nullable | bh_users / WP users | Guest booking allowed; never match accounts by email alone |
| Form provenance | booking_form_id, booking_submission_id | Fluent Forms submissions | Store form and submission IDs; avoid full payload duplication |
| Quantity | places | bh_booking_reservations.quantity | Validate capacity atomically and prevent double reservation |
| Amount | currency, unit_price_pence, gross_pence, commission_pence, leader_before_fees_pence, commission_rate_percent | legacy amount/price | Integer pence, server-calculated; do not trust posted price |
| Payment | payment_status, stripe_checkout_session_id, stripe_payment_intent_id | Stripe | Keep distinct from booking status |
| Booking lifecycle | **proposed** booking_status, status_updated_at | bh_booking_reservations.status | Confirm existing implementation; draft in 01-booking-lifecycle.DRAFT.sql |
| Expiry | reservation_expires_at | booking slot expiry policy | Release seats idempotently |
| Attendee | bh3_booking_attendees: attendee_number, family_profile_id, attendee_type, attendee_name, gender, year_of_birth | bh_children, bh_bumps | Confirm ownership; optional demographic sharing only with consent |
| Consent | bh3_booking_consents: consent_key, consent_version, response, response_at, actor_user_id, source | bh_booking_consent_snapshots | Immutable evidence/version; decide retention and sensitive data boundary |
| Booking status history | proposed bhs_booking_status_history | existing logs TBD | Add only if existing history insufficient |
| Notification dedupe | proposed bhs_message_delivery_log | existing mail logs TBD | Add only after existing delivery audit |

## Form 6 — Interested in Joining (enquiry)
| Logical field | Expected type | Handling |
|---|---|---|
| name | text, required | Normalise and validate |
| email | email, required | Validate; never infer account ownership |
| session_ids | multiselect | Validate against activity and published session IDs |
| family_profile_ids | multiselect, optional | Resolve only profiles belonging to current WP user; allow bump |
| message | textarea, optional | Sanitise and minimise retention |
| share_gender_year | unchecked-by-default consent | Only disclose demographic fields if explicitly checked |
| enquiry_acknowledgement | required checkbox | Explicitly not a confirmed booking |
| activity_id | server-derived | Never trust hidden client value without validation |
| recipient | server-derived verified leader email | Do not allow user-supplied recipient |

**Submission destination:** Fluent Forms enquiry, verified organiser notification, and optional dedicated enquiry record only after canonical ownership chosen. Never create bh3_bookings automatically.

## Form 7 — Booking consent
| Logical field | Expected type | Handling |
|---|---|---|
| booking_reference | server-validated reference | Lookup authoritative booking |
| attendee_ids | server-validated | Verify booking ownership and attendee relationship |
| consent_key / consent_version | explicit per consent | Store immutable versioned response |
| response | unchecked by default | Consent is specific, freely given and withdrawable where applicable |
| response_at / actor | server timestamp and authenticated actor or guest proof | Audit without storing excessive personal information |
| optional sensitive details | restricted fields | Need access controls, purpose, retention and secure delivery review |

**Export key names are not confirmed.** Do not implement positional mappings until actual Form 6 and Form 7 JSON is reviewed.

## Acceptance tests
Guest enquiry cannot create booking; session tampering rejected; family profile ownership enforced; no demographic disclosure without opt-in; expired reservations release capacity once; payment webhook replay safe; cancelled booking does not stay enrolled; consent snapshots versioned; no sensitive data in leader emails by default.

## Staging verification checklist
SHOW CREATE TABLE for all native booking tables; compare existing columns, indexes and FKs; export Forms 6/7 and notification rules; check session time zone and source; inspect existing booking status transitions; backup before approved migration.
