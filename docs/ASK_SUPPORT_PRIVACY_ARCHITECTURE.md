# Ask Support — privacy and specialist routing foundation

Status: design contract for staging development; **not deployed or enabled**.

## Confirmed behaviour

A parent submitting at `/guidance/ask/` chooses **Public** or **Private** before submitting. Both are routed to every opted-in, approved leader whose chosen expertise categories match the question (for example, all six sleep specialists). Leaders access the matching inbox within their **front-end Leader Dashboard**, never via WordPress admin.

### Visibility contract
- **Public**: question and approved answers may be published to a searchable advice library **only after moderator review**. Never publish parent name, email, phone, child identity or other contact details.
- **Private**: parent, approved matching specialists, and authorised moderators only. No public page, REST exposure, RSS, search index, sitemap, analytics payload, or caching of private content. All participants are told that several specialists may see and reply.
- Parent's email/contact data is accessible to authorised Bubba Hub support staff for notification purposes, not to specialists.
- Do not convert a private thread to public automatically. A later publication requires fresh, explicit consent and moderation of the exact redacted content.
- Do not treat a public choice as consent to publish sensitive details. Flag for moderation and redaction first.

### Roles and permissions
- Parent: create a question, view own thread using authenticated account or secure single-purpose token; reply and close own thread.
- Leader: opt in/out, select categories, view/reply to matched threads only while approved and opted in. No raw Fluent Forms submissions or private parent fields.
- Moderator: review, redact, approve public publication, hide, report, close, suspend specialist participation, audit decisions.
- Re-check access on **every** read, reply, notification, export, and attachment endpoint. Never rely on front-end visibility alone.

### Data model (new tables, separate from Fluent Forms)
- `{prefix}bh_ask_questions`: id, owner_user_id nullable, form_submission_id nullable, category_id, visibility (public/private), status (pending_review/open/closed/hidden), redacted_question, created_at, updated_at.
- `{prefix}bh_ask_private_contacts`: question_id, parent_email, parent_name, access-token hash (if guest), notification preferences. Restrict server-side reads to moderator/notification service.
- `{prefix}bh_ask_leader_categories`: organiser_id, user_id, category_id, opted_in, approval_status, updated_at. Enforce approved organiser ownership.
- `{prefix}bh_ask_replies`: id, question_id, author_user_id, organiser_id nullable, body, moderation_status, created_at. Public replies require approval.
- `{prefix}bh_ask_audit`: actor_id, question_id, event, occurred_at, limited metadata (no message bodies).
- Use WP database prefix; install with an explicit, versioned, staging-only admin action after backup and schema review. No automatic migration.

### Routing
1. Validate category and explicit public/private choice on server. Default to **private** if missing or invalid; do not publish.
2. Store private contact details separately from question content. Sanitize and moderate text, and check for identifying or sensitive information.
3. Match approved, opted-in leader/category pairs. No automatic assignment exclusivity; every matching specialist can see the thread.
4. Notify specialists with generic alerts and a login link, **not question text or personal details** in email.
5. Replies are moderated according to visibility. Notify parent without exposing private content in email.
6. When a leader opts out or loses approval, access is revoked immediately; existing published replies remain subject to moderation.
7. Handle no matching specialist: question remains queued for moderator review.

### Fluent Forms ID 4 migration
- Current form ID 4 is a generic contact form. **Do not publish or fan out its submissions** to leaders.
- Add an explicit required Public/Private choice with plain-language explanation and consent; verify imported form fields and exact category values before implementing the submission hook.
- Only ingest new submissions after the mapping, validation, anti-spam, moderation and permissions are tested. Existing submissions remain admin-only unless the parent gives fresh consent.
- Existing `bh_support_enquiry_*` workflow tables remain separate from Ask Support.

### Safeguards and acceptance tests
- Guest parent can securely access only their own thread; links expire/revoke and tokens are hashed at rest.
- Leader A (Sleep) sees Sleep questions only when opted in and approved; Leader B (Feeding) cannot access them, including by guessed ID.
- Six approved Sleep leaders can independently answer one Sleep thread.
- Private thread never leaks to public REST/search/cache/sitemap, even after a reply.
- Public thread appears only after moderator approval; names/emails and identifying content redacted.
- Moderators can hide unsafe content and revoke specialist access; actions audited.
- Escaping, nonces, rate limits, spam controls, attachment limits, privacy notice, retention/deletion policy and safeguarding escalation reviewed before enabling.
- No medical diagnosis, urgent-care promise, or unrestricted child-specific personal data collection.

## Rollout gates
1. Approve schema and privacy wording.
2. Build staging-only storage + permission checks and automated tests.
3. Build opt-in/category settings in leader dashboard.
4. Add parent visibility choice to form 4 and validate field mapping.
5. Build moderator queue and private thread view.
6. Test public publication/redaction and notifications.
7. Run cross-account privacy and security tests before live release.
