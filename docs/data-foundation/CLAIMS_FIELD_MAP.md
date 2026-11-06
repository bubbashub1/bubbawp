# Claims & Data Quality — implementation contract
Status: draft. Ownership and moderation persistence need a code audit.

| Concept | Source to verify | Required behavior |
|---|---|---|
| Listing claim | Existing organiser/activity ownership | Verified claimant identity, claimed listing ID, evidence, status |
| Claim approval | Existing moderation/audit records | Actor, decision, timestamp, rejection reason |
| Duplicate detection | Existing activity/venue keys | Candidate match scoring; never auto-delete |
| Listing health | Activity/session data and external verification | last verified date, source URL, inactive/outdated flag |
| Venue correction | Existing venue edit flow | Suggested changes pending review |
| Import mapping | proposed bhs_legacy_id_map | One canonical mapping per legacy entity/ID |

Forms: native claim and venue correction forms planned, IDs not assigned. Pages: admin listings/moderation, /leader/venues/; shortcode to be decided.

Acceptance: no claim grants access before approval; ownership conflicts flagged; no silent overwrite of organiser edits; verification uses independent organiser website, booking page or recent social posts, not Bubba Hub as evidence; audit history retained.
