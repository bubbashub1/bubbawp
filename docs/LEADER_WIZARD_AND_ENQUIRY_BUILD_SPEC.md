# Leader Wizard, Venue Submission and Family Enquiry — Build Specification

Status: approved product specification; implementation and QA not complete.
Target: staging only. Do not enable leader, family or booking feature flags as part of this document.

## Scope boundary
The Schedule & Booking importer/provider integrations (Bookwhen, Pebble, ThinkSmart, Class4Kids, website/social reading, iCalendar, CSV and copy/paste timetable import) are a separate future workstream/chat. This specification covers the wizard structure and the manual schedule/booking choices only.

## Leader wizard — seven steps
1. Basics: activity title, linked organiser, website/source URL.
2. About: description, primary/additional categories and tags/keywords.
3. Extra Information: age ranges, pregnancy/postnatal suitability, pricing, useful information and contact details.
   - Contact defaults are read from the linked organiser storefront/profile.
   - Activity-specific overrides are saved on the activity and never write back to organiser data.
   - No image fields on this step.
4. Schedule & Booking: manual timetable/session details plus booking method:
   - external booking URL;
   - Bubba Hub email reserve/enquiry (available only when a valid leader_email/contact destination is verified);
   - drop-in/no booking required.
   - Timetable and booking method are separate concepts within this step. No timetable importer in this workstream.
5. Venues: select one or more shared venues; submit a new venue for admin review; venue accessibility/directions information belongs here.
   - A submitted venue is pending and is not inserted into the shared approved venue directory or linked as an approved venue until an administrator approves it.
   - Check likely duplicates by name, address and postcode before submission.
6. Photos & Media: listing cover and gallery uploads/imported image choices.
7. Review & Submit: review, validation, save draft and submit for moderation. No automatic publishing.

## Reserve / Enquire form — Fluent Forms 6
Public heading: “Interested in joining?”
Fields in this order:
1. Your Name
2. Email Address
3. Session Select (activity's available sessions; optional only for drop-in/no fixed sessions)
4. Child(ren) Select (multi-select; logged-in verified family profiles only; includes child and pregnancy/bump profiles)
5. Message
6. Optional consent checkbox:
   “I give permission for my selected child's gender and year of birth to be shared with the class leader to help them understand their needs and suitability for the activity.”
7. Required acknowledgement checkbox:
   “I understand that submitting this form is an enquiry only and does not guarantee or confirm a place. The class leader will contact me with further information about availability and next steps.”
8. Booking Terms & Conditions link: /legal/bookings/

Privacy rules:
- Guest enquiries remain possible without an account; child/pregnancy selection is unavailable to guests, who may enter relevant context in Message.
- Never send child gender or year of birth unless the optional sharing consent is explicitly selected.
- Pregnancy/bump profiles can be selected, but pregnancy profile details are not included in the child gender/year-of-birth sharing consent.
- Share year of birth only, never full date of birth or due date.
- The server must reload selected profile IDs for the currently authenticated, verified family account and reject IDs not owned by that account. Do not trust client-provided names, dates, gender, activity IDs, session IDs or leader email.
- For native WordPress family profiles, add an optional gender field through a versioned, explicit staging migration; do not silently modify/create tables on plugin load. Legacy profile reads must use the verified legacy family identity mapping and the existing gender field.
- Leader destination is resolved server-side from the activity's linked organiser/storefront leader_email or verified contact email. Do not expose it in the public form.
- Fluent Forms 6 stores the submission; notifications go to the verified leader destination and parent acknowledgement goes to the validated submitted email.
- Submission is an enquiry, not a confirmed booking, reservation, payment or enrolment. No auto-confirmation or payment capture.
- Minimise child data in email bodies and submission metadata; do not include profile name, full date, photo or other sensitive fields.

## Booking Terms page
Create a human-reviewed legal content page at /legal/bookings/. It must explain Bubba Hub's intermediary role, enquiry vs confirmation, organiser/parent responsibilities, availability, payments (none handled by this enquiry form), cancellations, privacy/data sharing, safeguarding/data minimisation, complaints and liability. Cross-link the Privacy Policy. Do not publish legal copy as final without UK legal review.

## Current code audit / blockers
- BH_Activity_Create_3 is a wp-admin wizard and its allowed() check requires manage_options; it is not the public class-leader wizard.
- BH_Manager_3::FEATURE_LEADER, FEATURE_FAMILY and FEATURE_BOOKINGS are false. Do not enable them until explicit staging QA.
- Existing leader front-end editor is owner-scoped but edits existing draft activities only; it does not provide a new-listing wizard.
- Existing storefront profile has organiser name, description, website URL and contact email; the current schema/profile query does not expose a dedicated leader_email field. Resolve the canonical contact field before enabling email enquiries.
- Shared venues and owner-scoped venue assignment exist. There is no approved leader venue-submission workflow yet.
- Native wp_bh_family_profiles schema stores profile_type, display_name and profile_date but no gender. Legacy bh_children has gender/date_of_birth. A migration and safe ownership-aware reader are required before child gender sharing can be enabled.
- A new Fluent Forms 6 enquiry form has not yet been created/configured; do not assume a form ID. Existing forms #4 (Ask Support) and #5 (family profile) are unrelated and must not be reused.
- Do not build timetable provider imports in this workstream.

## Required QA before enabling
- Owner isolation: leader A cannot view/edit leader B's activity, venue request, sessions or enquiry.
- Contact inheritance and per-activity override; saving activity never mutates organiser profile.
- Guest enquiry and logged-in family enquiry.
- Multi-select children and bump profiles; tampered/foreign profile IDs rejected.
- Gender/year-of-birth included only with explicit consent; year only; no pregnancy profile data in that payload.
- Required enquiry acknowledgement; no confirmed booking language in success/notification emails.
- Correct server-resolved leader email; invalid/missing destination disables the email-enquiry choice with clear guidance.
- Fluent Forms 6 notification delivery and acknowledgement, spam protection, duplicate submission handling and retention.
- Venue duplicate detection, pending status, admin approval/rejection and assignment only after approval.
- Accessibility data saved against venue/location, not duplicated as generic activity field.
- Responsive seven-step wizard, keyboard navigation, validation, save/resume and review.
- Confirm live site unchanged; test only on staging.
