# Directory & Listings — implementation contract
Status: draft. Compare with live staging DDL and legacy schema before changing anything.

| Domain | Native source | Key fields / checks |
|---|---|---|
| Organiser | {prefix}organisers | id, name, slug, description, website_url, contact_email, created_at, updated_at |
| Activity | {prefix}activities | id, organiser_id, title, slug, description, status, category, age_min_months, age_max_months, price_from, is_free, booking_url, image_id |
| Venue | {prefix}venues | id, name, address_line1, town, postcode, region, latitude, longitude |
| Activity venue link | {prefix}activity_venues | activity_id, venue_id; unique pair |
| Session | {prefix}sessions | activity_id, venue_id, day_of_week, start_time, end_time, start_date, end_date, frequency, term_time_only, status |
| Session listing link | {prefix}session_listing_links | session_id, activity_id; unique pair |
| Legacy references | bh_activities, bh_venues, bh_sessions, bh_organisers | verified legacy IDs; no assumption that IDs match |

## Form and UI contracts
Native listing wizard: organiser selection with ownership verification; activity title/slug, age, price, category, location, sessions, term-time status, external booking URL. Validate venue ID and session ID server-side. No AI required.
Directory filters: category, age, day, price, region, town, session, term-time and free-only; postcode/near-me consent; sort and AJAX results planned. Calendar/planner view should use canonical sessions rather than duplicating schedules.

## Pages / shortcodes
/directory/ — [bubbahub_directory]; /activity/{slug}/ — [bubbahub_activity] or theme dynamic template; /organiser/{slug}/ — storefront; admin listing wizard — [bh_admin_activity_wizard]. Verify actual shortcode registrations and WP permalink rules.

## Acceptance gates
Only published entries in public search; search pagination and filters agree; free-only never includes paid; timezone and recurrence render consistently; title links canonical activity slug; no unverified organiser contact changes; external source verification recorded independently of existing Bubba Hub content.
