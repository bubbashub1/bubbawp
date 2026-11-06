# Forms and field contracts — DRAFT

Do not create duplicate Fluent Forms or overwrite current forms. Form numbers are **user-reported**; obtain actual staging JSON exports and notification settings before changing any.

| ID | Purpose | Fields / capture | Destination | Validation / privacy |
|---|---|---|---|---|
| 4 | Ask a Question | topic, question, public/private, contact if needed | Fluent Forms submission + approved Ask Support workflow | Moderation before public display; keep private contact separate |
| 5 | Family Support | category, message, contact, preferred reply | Fluent Forms submission + support inbox status/history | Private by default; minimise personal data |
| 6 | Interested in Joining (enquiry only) | name, email, session(s), selected family profiles if logged in, message, optional gender/year-sharing consent, mandatory acknowledgement | Fluent Forms submission; verified leader email notification; optional linked enquiry record | Never auto-confirm booking; reload profile ownership server-side; share only consented year/gender |
| 7 | Booking consent | booking reference, attendee(s), consent key/version, explicit responses, acknowledgement, timestamp | `bh3_booking_consents` and appropriate immutable snapshot | Server-side booking ownership, versioned consent, minimum necessary information; do not put medical data in ordinary notification emails |

## Proposed future workflows (form platform undecided)
- **Event visitor registration:** event key, timed entry, adult/child count, contact email, ticket terms, privacy acknowledgement; persist reservation then generate one QR per email/reservation according to Expo rules; do not duplicate legacy ticket tables.
- **Exhibitor application:** business, contact, website, table preference, accessibility/space requirements, public listing consent, insurance details only where required; review/approval workflow.
- **Sponsor enquiry:** organisation, contact, proposed tier/amount, consent to be contacted; financial receipt/payment state belongs in an authoritative payment ledger, not free-text submissions.
- **Claim a listing:** listing ID, verified requester identity, evidence, review status and audit trail; prefer native admin workflow.
- **Submit a venue:** venue details, postcode/address, contact, duplicate candidate, review decision; prefer native wizard and approval.
- **Newsletter opt-in:** email, channel, frequency, consent text/version, source, timestamp, unsubscribe mechanism; reuse existing newsletter subscriber/preferences records.

## Form-to-table rules
1. Forms collect input; **domain tables** hold authoritative booking, consent, event and approval state.
2. Store `form_id` and `submission_id` for provenance where necessary, without copying entire submission payloads.
3. Send leader messages only to server-resolved verified organiser destinations.
4. Use explicit permissions, nonces/CSRF protection, validation, retention and deletion rules; do not infer consent from a checked default.
5. Keep guest workflows available where appropriate, with rate limiting and Turnstile; no account linking by email alone.
6. Maintain versioned field maps in plugin configuration, not hard-coded positional assumptions.
7. Draft importable Fluent Forms JSON only after actual exports are inspected; this file is a contract, **not an import-ready form**.
