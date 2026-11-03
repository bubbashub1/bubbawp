# Bubba Hub 3.0 — Theme QA checklist

**Status:** Not yet executed on WordPress. Static checks are not browser QA.

## Build and install
- [ ] GitHub Actions theme checks pass for a known commit.
- [ ] Download `bubba-hub-theme.zip` from that run's artifact.
- [ ] Confirm ZIP root folder is `bubba-hub/`.
- [ ] Install in isolated/staging WordPress (not live).
- [ ] Activate without PHP fatal errors or database mutation.
- [ ] Confirm Appearance → Editor is available.

## Editor and templates
- [ ] Edit global colours, typography and spacing.
- [ ] Change logo and navigation.
- [ ] Edit header and footer without code.
- [ ] Verify homepage, page, single, archive, search and 404 templates.
- [ ] Insert/edit hero, discover and join patterns.
- [ ] Disable Bubba Hub Manager plugin; theme still loads gracefully.

## Browser / accessibility
- [ ] Mobile 360px and 390px: no horizontal scroll or overlapping navigation.
- [ ] Tablet 768px and desktop 1280px: layout, columns, cards and spacing.
- [ ] Keyboard-only menu navigation, visible focus and accessible controls.
- [ ] Check contrast, alt text, headings, zoom 200%, reduced motion.
- [ ] Check navigation overlay and touch target sizes.
- [ ] Check long headings, missing images, empty search results.
- [ ] Check that existing Elementor content has a safe migration plan (not an automatic guarantee).

## Data, URLs and deployment
- [ ] Confirm no writes to legacy `bh_` tables.
- [ ] Compare existing URLs with planned page routes.
- [ ] Document screenshots, issues, fixes and approval.
- [ ] Obtain explicit approval before any live theme switch.

## Evidence log
| Date | Commit | Environment | Tester | Result | Issues |
| --- | --- | --- | --- | --- | --- |
| — | — | — | — | Not tested | — |
