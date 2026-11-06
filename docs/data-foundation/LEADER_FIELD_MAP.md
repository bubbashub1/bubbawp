# Leader Portal — field-by-field implementation contract
Status: draft, not staging verified.

| Concept | Native table / field | Legacy source | Decision |
|---|---|---|---|
| Organisation identity | organisers.id, name, slug | bh_organisers.id, organisation_name, slug | Verified mapping and uniqueness |
| WP ownership | organiser-to-WP-user relationship (verify implementation) | bh_organisers.user_id | Server-side authorisation for every edit |
| Contact | organisers.contact_email, website_url | bh_organisers.email, website | Verified email used for enquiries; no client-provided destination |
| Phone/status | **verify missing** in native organisers | bh_organisers.phone, status | Draft additive fields only if truly absent |
| Listings | activities.organiser_id, title, slug, status, category, price_from, image_id | bh_activities | Owner-scoped edit and publish review |
| Venues | venues.id/name/address_line1/town/postcode/region/lat/long; activity_venues join | bh_venues.activity_id | Deduplicate venues, verify access |
| Timetable | sessions.activity_id, venue_id, day_of_week, start_date, end_date, frequency, term_time_only, status | bh_sessions | Session edits cannot invalidate existing bookings |
| Enquiries | Fluent Forms 6 submissions / leader inbox | bh_leader_messages | Do not conflate with bookings |
| Bookings | bh3_bookings, bh3_booking_attendees, bh3_booking_consents | bh_booking_reservations | Leader can only access own organisation's records |
| Payout settings | existing payout module/settings (verify storage) | no safe legacy assumption | Do not store bank credentials in plain WP options |
| FAQs/expertise | legacy bh_leader_faqs, bh_leader_expertise | legacy | Choose canonical owner and permissions |

## Routes and shortcodes (verify actual page placements)
| Page | Planned feature | Shortcode |
|---|---|---|
| /leader/ | Dashboard | `[bh_leader_dashboard]` |
| /leader/about/ | Leader onboarding | Verify |
| /leader/new-activity/ | Activity wizard | Verify |
| /leader/bookings/ | Booking dashboard/calendar | `[bh_leader_booking_dashboard]` |
| /leader/messages/ | Enquiries and replies | Verify |
| /leader/venues/ | Venue management | `[bh_leader_venues]` (verify) |
| /leader/business/ | Storefront | `[bh_leader_business]` (verify) |
| /leader/payment/ | Payout preferences | `[bh_leader_payout_settings]` |

## Forms
Fluent Form 6 enquiry, Form 7 consent for booking; native activity/venue/business editors. Server-side leader destination and ownership required. Keep front-end leader portal; reserve WP admin for team.

## Acceptance tests
Leader A cannot view/edit Leader B's bookings, child details or venue settings; verified email receives enquiry; leader edits have audit trail; timetable changes respect reservations; payout data is access controlled; no sensitive child medical information in ordinary email notifications.

## Staging gate
Verify actual organiser ownership mapping, payout storage, shortcode registrations, Fluent Forms notifications, capability checks and DDL before implementing changes.
