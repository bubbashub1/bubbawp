# Booking system — main merge readiness (5 November 2026)

## Scope and deployment intent

This branch is 73 commits ahead of main at review, touching eight files. It contains staging-only marketplace and booking experiments, schema additions, leader timetable/calendar changes and activity detail adjustments. **Merging is not equivalent to activating payments.** No production booking rollout is authorised.

## Mandatory pre-merge checks

- [ ] PHP lint all changed PHP files, especially the new ~2,500-line `class-bh-marketplace-settings.php`.
- [ ] Check WordPress load on staging with booking flags absent, and ensure no fatal errors, notices or broken shortcodes.
- [ ] Review the eight-file diff for unrelated changes and ensure existing leader timetable and activity pages retain behaviour.
- [ ] Verify schema version 0.7.0 upgrades existing installs using `dbDelta` without losing data; confirm indexes and table prefix.
- [ ] Verify Fluent Forms Free Form 7 field names and submission ownership assumptions against a real staging submission. The mapping option `bh3_booking_form_field_map` must be configured before binding.
- [ ] Confirm parent-owned profile selection, guest fields and sharing consent with representative test data; guest Checkout is not implemented.
- [ ] Review SQL transaction assumptions (tables must be InnoDB), retries and temporary capacity holds.
- [ ] Confirm verified leader email metadata is written by an actual verification process before sending any child details. No notifications are wired yet.
- [ ] Run Stripe test-mode payment success, failure, duplicate webhook, expiry, refund and reconciliation cases before enabling Checkout.
- [ ] Resolve fee allocation: destination charges currently charge Stripe processing fees to the platform; leader-paid processing fees are **not** implemented.
- [ ] Review access controls, data retention, privacy notices, child data minimisation and future consent versioning.

## Safe merge and staged activation

1. Keep this PR in draft until PHP lint, code review and basic regression checks pass.
2. Merge to `main` only after confirming that the staging-only registration guard works and all booking feature flags remain off by default.
3. Deploy the merged plugin to **staging**, not the live site, and smoke-test WordPress admin, leader pages, My Hub, directory and shortcodes.
4. Run the schema migration in staging, confirm tables and backups, then separately configure Form 7 mapping.
5. Enable booking flags **one at a time** for controlled QA; never enable unlinked QA Checkout in normal use.
6. Keep live bookings disabled until the complete guest/parent journey, consent, payment lifecycle and leader notifications have passed testing.

## Outstanding integration gaps

- Parent form submission-to-reservation front-end handoff is not yet wired.
- Guest input is a disabled preview; guest reservation and payment support do not exist.
- Verified leader email notification delivery and parent confirmation are not implemented.
- Standalone consent form is only a schema foundation.
- No end-to-end Stripe test payment has been verified on staging.

**Release decision:** Not yet ready to activate. A code-only merge to main is conditional on the mandatory pre-merge checks above.
