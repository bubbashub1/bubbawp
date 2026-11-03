# Bubba Hub Manager 3.0 — foundation

**Version 0.1.0 — not a functional replacement for the live Bubba Hub Manager.**

The plugin is intentionally inert except for a capability-protected WordPress admin status page under **Tools → Bubba Hub 3.0**.

## Safety
- No activation/deactivation/uninstall callbacks.
- No schema creation, updates, deletion, SQL or external API calls.
- No authentication, REST routes, roles, rewrites or cron registration.
- Does not connect to or change existing `bh_` tables.
- No front-end functionality yet; do not remove the existing production system.
- Avoid activating alongside a future version with overlapping class names; namespace the next modules.

## Installation
Use only on an isolated staging WordPress install with a backup. Package the `bubba-hub-manager/` directory as the ZIP root. Upload through Plugins → Add New → Upload Plugin. Verify activation and the Tools status page. Check PHP logs and ensure legacy behavior is unchanged. **No live deployment is authorised.**

## Next gates
1. Audit `bubbaplugin` legacy APIs and schema read-only.
2. Define compatibility interfaces and permission checks.
3. Add read-only directory adapter behind a disabled-by-default feature flag.
4. Run isolated WordPress activation/deactivation and integration QA.
