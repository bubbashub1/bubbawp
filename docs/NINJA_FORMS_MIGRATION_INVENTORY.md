# BubbaWP — whole-plugin Ninja Forms migration inventory

**Decision confirmed 2026-11-08:** Remove active Fluent Forms dependencies throughout BubbaWP and use Ninja Forms for form presentation/submission where a form is required. BubbaWP remains authoritative for domain records, permissions, validation, moderation, booking state, consent, notifications and audit history.

## Scope

This migration includes public and leader enquiries, Ask a Question / Support, family booking and consent, admin form configuration and diagnostics, and automated tests. Events Manager is not part of this architecture. FluentSMTP is a separate mail plugin and is not a Fluent Forms dependency.

## Source inventory from repository search

| Area | Current source / integration | Required replacement |
|---|---|---|
| Ask a Question | `modules/Support/support-ask-question.php` renders a Fluent Forms shortcode and stores responses in Fluent Forms | Render a configured Ninja Forms form; add a verified Ninja Forms submission adapter into the existing BubbaWP Ask Support workflow, with private contact handling and moderation preserved |
| Support enquiry inbox | `modules/Support/support-fluentforms-inbox.php` reads `{prefix}fluentform_submissions`, uses Fluent Forms form IDs, and tracks status in separate workflow tables | Read verified Ninja Forms submissions through a provider adapter; preserve workflow status/history and privacy boundaries; do not copy private answer bodies into duplicate tables |
| Support dashboard | `modules/Support/support-admin-dashboard.php` checks Fluent Forms tables/constants and links administrators to Fluent Forms | Show Ninja Forms-backed workflow metadata and safe admin links; retain capability checks and avoid exposing private answers |
| Booking and consent | `includes/class-bh-marketplace-settings.php` uses Fluent Forms hooks, stored-submission verification, Form 7/8 settings and provider-specific provenance | Add Ninja Forms integration for confirmed Form 2, normalize trusted input into BubbaWP booking services, preserve parent ownership, consent, idempotency, capacity and payment guards |
| Activity / leader enquiries | `includes/class-bh-activity-detail.php` checks for the Fluent Forms shortcode when deciding whether to show an enquiry link | Use Ninja Forms availability and configured form routing; never treat an enquiry as a booking |
| Admin diagnostics | `includes/class-bh-developments-admin.php` checks Fluent Forms shortcode availability | Report Ninja Forms integration readiness and actual configured forms |
| Bootstrap | `includes/class-bh-manager.php` loads the Fluent Forms-specific Support inbox module | Load a provider-neutral Support inbox module / Ninja Forms adapter; do not load a Fluent Forms runtime module |
| Tests | `tests/parent-paid-booking-fee-smoke.php` asserts Fluent Forms tables, hooks and settings | Replace provider-specific assertions with Ninja Forms contract, provider identity, ownership and workflow tests |
| Contracts and roadmap docs | Form contracts, support/booking maps, leader enquiry spec, foundation audit, SQL readiness matrix and roadmap contain Fluent Forms references | Update active contracts to Ninja Forms; preserve only clearly labelled historical migration/provenance notes where deleting them would falsify the audit trail |

## Data preservation

- Do not delete, truncate, rename or migrate Fluent Forms tables or submissions as part of code removal.
- Do not deactivate or uninstall Fluent Forms during this migration.
- Existing workflow rows that refer to legacy form/submission IDs must remain readable and auditable. Provider identity must be explicit; a numeric submission ID alone is not globally unique.
- New Ninja Forms submissions must be verified against the installed Ninja Forms API/payload and tied to the authenticated user or validated guest flow before BubbaWP writes any domain record.
- Keep legacy readers available only as an explicitly isolated compatibility path if required to view historical records; they must not be an active submission dependency for new workflows.

## Implementation order and gates

1. **Inventory and contracts:** enumerate all runtime references and workflows; confirm Ninja Forms forms/IDs, installed version, field keys/types and submission lifecycle from staging without writes.
2. **Provider-neutral submission contract:** define a trusted normalized submission envelope with provider, form ID, submission ID, actor identity, validated fields and timestamp. Do not conflate provider IDs.
3. **Support and enquiries:** implement Ninja Forms rendering and a server-side adapter; route Ask a Question and enquiry submissions into the existing BubbaWP moderation/workflow services. Verify privacy and access controls.
4. **Bookings and consent:** implement the Ninja Forms Form 2 adapter, dynamic parent-owned profile choices and explicit consent mapping; retain existing booking service and payment safeguards. Fix and test free-session booking before claiming parity.
5. **Admin, activity pages and bootstrap:** replace provider checks, settings, links and module loading.
6. **Tests and documentation:** replace active Fluent Forms assertions, add regression tests, and update all active contracts.
7. **Staging parity and cutover:** verify each journey end-to-end, compare existing records, test rollback and obtain explicit approval before removing legacy runtime compatibility or deactivating the old plugin.

## Current state

- Architecture decision confirmed; this inventory is a source audit, not implementation.
- Confirmed Ninja Forms Booking Form ID 2 and its visible labels are documented in `docs/EVENTS_MANAGER_NINJA_FORMS_MIGRATION.md`.
- Other Ninja Forms form IDs, field keys, payload shapes and support/enquiry mappings still require read-only staging verification.
- No database writes, schema changes, submission migrations, plugin deactivation, live changes or deployment are authorised or performed.
