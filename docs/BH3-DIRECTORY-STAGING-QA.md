# Bubba Hub 3.0 — staging directory acceptance checks

Scope: **staging.bubbahub.co.uk only**. Do not run these checks against the live legacy directory. Deploy the merged `bubbawp` main branch through the staging deployer first.

## Before starting
- Confirm the staging WordPress plugin is updated to the intended GitHub commit.
- In WordPress admin, open **Bubba Hub 3 → Directory Builder**. Save the settings once and confirm the success notice.
- Confirm a published activity has a linked venue with valid latitude and longitude, and another published activity has no venue coordinates.
- Use a desktop browser and an Android/mobile-sized browser. Open the browser console to check for errors.

## Directory search
- [ ] Open the page with `[bubbahub_directory]`; confirm the page renders without a PHP error.
- [ ] Keyword, category, town and region filters return appropriate published activities.
- [ ] Age and day filters use the stored activity age range and weekly sessions.
- [ ] Free-only and term-time filters work; clear filters resets them.
- [ ] Postcode prefix searches venue postcodes; it is **not** a distance/radius search yet.
- [ ] Empty results display a friendly message; no draft activity appears.
- [ ] Next/Previous pagination retains the current filters.
- [ ] Hide a search field in Directory Builder, save, and confirm it disappears from the form.

## Map and activity cards
- [ ] Mapped venues show OpenStreetMap tiles, attribution and markers.
- [ ] Nearby markers cluster; zooming in reveals individual markers.
- [ ] Open a marker popup and select **Show listing**; the correct activity card is highlighted and scrolled into view.
- [ ] In **Map only**, selecting **Show listing** switches back to the combined view and reveals the card.
- [ ] **Map & list**, **Map only** and **List only** all work on desktop and mobile.
- [ ] The map redraws correctly after switching back from List only.
- [ ] Activities with missing coordinates remain in the list without breaking the map.
- [ ] With no mapped venues on a result page, the directory list still works and no map-only switcher is shown.
- [ ] No unexpected horizontal scrolling at 360px width; controls are tappable.

## Activity detail and visual builder
- [ ] Open an activity via **View activity details** (requires a staging `activity-preview` page with `[bubbahub_activity]`).
- [ ] The linked venue map displays all valid venue pins; the timetable and venue address cards remain available.
- [ ] In Directory Builder, change section order and visibility and confirm the schematic preview updates immediately.
- [ ] Save and reload the builder; confirm section order and visibility persist.
- [ ] Check the activity page reflects the saved order and hidden sections.
- [ ] Restore intended defaults after testing.

## Known limits (not defects)
- The directory map shows venues for the **current 12 results only**, not all search results.
- Postcode is prefix filtering, not Near Me/radius geocoding.
- Gallery and accessibility sections are reserved in the builder, but their public display blocks are not implemented.
- The builder preview is schematic rather than an actual activity rendering.
- Do not claim production-ready until staging browser checks, PHP lint and WordPress integration testing pass.

## Record the outcome
For each failed check, record: test step, activity ID or URL, browser/device, screenshot or console message, and GitHub commit SHA. Do not deploy to live until regressions are resolved.
