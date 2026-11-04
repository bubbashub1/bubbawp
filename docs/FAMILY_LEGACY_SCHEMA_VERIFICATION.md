# Legacy Family Hub schema verification

Source inspected: `bubbashub1/bubbaplugin/database/schema.sql` on the default branch (2026-11-04).

| Table | Expected fields | Key |
|---|---|---|
| `bh_saved_activities` | `user_id`, `activity_id`, `created_at` | PRIMARY (`user_id`, `activity_id`) |
| `bh_planner` | `user_id`, `activity_id`, `visited`, `created_at`, `updated_at` | PRIMARY (`user_id`, `activity_id`) |
| `bh_activities` | `id`, `title`, `slug`, `status` | PRIMARY (`id`) |

`bh_activities.status` is an ENUM including `published`. The new Family Hub checks this legacy table, not the WordPress `wp_activities` table, for additions from legacy saved favourites.

**Important:** Source schema is not proof that staging has the same structure. Before enabling `BH3_FAMILY_ACTIONS` and `BH3_FAMILY_LEGACY_ACTIVITY_STATUS_VERIFIED`, inspect the actual staging tables and test with a throwaway family account. New planner insert actions additionally check the composite primary keys at runtime and fail closed if they differ. The new WordPress activity-ID parity gate is separate and must not be enabled solely because the legacy schema matches.

No migrations or writes were performed during this source review.
