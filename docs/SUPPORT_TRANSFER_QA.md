# Support & Guidance transfer

Source: `bubbashub1/bubbaplugin` draft PR #773, branch `feature/support-resources-wp-foundation`.

Destination: `plugin/bubba-hub-manager/modules/Support/`. Six PHP modules were copied into this branch; the old standalone MU loader was deliberately **not** transferred because `bubbawp` uses `BH_Manager_3::boot()`.

## Activation
Modules are disabled by default. Only on `staging.bubbahub.co.uk`, set `define('BH3_ENABLE_SUPPORT', true);` in wp-config.php to enable them for testing. Do not add this constant to production.

When enabled, the plugin registers:
- `bh_resource` WordPress resource post type and `bh_support_topic` taxonomy.
- `[bh_support_hub]`, `[bh_support_resources]`, `[bh_support_articles]` shortcodes.
- BH Support admin dashboard, leader article review, Fluent Forms enquiry queue and workflow audit history.

## Important limitations
- The enquiry module currently attempts to create two WordPress-prefixed workflow tables on the first staging admin request by an administrator. Back up staging before enabling.
- This is a transfer, not a staging test or release approval. Confirm PHP syntax, Fluent Forms table compatibility, shortcode rendering, permissions, URL rewrites, existing article workflows and conflicts with other modules.
- The legacy leader submission API has **not** been moved; leader Pro entitlements and submission behaviour require a separate migration.
- No data is imported and existing private enquiry responses remain in Fluent Forms.
- The old PR #773 is superseded by this transfer for Bubba Hub 3.0. Do not merge it into the old repo's main branch.
