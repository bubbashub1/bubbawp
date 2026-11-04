# FluentAuth + Family Hub staging acceptance

## Scope
The initial integration uses WordPress core `is_user_logged_in()` and `wp_login_url()`. No FluentAuth-specific API or hook has been verified. The shortcode is a **preview** and does not read legacy family data.

## Manual test matrix
1. Signed out: open a staging page containing `[bubbahub_family_hub]`; expect sign-in link and no private information.
2. Follow sign-in: verify the active FluentAuth login experience handles WordPress's login URL or configure a documented login-page redirect. Check the redirect returns to the staging preview page, not live.
3. Signed in: see signed-in preview notice; no child, saved, booking or planner records.
4. Logout: refresh; preview returns to signed-out notice.
5. Disable FluentAuth in a disposable staging clone: WordPress core sign-in should still function.
6. Expired session: signed-out notice; no stale private content.
7. Test as family, leader and admin WP accounts: none should see legacy family data without verified mapping.
8. Verify mobile layout, keyboard navigation, safe return URLs and no login redirect loop.

## Blocking checks before family-data adapter
- Identify the actual FluentAuth plugin version and confirm whether it replaces or redirects `wp-login.php`.
- Confirm the exact login, registration, reset and logout URLs on staging.
- Inspect the current Login Bridge MU plugin and ensure it cannot accidentally map a leader/admin to a family.
- Establish a verified, auditable WP user -> `bh_users` mapping, never numeric equality or email-only automatic matching.
- Validate cookie/session behaviour of `BUBBAHUBSESSID` and WordPress/FluentAuth across old and new pages.
- Implement no-store headers and server-side ownership checks for all private responses.
- Do not activate the legacy `api/my-hub.php` as a read-only adapter because it can create/alter tables.

## Deployment
No deployment, merge, data write, schema change or production auth switch is authorised by this document.
