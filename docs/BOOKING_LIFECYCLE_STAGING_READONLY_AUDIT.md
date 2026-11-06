# Booking lifecycle — staging-only read-only audit

Purpose: verify that reservation status, payment status, expiry and the leader calendar agree **before** adding any public reservation or checkout action. Run against staging only. Queries below do not write data or expose customer details.

## 1. Inventory statuses and totals
```sql
SELECT booking_status, payment_status,
       COUNT(*) AS booking_records, COALESCE(SUM(places),0) AS places
FROM wp_bh3_bookings
GROUP BY booking_status, payment_status
ORDER BY booking_status, payment_status;
```
Expected on an empty table: zero rows. Never interpret an empty table as proof of working reservations.

## 2. Inspect the test session's date-specific occupancy
Replace the date with a **future Monday** that appears in the calendar; use session ID 2 only while that remains the verified staging fixture.
```sql
SELECT occurrence_date,
       COALESCE(SUM(CASE WHEN booking_status='reserved' THEN places ELSE 0 END),0) AS reserved,
       COALESCE(SUM(CASE WHEN booking_status IN ('confirmed','enrolled') THEN places ELSE 0 END),0) AS confirmed_or_enrolled,
       COALESCE(SUM(CASE WHEN booking_status IN ('cancelled','canceled','expired','rejected') THEN places ELSE 0 END),0) AS released,
       COALESCE(SUM(CASE WHEN booking_status NOT IN ('reserved','confirmed','enrolled','cancelled','canceled','expired','rejected') OR booking_status IS NULL OR places < 1 THEN 1 ELSE 0 END),0) AS needs_review
FROM wp_bh3_bookings
WHERE session_id=2
GROUP BY occurrence_date
ORDER BY occurrence_date;
```
The leader panel's unallocated preview must subtract both reserved and confirmed/enrolled places. Cancelled/expired/rejected rows do not occupy capacity. Unknown statuses and invalid quantities must block live availability.

## 3. Detect stale holds without modifying them
```sql
SELECT COUNT(*) AS expired_but_still_reserved,
       COALESCE(SUM(places),0) AS places_still_held
FROM wp_bh3_bookings
WHERE booking_status='reserved'
  AND reservation_expires_at IS NOT NULL
  AND reservation_expires_at < UTC_TIMESTAMP();
```
**Do not** directly update these rows to `expired`. Expiry must be atomic, idempotent and coordinated with checkout/webhook state. The current conservative capacity logic intentionally holds them until a verified transition is implemented.

## 4. Validate the transition contract before enabling writes
- New hold: `reserved` + `pending`; never `confirmed` from Form #7 submission alone.
- Confirm/enrol: only after verified payment or explicit authorised leader action.
- Cancel: status transition releases capacity exactly once; refund is a separate payment process.
- Expire: only a verified expired hold; protect checkout/webhook races.
- Retry: duplicate Form #7 submissions must not reserve places twice.
- Concurrency: two requests for the final seat must yield at most one successful hold.
- Ownership: one organiser cannot inspect or mutate another's bookings.
- Privacy: child details and optional demographics are not disclosed without explicit consent.

## Deployment gate
Keep `BH3_ENABLE_BOOKING_RESERVATIONS` disabled by default. No public booking endpoint, checkout, emails or test reservation inserts are part of this audit. Record results in the staging acceptance checklist before proceeding.
