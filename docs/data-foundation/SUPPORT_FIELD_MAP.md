# Support & Guidance — implementation contract
Status: draft; existing native support storage and Fluent Forms must be inspected.

| Domain | Existing source | Field contract / privacy |
|---|---|---|
| Questions | {prefix}bh_ask_questions | id, topic, question, public/private status, moderation state, timestamps — verify actual names |
| Private contact | {prefix}bh_ask_private_contacts | question linkage, restricted contact data; never expose publicly |
| Replies | {prefix}bh_ask_replies | question linkage, author role, content, approval state |
| Moderation audit | {prefix}bh_ask_audit | actor, action, timestamp |
| Leader categories | {prefix}bh_ask_leader_categories | opt-in expertise categories and verification |
| Legacy | bh_support_questions, bh_support_answers, bh_guidance_topics, bh_guidance_offers | mapping only after staging verification |

Fluent Form 4 is Ask a Question; Form 5 is Family Support. Confirm real field keys, notification routing and storage via exports before writing integration. Public questions require moderation and removal of identifying details; private questions must not be visible to other families or leaders without appropriate permission.

Pages: /guidance/, /guidance/resources/, /guidance/blog/, /guidance/ask/. Candidate shortcodes: [bh_ask_question], [bh_support_hub], [bh_support_articles], [bh_support_resources], [bh_leader_ask_inbox]. Confirm which are registered.

Acceptance: role-based access, private/public boundaries, moderator audit, consent, notifications to correct recipients, deletion/retention policy, no accidental disclosure through search, feeds or caches.
