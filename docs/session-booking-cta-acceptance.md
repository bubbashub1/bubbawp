# Session booking CTA — staging acceptance contract

## Current verified code path
- Leader session editor stores booking enablement, price and capacity in `bh3_session_booking_config`.
- Activity detail reads that option, but its `schedule[].booking_url` is derived from the external activity URL, not from Form #7.
- The leader calendar is explicitly a read-only staging preview.
- Enquiry Form #6 is a separate journey. Do not create bookings from enquiries.

## CTA rules
| Session state | Public action | Reservation/payment side effects |
| --- | --- | --- |
| Enquiry enabled, booking disabled | Enquire | None |
| Booking enabled, checkout unverified, enquiry enabled | Enquire + informational “Online booking coming soon” | None |
| Booking enabled, checkout unverified, enquiry disabled | Informational “Online booking coming soon” only | None |
| External booking URL validated | Book with organiser (external) | Managed by external provider |
| Verified internal checkout in future | Book now (session/occurrence-specific) | Only after capacity, ownership, consent, payment and confirmation gates pass |

## Before activating internal Book Now
1. Confirm actual Fluent Form #7 export keys, notifications, required consents and submission behavior.
2. Resolve a specific future session occurrence on the server, with dates and term-time rules.
3. Resolve the organiser from authoritative session/activity records; do not trust posted IDs, email, price or capacity.
4. Validate price in integer pence and available seats atomically.
5. Prevent duplicate submissions and concurrent overbooking; ensure cancellation and expiry release seats once.
6. Distinguish reservation status from Stripe payment status. Confirm only from verified payment or explicit approved leader action.
7. Provide guest verification without account inference from email.
8. Do not expose optional child demographics without explicit consent.
9. Confirm booking terms page, cancellation/refund wording and delivery of customer/leader notifications.
10. Test both the block-theme activity template and the legacy `[bubbahub_activity]` renderer on staging.

## Immediate staging checks
- [ ] Save a session with enquiry and booking toggles on; refresh editor and confirm persistence.
- [ ] Verify timetable continues to show Enquire and clearly communicates booking is not yet live.
- [ ] Verify booking-only sessions do not render a dead link or a misleading working checkout.
- [ ] Verify sessions with an external booking URL retain the valid external link.
- [ ] Verify mobile/keyboard layout and ensure no payment or booking records are created by simply opening the page.

No production rollout until evidence is attached.
