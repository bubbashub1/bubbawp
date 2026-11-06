# Platform & Developments — implementation contract
Status: draft; this document is a planning inventory, not proof of staging deployment.

| Store | Purpose | Rules |
|---|---|---|
| WP option bh_development_tracker_statuses | 3.0 feature status | Preserve existing saved keys |
| WP option bh3_development_statuses | 4.0 phases | Manual status, not CI proof |
| WP option bhs_development_readiness | 10 sectors × 7 dimensions | Do not overwrite manual status during upgrades |
| GitHub main + PRs | Source and change history | Validate CI and review before merging |
| docs/data-foundation/*.DRAFT.sql | Proposed migrations | Never execute automatically |

Admin route: /wp-admin/admin.php?page=bh3-developments. No public shortcode or form.

Acceptance: statuses save/reload, admin nonce and capability checks, mobile usability, source-linked blueprint, separate drafted/validated/deployed states, staging-first deployment, backup and rollback plan. Never claim production or staging tests passed based solely on repository checks.
