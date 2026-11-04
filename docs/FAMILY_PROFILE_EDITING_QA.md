# Family profile editing — staging QA

The editor service is loaded but disabled by default. Do not enable it on live until staging tests pass.

## Preconditions

- FluentAuth establishes a WordPress session.
- Login Bridge resolves the matching active family identity; no email-only auto-linking.
- Legacy `bh_children` table is available in the expected database.
- Private Family Hub preview is restricted to verified family accounts and excluded from page caching.

## Test cases

1. Logged-out call is rejected without writing.
2. Logged-in unlinked account is rejected without writing.
3. Leader account is rejected without writing.
4. Invalid or expired nonce is rejected.
5. An attempt to edit another family's child ID does not change the row.
6. Valid owner update changes only the intended name field.
7. Empty and overlong names are rejected.
8. Missing table produces an error, not a schema migration.
9. Both opt-in constants must be enabled; either one disabled blocks writes.
10. Existing legacy My Hub remains functional after a staging update.

No public form or endpoint is registered yet. Runtime tests have not been performed.
