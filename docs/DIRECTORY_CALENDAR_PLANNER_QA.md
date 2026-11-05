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
- Calendar now queries all filtered published activities independently of the 12-card List pagination, but returns at most 500 matching weekly session rows. The UI must disclose when the cap is reached.
- Only weekly sessions with `status='scheduled'` are included. Term-time is labelled but actual holiday exclusions, cancellations and dated exceptions are not resolved.
- The planner currently links to the existing Family Hub; planner entries deep-link to the activity timetable. It does not add dated sessions to `bh_planner` or make bookings.
- Do not wire new directory IDs to legacy family planner writes until the ID mapping, verified identity, and family action feature flags have been checked.

## Before merge
- Run `php -l` on the three edited PHP files.
- Verify the active staging activity template renders the `#timetable` section.
- Confirm session schema, status values, and dates with real staging records.
- Test logged-out, logged-in family, and leader views; verify no private data leaks.
- Check PR conflicts and CI; deploy to staging only after passing.
