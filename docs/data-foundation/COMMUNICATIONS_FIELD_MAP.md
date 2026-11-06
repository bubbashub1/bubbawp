# Communications — implementation contract
Status: draft; audit existing email and subscription modules first.

| Domain | Source | Fields to verify |
|---|---|---|
| Newsletter | legacy bh_newsletter_subscribers | email, status, consent timestamp/source, unsubscribe token, updated_at |
| Push subscriptions | legacy bh_push_subscriptions | user/subscription identity, endpoint, permission, expiry |
| Push delivery | legacy bh_push_notifications | template, recipient, delivery status, sent_at, error |
| Transactional mail | FluentSMTP / existing mail logger | message type, recipient, attempt status, dedupe key, retry count |
| Planned delivery log | bhs_message_delivery_log draft | Only if existing logs cannot satisfy audit/dedupe needs |

Separate marketing consent from booking transactional communications. Send leader enquiries only to verified organisation email; never include sensitive child medical details in ordinary email. Use a stable message key for reminders and retries; protect unsubscribe tokens.

Forms: native preferences and opt-in, with explicit separate marketing consent. Pages: admin Communications, /my-hub/preferences/. No confirmed public shortcode.

Acceptance: double-send prevention, opt-out honored, no emails to wrong leader, template rendering, test mode, bounce/error visibility, rate limits, minimum data retention and unsubscribe functioning.
