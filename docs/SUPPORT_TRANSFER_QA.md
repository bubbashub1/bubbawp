# Support & Guidance transfer

Source: `bubbashub1/bubbaplugin` draft PR #773, branch `feature/support-resources-wp-foundation`.

Destination: `plugin/bubba-hub-manager/modules/Support/`. Six initial PHP modules were transferred; a seventh Website Help module was added subsequently; the old standalone MU loader was deliberately **not** transferred because `bubbawp` uses `BH_Manager_3::boot()`.

## Activation
Modules are disabled by default. Only on `staging.bubbahub.co.uk`, set `define('BH3_ENABLE_SUPPORT', true);` in wp-config.php to enable them for testing. Do not add this constant to production.

When enabled, the plugin registers:
- `bh_resource` WordPress resource post type and `bh_support_topic` taxonomy.
- `[bh_support_hub]`, `[bh_support_resources]`, `[bh_support_articles]`, `[bh_website_help]` shortcodes.
- BH Support admin dashboard, leader article review, Fluent Forms enquiry queue and workflow audit history.

## Important limitations
- The enquiry module does **not** create workflow tables automatically. Back up staging, then open **BH Support → Bubba Hub Enquiries**, type `INSTALL` and submit the explicit administrator-only setup form. Installation refuses to run over existing workflow tables and is blocked off staging.
- Status changes and audit events are written in one transaction; both workflow tables must use InnoDB. Validate rollback and uncertain commit handling on staging.
- This is a transfer, not a staging test or release approval. Confirm PHP syntax, Fluent Forms table compatibility, shortcode rendering, permissions, URL rewrites, existing article workflows and conflicts with other modules.
- The legacy leader submission API has **not** been moved; leader Pro entitlements and submission behaviour require a separate migration.
- No data is imported and existing private enquiry responses remain in Fluent Forms.
- The old PR #773 is superseded by this transfer for Bubba Hub 3.0. Do not merge it into the old repo's main branch.

## WordPress shortcodes (staging only)

Add these in a **Shortcode** block in the WordPress editor, not in a Code/HTML block:

| Shortcode | Output |
| --- | --- |
| `[bh_support_hub]` | Combined landing page, resources, articles and website-help link |
| `[bh_support_resources]` | Resource search, topic filter and paginated cards |
| `[bh_support_articles]` | Published support-tagged WordPress articles and filters |
| `[bh_website_help]` | Public Website Help FAQ accordions (family accounts, leaders, troubleshooting) |
| `[bh_support_resources limit="6"]` | Six resources per page |
| `[bh_support_articles limit="6"]` | Six articles per page |

Recommended published pages and shortcode blocks:

| Page | Shortcode |
| --- | --- |
| `/guidance/` | `[bh_support_hub]` |
| `/guidance/resources/` | `[bh_support_resources]` |
| `/guidance/blog/` | `[bh_support_articles]` |
| `/guidance/help/` | `[bh_website_help]` |

The landing page shows a preview of resources and articles, with navigation cards pointing to the dedicated published pages. If one is unpublished or missing, the card falls back to the corresponding section on the landing page. The Website Help card falls back to its on-page help section if no published help page is found. Do not put all four shortcodes on the landing page; that would duplicate content.

### Staging checks before merge
1. Confirm GitHub PHP validation and Support syntax checks pass.
2. Enable the support flag on staging only; verify all four shortcodes render with no content imported.
3. Confirm shortcode filter labels work with multiple instances on one page.
4. Check empty states, pagination, keyboard navigation and mobile widths.
5. Verify **BH Support** appears for administrators and does not expose private enquiries to non-administrators.
6. Back up staging, explicitly install workflow tables and verify their engine is InnoDB.
7. Configure a Fluent Forms test form ID, submit a dummy enquiry and test status transitions plus audit history.
8. Confirm live remains unchanged; only merge when tests pass.

### Four-page acceptance test
1. Publish the four pages with the exact parent/child slugs shown above. Check the WordPress page status is **Published**.
2. On `/guidance/`, click each of the three cards. Confirm Resources, Blog and Website Help open their respective pages rather than on-page anchors.
3. On Resources and Blog, submit keyword/topic filters and clear them; confirm an empty result has a clear-filters link.
4. On `/guidance/help/`, expand and collapse each FAQ with mouse and keyboard; confirm no personal data is displayed or collected.
5. Check all four pages at mobile width and on desktop, and verify links remain readable and keyboard focus is visible.
6. Confirm only public published content appears. Verify the support admin screens remain restricted to authorised users.
7. Recheck that the Support flag is not enabled on the live site. No data import is part of this release.
