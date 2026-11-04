<?php
/**
 * Read-only compatibility with the existing Bubba Hub Login Bridge MU plugin.
 * FluentAuth establishes the WordPress session; the bridge validates legacy identity.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class BH_Family_Identity_3 {
    /**
     * Return a verified legacy family ID, or 0 if unavailable.
     * Never infer a link from matching numeric IDs or email alone.
     */
    public static function current_legacy_id() {
        if ( ! is_user_logged_in() ) { return 0; }

        // Reuse the existing bridge when installed; do not create a competing mapping.
        if ( function_exists( 'bh_wp_resolve_legacy_identity' ) ) {
            $identity = bh_wp_resolve_legacy_identity();
            if ( is_wp_error( $identity ) || ! is_array( $identity ) ) { return 0; }
            if ( ( $identity['role'] ?? '' ) !== 'family' ) { return 0; }
            if ( (int) ( $identity['wordpress_id'] ?? 0 ) !== get_current_user_id() ) { return 0; }
            return max( 0, (int) ( $identity['legacy_id'] ?? 0 ) );
        }

        // A missing MU bridge must not be silently replaced with weaker checks.
        return 0;
    }
}
