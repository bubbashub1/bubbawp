# Superseded: Events Manager / Ninja Forms migration proposal

This earlier proposal is superseded by the agreed BubbaWP + Ninja Forms architecture recorded in [NINJA_FORMS_BOOKING_ADAPTER_PLAN.md](NINJA_FORMS_BOOKING_ADAPTER_PLAN.md).

Decision (2026-11-08): Events Manager is not part of the core booking architecture. BubbaWP owns activities, sessions, booking validation, reservations, attendees, consent, capacity and booking lifecycle. Ninja Forms is the booking form interface. Stripe remains within the existing guarded BubbaWP payment flow.

Do not map or mirror BubbaWP activities/sessions into Events Manager. Preserve existing Fluent Forms 7/8 forms, submissions and checkout until the Ninja Forms route passes staging parity and the site owner explicitly approves cutover. No live activation, data migration, schema change or plugin deactivation is authorised by this document.
