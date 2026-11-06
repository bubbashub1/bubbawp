# Membership — implementation contract
Status: draft; do not create a competing membership store.

Canonical sources: WP users and Simple Membership plugin's own tables/API. Never hard-code level IDs from memory; staging must confirm actual IDs and roles. Preserve FluentAuth login behavior.

| Concept | Source | Contract |
|---|---|---|
| Account identity | wp_users.ID | Native WP identity, no assumed match to legacy bh_users |
| Tier | Simple Membership level record | Family Free/Pro and Leader Free/Pro; verify actual IDs |
| Billing | Membership provider / Stripe | Subscription and renewal status through existing integration |
| Entitlements | Existing access checks | Define server-side feature matrix; UI hiding alone insufficient |
| Registration | Membership plugin registration | Correct tier selection and callback; never default to Family when Leader selected |
| Profile | WP user meta / native family/leader tables | Keep ownership consistent |

Pages: /membership-join/, /auth/. Candidate shortcode [bh_membership_registration], plus plugin-provided blocks. Family Pro £20/year and Leader Pro £70/year are planning prices; confirm current checkout configuration before publication.

Acceptance: free tiers can register; no cross-tier leakage; Stripe webhook verified and idempotent; cancellations and expiry revoke access appropriately; pricing chart matches actual products; no duplicate WP users.
