# Directory Calendar and Planner — staging QA

PR #142. Staging only; do not merge/deploy until validated.

## Navigation
- Directory defaults to **List** with Map, List, Calendar and My Planner choices; no combined Map & List view.
- Switching Map/List/Calendar hides the other content, preserves filters, and redraws Leaflet after Map is shown.
- Marker “Show listing” switches to List and focuses the correct card.
- Calendar week/month navigation changes the displayed dates; calendar activity links land on `/activity/<slug>/#timetable`.
- Activity timetable has a stable `id="timetable"`; booking and enquiry controls still work.
- Family Planner activity titles and Timetable & booking options links navigate to that anchor.
- Test mobile widths, keyboard navigation and screen reader labels.

## Data safety
- Calendar currently includes only the 12 filtered activities on the current directory page. Do not present it as a complete directory-wide calendar.
- Only weekly sessions with `status='scheduled'` are included. Term-time is labelled but actual holiday exclusions, cancellations and dated exceptions are not resolved.
- The planner currently links to the existing Family Hub; it does not add sessions to `bh_planner` or make bookings.
- Do not wire new directory IDs to legacy family planner writes until the ID mapping, verified identity, and family action feature flags have been checked.

## Before merge
- Run `php -l` on the three edited PHP files.
- Verify the active staging activity template renders the `#timetable` section.
- Confirm session schema, status values, and dates with real staging records.
- Test logged-out, logged-in family, and leader views; verify no private data leaks.
- Check PR conflicts and CI; deploy to staging only after passing.
