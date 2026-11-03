# ADR-017 — Communications module development slice

**Date:** 2026-11-03  
**Status:** Approved for isolated development by user request  
**Scope:** Newsletter management, transactional email integration point and central email activity log in Bubba Hub Manager 3.0.

## Decision

Proceed with a separate feature branch for communications while the theme-first foundation remains the main repository milestone. This is a contained development slice only; it does not mark the theme-first exit gate complete or authorise a live deployment.

Use WordPress core `wp_mail()` for delivery so the existing FluentSMTP plugin remains the transport. Store subscriber, campaign and email-log records in private WordPress post types for this initial slice, avoiding custom SQL tables and leaving all existing application and legacy tables untouched.

## Guardrails

- Staging-only sending and public newsletter sign-up; default disabled.
- Explicit administrator enablement on staging.
- Double opt-in and consent timestamp/source.
- Only confirmed active subscribers receive newsletters.
- Unsubscribe support and suppression of unsubscribed addresses.
- Batched campaign delivery via WP-Cron.
- Central email log without message bodies/attachments; 90-day log retention.
- No live deployment, live sends, or legacy email migration until QA and explicit approval.
- Transactional event triggers are integrated individually after the relevant event and privacy requirements are verified.

## Consequences

The module can be developed and reviewed independently without adding a paid plugin or service. The initial release provides the communications dashboard and a shared transactional mail API, but claim/booking/account triggers, subscriber export/erasure tools, template management and campaign analytics remain follow-up work.
