# Ask Support staging release gate

This feature is **not production-ready**. Consolidated PR #117 remains draft until staging verification is complete.

## Deployment prerequisites

- [ ] Confirm a verified staging database backup.
- [ ] Confirm Fluent Forms form ID 4 and exact **Name Attribute** keys: `ask_category`, `ask_visibility`, `ask_specialist_consent`, `ask_question`, `ask_email`, `ask_name`.
- [ ] Confirm the specialist-sharing consent wording explicitly authorises moderator-reviewed text being shown to vetted specialists. `ask_specialist_consent` must submit exactly `yes`. A generic privacy-policy checkbox is **not** enough.
- [ ] Ensure form options use specialist category slugs from `BH_Ask_Support_Optin_3::categories()` and `public`/`private` visibility.
- [ ] Confirm whether guests can use the form. Guest submissions have no owner account and cannot use the signed-in parent history shortcode.
- [ ] Install the five staging tables using the manual admin installer; do not run on production.
- [ ] Keep `BH3_ASK_SUPPORT_INTAKE` undefined/false until tests are complete.
- [ ] Confirm WordPress caches/CDN do not cache signed-in question or inbox pages.

## Manual staging matrix

1. **Intake OFF**: submit form #4; confirm Fluent Forms receives it, no Ask Support question created.
2. **Intake ON** (staging only): submit valid consented test entry; confirm status `pending_review`, private contact stored separately, no specialist inbox exposure.
3. Submit invalid category, missing consent, missing email, invalid visibility; confirm fail-closed, no specialist exposure.
4. Retry same submission ID; confirm no duplicate question. Review concurrency/idempotency before release.
5. Confirm historic entries #1/#2 are **not imported**.
6. Moderator rejects test question; confirm no specialist visibility.
7. Moderator redacts a different test question and approves; confirm specialist sees **only the rewritten text**.
8. Specialist in matching category but not approved: no access. Approved matching specialist: access. Approved different category: no access.
9. Withdraw specialist opt-in, remove organiser association or delete organiser: access immediately revoked.
10. Specialist submits a reply; confirm `pending_review` and invisible to parent.
11. Moderator approves a reply; signed-in **owner only** sees approved reply. Another signed-in user and logged-out visitor cannot.
12. Moderator rejects a reply; it remains invisible.
13. Confirm no email notifications, public URLs, search indexing or anonymous REST access.
14. Review PII redaction and safeguarding with realistic examples, including sensitive child/health content. Avoid storing sensitive examples in test logs.

## Known blockers

- Current form field names and explicit specialist-sharing consent have **not been verified**.
- Approval depends on human redaction; automated PII checks and escalation for safeguarding concerns are not implemented.
- Moderators can edit replies before approval; staging testing of that review step remains outstanding.
- Guest access, notifications, opt-out/deletion, retention, parent follow-up and reporting are not built.
- Verify the unique submission index on any already-installed staging tables before enabling intake.
- Code-level CI does not substitute for WordPress/Fluent Forms staging integration tests.

## Rollout rule

Do not merge/deploy this feature to production, enable intake, or invite specialists until the privacy, consent, permissions and end-to-end tests pass.
