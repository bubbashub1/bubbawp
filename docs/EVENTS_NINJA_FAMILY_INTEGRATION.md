# Events Manager + Ninja Forms + Family Hub: integration boundary

Status: design and read-only profile selector only. **No active booking integration yet.**

## Ownership of data
- BubbaWP retains activities, venues, timetables and family profiles in existing tables.
- Events Manager is a *candidate* booking/attendance owner; confirm its actual staging event, recurrence, booking and payment capabilities before connecting.
- Ninja Forms handles additional enquiries, consent and conditional information; its submissions must not independently create a second paid booking.
- Do not change existing Stripe checkout or payment webhooks until a verified staging replacement exists.

## First connector
`BH_Booking_Family_Profile_Reader` is loaded by the existing BubbaWP manager bootstrap and registers only Ninja Forms' documented `ninja_forms_render_options` filter. Configure a Ninja Forms Select field with the exact Field Key `bh_family_profile_id`; the adapter replaces that field's options with the logged-in WordPress account's owned child profiles plus a manual-entry (`guest`) option. It never exposes pregnancy/bump profiles as child attendees and leaves every other Ninja Forms field untouched.

`owned_options()` reads only the native `{prefix}bh_family_profiles` table and scopes the query to `get_current_user_id()`. It does not assume legacy IDs match WordPress IDs and fails closed if the table is missing. Before any booking writer stores an attendee, it must call `owns_child_profile($id)` again; the displayed Select options are not trusted as proof of ownership. This PR does not create a booking, payment, submission action or Events Manager reservation.

## Staging acceptance sequence
1. Inspect the Events Manager installed version and actual booking hooks, custom booking form fields and recurring-session behaviour.
2. Inspect Ninja Forms installed version, field population and submission hooks.
3. Confirm the active Family Hub profile storage and WordPress-user ownership mapping with non-sensitive fixture accounts.
4. Connect a single staging test event and one owned child, with a guest route.
5. Reject forged child IDs, guests requesting private child lists, duplicate submits and stale sessions.
6. Test consent, leader visibility limits, cancellations, confirmations, and a sandbox payment before switching ownership of any real bookings.

No live changes or database migration are authorised by this document.
