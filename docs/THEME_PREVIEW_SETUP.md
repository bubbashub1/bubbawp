# Theme preview setup — isolated WordPress only

**Purpose:** Review the current Bubba Hub 3.0 theme visually, without changing live or staging.

## Preconditions
- Use a disposable/isolated WordPress install, not the production site or its database.
- Download the `bubba-hub-theme` artifact from the latest successful **Theme checks and ZIP** GitHub Actions run; unzip the artifact, then install the contained `bubba-hub-theme.zip`.
- Back up the isolated site if it contains anything important. Do not import family, child, booking or organiser records for a theme review.

## Preview homepage
1. Appearance → Themes → Add New → Upload Theme → install and activate the ZIP.
2. Appearance → Editor → Templates → Front Page.
3. Review hero, category cards, featured placeholder, family and leader CTAs.
4. Add the real logo using Site Logo; it is not bundled in the theme.
5. Assign a navigation menu with links appropriate to the isolated test site. Existing live URLs should not be assumed to work on the test install.

## Preview activity detail design
1. Pages → Add New → title **Activity Design Preview**.
2. In the page settings, choose **Template → Activity Design Preview (Demo Only)**.
3. Publish the page on the isolated install and open its URL.
4. Inspect the gallery placeholder, facts row, session placeholder, map/contact placeholders and visually styled CTA labels.
5. **Important:** CTA labels are intentionally non-clickable; this preview is not connected to booking, saving, planner or activity records.
6. Do not use this template for real activity pages. The future Bubba Hub Manager plugin must supply real data, permissions and working actions.

## QA capture
- Screenshot desktop at 1280px, tablet 768px, mobile 390px and 360px.
- Check mobile menu, visible focus, 200% zoom, card overflow, contrast and readability.
- Verify editor changes appear on frontend and the theme works without the Manager plugin.
- Record any issues in `docs/THEME_QA.md` and get approval of design before integrating dynamic activity pages.

## Safety
- Do not switch the live theme or change Elementor templates.
- Do not create/edit `bh_` tables, bookings, family records or URLs.
- Static CI passing is **not** a substitute for actual WordPress activation and browser QA.
