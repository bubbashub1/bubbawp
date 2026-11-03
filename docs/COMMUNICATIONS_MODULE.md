# Communications module — first implementation slice

**Branch:** `feature/communications-newsletters-email-logs`  
**Status:** implementation draft; requires CI and staging QA  
**Scope:** newsletter consent, campaign queue, transactional mail adapter and central email activity.

## Design

- WordPress core `wp_mail()` is the only delivery interface. FluentSMTP remains the SMTP transport; this module does not store SMTP credentials or bypass it.
- Subscriber, campaign and email-log records use private, non-public WordPress post types and post meta. No custom SQL tables or changes to existing `wp_*` / legacy `bh_*` tables are introduced.
- Newsletter sign-up records consent timestamp and source, then requires double opt-in confirmation. Only active/confirmed subscribers are eligible for a campaign.
- Every campaign includes a one-click unsubscribe link. Unsubscribed subscribers are excluded from subsequent batches.
- Campaigns are queued in batches of 20 using WP-Cron to avoid one long-running request.
- A reusable `BH_Communications_3::send_transactional()` API is provided for future claim, booking, account and reminder event integrations.
- WordPress `wp_mail_succeeded` and `wp_mail_failed` actions feed a central log. The log records recipient, subject, type, status and error detail, but deliberately does not retain message bodies or attachments.
- Email activity logs are automatically pruned after 90 days. Subscriber consent and suppression records are retained until an approved privacy/retention workflow is implemented.

## Admin experience

WordPress Admin → **Bubba Hub 3.0 → Communications** provides:
- subscriber and pending-confirmation counts;
- accepted-by-mailer and failed-email counts;
- an explicit staging-only sending control;
- newsletter draft creation and queue action;
- campaign status and batch counts;
- recent email activity and recent subscriber status;
- the `[bubbahub_newsletter_signup]` shortcode.

## Safety gates

- Delivery and public newsletter sign-up are disabled unless the site host is exactly `staging.bubbahub.co.uk` and the administrator explicitly enables communications.
- This branch does not authorise live deployment or live sends.
- A WordPress/SMTP accepted result is not proof of inbox delivery, placement or opening. FluentSMTP transport logs remain useful for SMTP diagnostics.
- No newsletter is sent to pending, unsubscribed or unconfirmed addresses.
- No automatic claim/booking trigger has been connected in this first slice. Existing modules can call the transactional API after each event's recipient, consent/legal basis, template and QA requirements are agreed.

## QA still required

- PHP lint and WordPress coding/static checks in CI.
- Isolated staging activation and admin capability/nonce checks.
- Double-opt-in, duplicate signup, invalid token and unsubscribe tests.
- Campaign batching, mid-send unsubscribe, retry/failure and cron tests.
- FluentSMTP success/failure integration and confirmation of central log behaviour.
- Privacy review for subscriber export/erasure and a documented suppression policy.
- Confirm no collisions with the current legacy email service before enabling.

## Known follow-up work

- Subscriber search, filters, CSV export/import and deletion/suppression controls.
- Reusable branded email templates and template preview/test-send.
- Event-specific integrations for claim approvals/invitations, account notices, booking confirmations/reminders and organiser updates.
- Campaign scheduling, audience segmentation, unsubscribe preferences and reporting.
- Delivery provider webhooks for hard bounces/complaints where supported.
