# Bubba Hub 3.0 — Repository Architecture Plan

**Status:** Approved architecture direction; implementation not started  
**Decision:** ADR-013 — Fully custom native WordPress block theme (Approach A), approved 2026-11-03  
**Repository:** `bubbashub1/bubbawp`  
**Master tracker:** [MASTER_CHECKLIST.md](../MASTER_CHECKLIST.md)

## 1. Product boundaries

The repository will produce **two separately installable ZIP files**:

1. **Bubba Hub Theme** (`theme/bubba-hub/`) — WordPress block theme; owns appearance, templates, template parts, block patterns, global styles, responsive navigation and presentation. Editable using the native WordPress Site Editor.
2. **Bubba Hub Manager** (`plugin/bubba-hub-manager/`) — WordPress plugin; owns domain logic, directory search, activities, organisers, venues, timetables, bookings, My Hub, leader/admin workflows, payments policy, REST endpoints, capabilities, data adapters and **dynamic blocks**.

**Separation rule:** Switching or disabling the theme must not erase or change business data. Switching or disabling the plugin must not make the theme fatally error; dynamic features may show a graceful unavailable state. Theme activation must not create or mutate existing `bh_` tables.

## 2. Proposed repository structure

```text
bubbawp/
├── MASTER_CHECKLIST.md
├── README.md                          # to be created
├── docs/
│   ├── ARCHITECTURE.md                # this document
│   ├── DECISIONS/                    # ADRs as needed
│   ├── LEGACY_COMPATIBILITY.md        # after read-only audit
│   ├── DATA_MODEL.md                  # after schema inventory
│   └── QA_RELEASE.md                  # staging/rollback criteria
├── theme/
│   └── bubba-hub/
│       ├── style.css                  # theme metadata
│       ├── theme.json                 # editor styles and tokens
│       ├── functions.php              # theme setup, presentation only
│       ├── templates/
│       │   ├── index.html
│       │   ├── front-page.html
│       │   ├── page.html
│       │   ├── single.html
│       │   ├── archive.html
│       │   ├── search.html
│       │   └── 404.html
│       ├── parts/
│       │   ├── header.html
│       │   └── footer.html
│       ├── patterns/
│       ├── assets/
│       └── styles/                    # optional style variations
├── plugin/
│   └── bubba-hub-manager/
│       ├── bubba-hub-manager.php
│       ├── includes/
│       │   ├── Core/
│       │   ├── Data/
│       │   ├── Security/
│       │   └── REST/
│       ├── modules/
│       │   ├── Directory/
│       │   ├── Activities/
│       │   ├── Venues/
│       │   ├── Organisers/
│       │   ├── Timetables/
│       │   ├── Bookings/
│       │   ├── Families/
│       │   ├── LittleExplorers/
│       │   ├── Payments/
│       │   ├── Events/
│       │   └── Communications/
│       ├── blocks/                    # native Gutenberg dynamic blocks
│       ├── admin/
│       ├── public/
│       └── assets/
├── tests/
└── .github/workflows/                 # CI and ZIP packaging
```

This is a **target layout**, not a claim that these files have been built.

## 3. Theme design contract

- **WordPress-native:** use `theme.json` global settings/styles, HTML block templates, template parts and patterns. No Astra, Elementor or premium builder required for the new theme.
- **Editable:** site owner can change header, footer, homepage layouts, typography, colour palette and page sections through the Site Editor. Document which dynamic areas are controlled by plugin blocks.
- **Visual system:** shared design tokens for green/neutral palette, readable typography, spacing, rounded cards, focus states, touch targets, responsive grids and navigation. Exact palette and brand assets will be approved later.
- **Templates:** homepage, pages, single posts, archives, search, 404, and plugin-powered directory/activity/organiser/venue/events entry points.
- **Patterns:** homepage hero, discovery search placement, categories, featured activities, family CTA, leader CTA, newsletter and event promotion.
- **Accessibility:** semantic landmarks, keyboard navigation, visible focus, WCAG-aligned contrast, reduced-motion support and mobile usability.
- **Graceful fallback:** theme remains functional if plugin is inactive; no direct SQL or payment logic in the theme.
- **No paid dependencies or AI services.** Review any free map/geocoding dependency for usage limits and privacy.

## 4. Plugin ↔ theme interface

- Plugin registers namespaced Gutenberg blocks, e.g. `bubbahub/directory-search`, `bubbahub/activity-grid`, `bubbahub/activity-detail`, `bubbahub/organiser-profile`, `bubbahub/booking-panel`, `bubbahub/family-planner`.
- Blocks receive validated attributes and render through plugin services and repositories; server-rendered output is preferred for SEO-relevant directory content.
- Theme templates/patterns **place** these blocks; the theme does not own data queries, booking calculations, user authorisation or commission rules.
- API endpoints and role checks live in the plugin, not in theme PHP.
- Keep HTML/CSS contracts small and documented to allow future independent theme updates.
- Block availability and display behaviour must be tested with plugin enabled/disabled.

## 5. Existing-site transition

**Existing live site:** Astra + Elementor + standalone PHP/HTML and existing `bh_` data.  
**Target:** Custom block theme + Bubba Hub Manager plugin.

1. Complete read-only inventory of WordPress pages/posts, Elementor templates/widgets, theme customisations, menus and current PHP/API behaviour.
2. Recreate representative public layouts in isolated/staging WordPress using native block templates and patterns.
3. Add plugin dynamic blocks backed initially by fixtures or read-only legacy adapters.
4. Verify content parity, permalinks, SEO, mobile design, accessibility, performance and authentication.
5. Plan migration of Elementor-authored content without silently dropping layouts or content. Elementor can remain installed on the legacy site during the transition; it is not a requirement of the new theme.
6. Test coexistence and rollback. **No live theme switch, deployment, data rewrite or table alteration without explicit approval.**

## 6. Data and safety boundaries

- Preserve **all** existing `bh_` table structures, keys, IDs and relationships; no schema changes without explicit approval.
- Preserve PHP APIs, authentication and URL behaviour until replacement parity is verified.
- Do not clone master listings into an alternative directory-plugin data store.
- Any new booking/payment tables require a separate schema proposal and approval.
- Keep child information private by default; minimise data and enforce server-side permissions.
- Development stage has **no AI or new paid service dependencies**.
- Real payments, payout services and associated fees are deferred; booking/payment logic can be simulated using test fixtures.

## 7. Planned release outputs

- `bubba-hub-theme-<version>.zip` — standalone theme.
- `bubba-hub-manager-<version>.zip` — standalone plugin.
- Matching release notes, tested WordPress/PHP compatibility, checksum, staging QA record and rollback instructions.

**Current milestone:** Initial theme scaffold committed (theme.json, templates, parts and patterns); visual review, theme ZIP, activation, CI and staging QA are **not yet completed**.

## 8. Checklist links

- Governance: **G-11** (theme choice approved), **G-12** (theme design specification)
- Foundation: **F-01–F-08**, **T-01–T-07**
- Directory blocks: **D-07**
- Release: **Z-01–Z-07**

**Approved revised sequence (ADR-016):** Theme-first Stage T0 (design baseline) → Stage 0 governance/legacy audit → plugin foundation → feature modules. The initial theme scaffold is in progress; next action is visual review and technical validation, not a live deployment.
