# Google sign-in — staging implementation

Status: implementation branch only; not deployed or enabled on the live site.

## What this adds

- Google OpenID Connect authorisation-code flow.
- Random state and nonce, browser-bound HttpOnly/SameSite=Lax state cookie, PKCE S256, and one-use state transient.
- Server-side ID-token signature verification against Google's rotating JWKS, plus issuer, audience/authorised-party, expiry, nonce and verified-email checks.
- New Family and Class Leader WordPress account paths.
- New Family accounts are created with the WordPress Subscriber role and account-type metadata.
- New Leader accounts are created with the Subscriber role, marked `pending_approval`, and tagged as requiring leader approval. They do not receive leader capabilities automatically.
- Existing WordPress email matches are never silently linked. A one-use, 15-minute email confirmation link is sent; the link opens a confirmation page and requires a nonce-protected POST before the Google subject is attached.
- A returning Google subject signs in to the one matching WordPress account.
- The native WordPress login form and membership comparison shortcode can display the Google options.

## Important membership integration boundary

This first implementation creates/authenticates WordPress users and records account type/status in user metadata. It does **not** create a Simple Membership (SWPM) member record or assign a Family Free / Leader Free membership level. The current membership field-map says SWPM owns membership tiers and that IDs must be confirmed on staging. Do not represent a Google-created WordPress account as fully enrolled in a paid/free SWPM tier until that adapter is implemented and tested.

Existing accounts are only connected after confirmation delivered to the existing account email address. This is not the same as importing or linking the legacy `bh_users` identity. The legacy bridge contract remains unchanged.

## Google Cloud setup

Create an OAuth client of type **Web application** in Google Cloud Console.

Authorised redirect URI must exactly equal:

```
https://staging.bubbahub.co.uk/wp-admin/admin-post.php?action=bh_google_callback
```

After staging tests, add the live callback as a separate authorised redirect URI:

```
https://bubbahub.co.uk/wp-admin/admin-post.php?action=bh_google_callback
```

No JavaScript origin is needed for this server-side flow. Configure the consent screen / audience and add test users while the app is in testing mode.

Add credentials to the staging `wp-config.php` above the stop-editing line. Never commit the secret to Git:

```php
define( 'BH_GOOGLE_CLIENT_ID', 'YOUR_WEB_CLIENT_ID.apps.googleusercontent.com' );
define( 'BH_GOOGLE_CLIENT_SECRET', 'YOUR_WEB_CLIENT_SECRET' );
```

The site must use HTTPS and the PHP OpenSSL extension must be enabled.

## Page placement

- The `[bh_membership_registration]` output now includes the Google account choices.
- The native WordPress `wp-login.php` form gets the same sign-in component.
- If the custom `/auth/` page is built with FluentAuth or page-builder content rather than the native WordPress login form, add `[bh_google_login]` to that page manually after checking its existing layout. Do not replace or remove the current email/password form.

## Staging acceptance checklist

1. Google consent and callback work on staging HTTPS.
2. State mismatch, missing cookie, expired state, OAuth denial, bad signature, wrong audience, wrong nonce, expired token, unverified email and unknown key all fail closed.
3. New Family selection creates one Subscriber WP account with `bh_account_type=family` and `bh_account_status=active`.
4. New Leader selection creates one Subscriber WP account with `bh_account_type=leader`, `bh_account_status=pending_approval`, and `bh_leader_approval_required=1`; no leader capabilities are granted.
5. Repeat sign-in for the same Google subject reuses the same WP account and never creates a duplicate.
6. Existing WP email match sends a confirmation email; no metadata changes occur until the user submits the confirmation form.
7. Expired, replayed, altered or already-used link tokens fail.
8. A Google subject already attached to another account cannot be linked again.
9. Existing email/password and FluentAuth login continue to work.
10. Test family/leader route permissions and account recovery before enabling any private Family or Leader features.
11. Implement and test Simple Membership free-tier provisioning before considering registration fully integrated.
12. Test logout, login redirect, mobile layout, mail delivery and Google consent-screen branding.

## Deployment gate

Do not merge to `main`, add live credentials, or deploy until staging credentials have been configured and the above tests pass. No Google client secret belongs in the repository.
