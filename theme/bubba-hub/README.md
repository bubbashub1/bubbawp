# Bubba Hub native WordPress block theme

**Status:** Early design scaffold; **not installed or staging-tested**.

## Scope
Native WordPress Site Editor, editable templates, patterns, global styles, responsive layout and accessible navigation. No Astra, Elementor, AI, premium plugins or paid services required.

## Current templates
Home, index, page, single, archive, search and 404. Header and footer template parts. Hero, discovery and family/leader CTA patterns.

## Test installation (isolated WordPress only)
1. Package the contents of `theme/bubba-hub/` as a ZIP with a single top-level `bubba-hub/` folder.
2. In an **isolated/staging** WordPress install, go to Appearance → Themes → Add New → Upload Theme.
3. Activate only after backup and explicit staging approval; review Appearance → Editor.
4. Verify desktop/mobile navigation, content templates, editor settings, keyboard focus and plugin-disabled behaviour.
5. Record test evidence in the master checklist. **Do not activate on live.**

## Known limitations
- No actual directory search, bookings or My Hub integration yet; homepage uses placeholder copy and links.
- No logo asset bundled; set the site logo in WordPress after installation.
- Visual design has not been approved and theme compatibility has not been tested.
- Links such as `/directory/`, `/leader/` and `/my-hub/` are placeholders pending URL compatibility audit.


## Editor style parity

The theme registers `assets/css/site.css` for both the public site and the block editor via `add_editor_style()`. This is presentation-only CSS; the visual appearance still needs real Site Editor/browser QA. Navigation links are deliberately not auto-created or redirected during activation.

## Header and mobile checks

1. Add the actual Bubba Hub logo via Appearance → Editor → Header → Site Logo.
2. Create or assign the desired WordPress navigation menu; verify each destination against the existing URL inventory.
3. Preview at 360px, 390px, 768px and desktop widths; check menu overlay, logo sizing, keyboard navigation and zoom.
4. Do not switch the live site's theme until Elementor and existing page routes have a documented migration path.
