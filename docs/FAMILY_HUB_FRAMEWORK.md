# Family Hub 3.0 — implementation framework

Status: foundation only; not connected to production records.
Branch: feature/my-hub-framework

## Existing contracts and risks
- Legacy family routes: my-hub.html, account/, api/my-hub.php, api/auth.php.
- Legacy planner: planner.html, planner-pro.html, api/planner.php, api/planner-pro.php.
- Legacy database references include bh_users, bh_planner, bh_hidden_planner_days, bh_children and bh_activities. Table columns, user mappings and current permissions are **not verified**.
- The existing application uses custom authentication. Do not assume a WordPress user ID is equivalent to a bh_users ID.
- Never include legacy api/db.php in the new plugin: it has table-creation behaviour.

## Module boundaries
1. Family identity adapter: resolve current authenticated family ID through an explicitly audited integration; fail closed when unavailable.
2. Family repository: private profiles/preferences and child data; server-side ownership checks on every read and write.
3. Saved activity adapter: references canonical existing activity IDs, never clones listings.
4. Planner adapter: existing schedules and personal items; writes disabled until parity and permission testing.
5. Dashboard: plugin-owned accessible shortcode/block renderer with theme-compatible styling.
6. Settings: account privacy, notifications, export/delete requests, and consent handling.

## Delivery stages
- F0: safe UI scaffold and documented adapters, no database writes.
- F1: authenticated read-only dashboard on staging after identity mapping is verified.
- F2: saved activities and family profile CRUD with nonce, capability, ownership and validation tests.
- F3: planner/calendar parity, timezone and recurring session tests, conflict detection, printing.
- F4: recommendations and optional Pro entitlements without exposing child details.

## Acceptance gates
- No new bh_ tables, schema changes, or live data writes without approval.
- Signed-out visitors see a sign-in prompt, never family information.
- A family cannot access another family's data by changing request IDs.
- Children are private by default; store only necessary data, avoid public APIs.
- No direct frontend calls to privileged legacy endpoints.
- Existing planner and My Hub URLs continue working until migration verified.
- Mobile navigation, keyboard focus, contrast, screen-reader labels and empty states tested.
- Test fixtures and staging account journeys before any merge or deployment.

## Initial presentation
Shortcode [bubbahub_family_hub] renders a read-only UI foundation. It is intentionally not registered as a replacement for the live My Hub route. It does not claim to show actual saved records or child profiles.
