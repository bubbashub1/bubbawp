# Theme-first visual design brief — Bubba Hub 3.0

**Status:** Proposed for visual approval; not yet browser-tested.  
**Work items:** T0-02, T0-03, T0-04, T0-06  
**Scope:** Design and block-theme presentation only. No live changes.

## Approved continuity rules (3 November 2026)

- Retain Bubba Hub's recognisable existing colour identity and actual logo. The token hex values below remain **provisional** until compared against the current website; do not present them as verified brand colours.
- Keep the current activity page's useful structure: photo slider (up to ten images), key facts (price, age, location, sessions, term time, length), map then timetable, directions below, organiser/contact links, and claim/report/suggest near the bottom.
- Keep existing Save, Add to Planner, Visited, Compare and Book actions. **Book Now** is prominent only when a valid booking destination or future native booking capability exists; never display a dead booking button.
- On mobile, prioritise a readable activity title and primary action, with secondary actions clearly labelled and comfortably tappable. Do not obscure content with a fixed action bar without browser testing.
- Preserve directory search, postcode/Near Me, map/list/calendar and family/leader journeys. The theme styles these experiences; the Manager plugin owns their behaviour and data.
- Static design patterns must be labelled previews and must not impersonate working buttons or real sessions.

## Design direction

**Friendly, polished, local and app-like** rather than a generic directory template. Prioritise quick discovery for parents and easy access for class leaders. Mobile first, with a consistent system across all screens.

### Proposed design tokens (reviewable, not final)
| Token | Draft value | Use |
| --- | --- | --- |
| Forest | `#174F46` | Primary brand, headings and buttons |
| Mint | `#E7F2EC` | Soft backgrounds |
| Cream | `#FCFAF5` | Page background |
| Ink | `#20332F` | Body text |
| Muted | `#5E706A` | Secondary labels |
| Coral | `#E69A83` | Limited accent |
| Card radius | `16px` | Cards and panels |
| Content width | `760px` | Reading content |
| Wide width | `1200px` | Directory and dashboard layouts |

All colours must pass contrast checks in actual combinations; values may change. Use a system font stack initially, no external font requests.

## Layout contracts

### Global header
- Site logo + Bubba Hub wordmark on left.
- Primary navigation: Find Activities, Events, My Hub, For Class Leaders.
- Account/sign-in action should be consistent, but authentication remains plugin-owned.
- Mobile: accessible menu overlay, no crowding, sticky behaviour only after usability review.
- Avoid duplicating header implementations between homepage and directory.

### Homepage
1. Compact welcome hero: **Little adventures. Big memories.**
2. Search/discovery placement: design placeholder until plugin dynamic block is available; **do not fake live search**.
3. Popular categories in consistent cards.
4. Featured activities section: clearly identified placeholder until real listings are wired.
5. Family My Hub CTA and class leader CTA.
6. Event/Expo promotional pattern (optional placement, editable).
7. Footer with support, privacy, useful links and branding.

### Directory/listing
- One consistent card system for activities, events and businesses.
- Mobile: filter/search bar first, results cards below; map toggle only when plugin map exists.
- Desktop: aligned search/filter area, results grid/list and map region when enabled.
- Listing detail: compact image area, key facts, action bar, schedule, map, contacts, organiser, claim/report near bottom.
- Actual filtering, geolocation and schedules belong to plugin, not theme.

### Leader Portal and My Hub
- Share typography, colours, buttons, cards, form fields, nav spacing and responsive breakpoints.
- Different navigation can be plugin-provided within shared theme shell.
- Avoid theme-side account logic or exposing private child/booking information.

## Breakpoints and quality targets
- Test 360, 390, 768, 1024 and 1280 CSS px.
- No horizontal overflow at 200% zoom.
- Keyboard usable menus and dialogs; visible focus states.
- Aim for WCAG 2.2 AA in components and colour combinations.
- Avoid heavy JS and unnecessary third-party assets.
- Respect reduced motion; no auto-playing media.

## Site Editor patterns to add next
- `bubba-hub/category-grid`: editable categories with optional icons.
- `bubba-hub/featured-placeholder`: explicitly labelled demonstration content, never presented as real listings.
- `bubba-hub/event-promo`: event banner with editable date/venue/CTA.
- `bubba-hub/help-footer`: support and family/leader links.
- `bubba-hub/directory-shell`: layout-only placeholder for future dynamic directory block.

## Approval checkpoints

**Checkpoint A — foundation:** approve header, footer, colours, typography and mobile nav.  
**Checkpoint B — homepage:** approve hero, categories, CTAs and content density.  
**Checkpoint C — shared components:** approve cards, filters, forms, timetable and dashboard layout rules.  
**Checkpoint D — staging:** real WordPress install, editor usability, mobile screenshots, keyboard/contrast testing and no data changes.

## Next safe tasks while user is away

1. Confirm CI build and artifact exist (completed for run 41639189965).
2. Finish reusable block patterns and presentation-only styles.
3. Add static tests for pattern headers, template references and build completeness.
4. Prepare screenshot checklist and exact steps for staging QA.
5. Do **not** activate the theme on live, change WP menus, migrate Elementor content or write to `bh_` tables.

## Open questions for later visual review
- Confirm actual logo display and brand colours against the current site.
- Decide whether desktop navigation should be sticky.
- Choose between card-first and map-first directory layout.
- Confirm homepage hero height and category emphasis.

**Important:** Theme ZIP passing static checks does not prove it looks right, that the Site Editor works, or that existing Elementor content renders correctly.
