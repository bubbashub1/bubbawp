# Events & Expo — implementation contract
Status: draft. Reuse legacy Expo structures; generalisation to multiple events is not approved.

| Domain | Legacy source | Field contract |
|---|---|---|
| Reservations | bh_expo_reservations | visitor/contact, slot, capacity, status |
| Tickets | bh_expo_tickets | unique ticket/QR, reservation linkage, check-in state |
| Exhibitors | bh_expo_exhibitors | organiser, package, approval, table |
| Tables | bh_expo_tables | floor location, assignment, capacity |
| QR bookings | bh_expo_booking_qrs | token, reservation, redeemed status |
| Check-in | bh_expo_family_checkins | ticket/visitor, check-in timestamp |
| Giveaway | bh_expo_giveaway_claims | prize, claimant, redemption |
| Raffle | bh_expo_raffle_prizes | prize, winner/claim state |
| Sponsors | wp_bh_sponsor_receipts | sponsor payment and receipt references |
| Volunteers | bh_volunteer_assignments | role, slot, contact |

Planned generic event identity and metadata is in 03-events-generalisation.DRAFT.sql; do not execute without full legacy schema comparison.

Visitor, exhibitor and sponsor forms are planned, not assigned Fluent Form IDs. Candidate routes /events/ and /events/{event-slug}/, with organiser-facing event templates. Do not invent event shortcodes before code review.

Acceptance: one QR per permitted identity, idempotent check-in, timed capacity enforcement, role-separated exhibitor/admin access, refund/cancellation rules, event-specific reporting and retention, no collision with existing Expo data.
