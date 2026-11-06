# Fluent Form #7 — verified export contract (2026-11-06)

Source: user-provided `fluentform-export-forms-1-06-11-2026.json`. Export contains one published form: ID **7**, “Bubba Hub – Session Booking & Payment (Prototype)”. This document records export configuration only; it does not prove staging submission, notification or payment behavior.

| Field key | Element | Required in export | Server-side integration rule |
|---|---|---|---|
| `parent_name` | input_text | Yes | Validate and sanitise |
| `parent_email` | input_email | Yes | Validate; do not infer user ownership |
| `parent_phone` | input_text | No | Optional; minimise retention |
| `activity_id` | input_hidden | No | Untrusted; resolve against published activity |
| `session_id` | input_hidden | No | Untrusted; validate ownership and occurrence |
| `organiser_id` | input_hidden | No | Never trust; derive server-side |
| `booking_reference` | input_hidden | No | Never trust; generate server-side |
| `session_selection` | select | Yes | **advanced_options empty**; populate only from server-authorised occurrences |
| `places` | input_numeric | Yes | Validate positive integer and atomic capacity |
| `family_profile_ids` | select | No | **advanced_options empty**; restrict to logged-in user's own profiles, including bump |
| `child_details` | textarea | No | Free text can contain optional demographic data; do not forward or retain unnecessarily |
| `booking_notes` | textarea | No | Sanitize and restrict access |
| `share_child_details_consent` | input_checkbox | No | Explicit opt-in, checkbox value `yes`; absence means no sharing |
| `booking_terms_consent` | input_checkbox | Yes | Value `yes`; require terms page/version and server-side validation |

Other observed export settings:
- `has_payment` is **`0`**. No checkout or payment capability is evidenced.
- Submit button text: **Continue booking**.
- `form_meta` and `metas` arrays are empty. Notification routing and webhook behavior are not evidenced by this export.
- No occurrence-date input is present. Weekly session ID alone is insufficient for a specific reservation.
- No explicit distinction between guest and logged-in customer is represented by a form field; derive on server.

## Blocking gates before accepting a booking
1. Implement verified occurrence selection and populate session/profile options without trusting hidden IDs.
2. Publish and legally review `/legal/bookings/`, including versioned acceptance.
3. Ensure unchecked child-sharing consent prevents disclosure of gender/year of birth, including within free-text guest details.
4. Verify submission ownership, leader email routing, capacity concurrency, idempotency, status transitions and Stripe integration.
5. Keep booking form in prototype mode and **do not** mark submissions paid/confirmed or reserve seats until these gates pass.

## Recommended immediate UI behavior
- Preserve Form #6 enquiry as the usable CTA where configured.
- Display “Online booking coming soon” as informational text, not an active payment/booking button.
- Do not dynamically embed Form #7 on public activity pages yet.
