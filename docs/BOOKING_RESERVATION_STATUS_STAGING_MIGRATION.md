# Booking reservation status — staging migration plan

The native `bh3_bookings` table previously held only `payment_status`. Reservation status is independent of payment: a family can pay but still be awaiting leader confirmation, and a free place can be enrolled without a Stripe payment.

## Proposed additive schema

`BH_Schema_3::statements()` now declares `booking_status varchar(30) NOT NULL DEFAULT 'reserved'` with index `booking_status`. Existing records and payment fields must be preserved. **This PR does not execute any migration.** Staging will report a missing column/index until a reviewed migration is run.

## Before staging migration

1. Back up the staging database and inspect existing booking rows and status semantics.
2. Check `SHOW COLUMNS FROM wp_bh3_bookings LIKE 'booking_status';` and `SHOW INDEX FROM wp_bh3_bookings WHERE Key_name = 'booking_status';` (replace `wp_` with actual prefix).
3. If column absent, run on staging only after review:

```sql
ALTER TABLE wp_bh3_bookings
  ADD COLUMN booking_status varchar(30) NOT NULL DEFAULT 'reserved' AFTER payment_status;
```

4. If index absent, run:

```sql
ALTER TABLE wp_bh3_bookings ADD INDEX booking_status (booking_status);
```

5. Re-run Booking Structure audit and confirm both new requirements pass.

## Important safeguards

- Do not run the SQL on live until staging verification and rollback planning.
- The default `reserved` is a **technical placeholder** for existing rows, not proof that historical reservations are valid. Reconcile historic booking records before enabling leader actions.
- No automatic expiry, cancellation, enrollment, payment collection or customer notifications are enabled by this declaration.
- Application write endpoints must check organiser ownership, nonces, allowed transitions and concurrency before changing status.
- Payment refunds and Stripe events must remain separate from reservation status.
