<?php
/**
 * Explicit, read-only bridge from a WordPress login (including FluentAuth)
 * to a verified legacy family ID. Does not create or link accounts.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class BH_Family_Identity_3 {
    const META_KEY = '_bh3_verified_legacy_family_id';

    /**
     * Return the verified legacy ID, or 0 when anything is ambiguous.
     * The mapping is provisioned only by a separately audited linking flow.
     */
    public static function current_legacy_id() {
        if ( ! is_user_logged_in() ) { return 0; }
        $wp_user_id = get_current_user_id();
        if ( $wp_user_id < 1 ) { return 0; }
        $raw = get_user_meta( $wp_user_id, self::META_KEY, true );
        if ( ! is_scalar( $raw ) || ! ctype_digit( (string) $raw ) ) { return 0; }
        $legacy_id = (int) $raw;
        if ( $legacy_id < 1 ) { return 0; }

        global $wpdb;
        // Never allow a legacy account to be linked to multiple WP accounts.
        $matches = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s",
            self::META_KEY,
            (string) $legacy_id
        ) );
        if ( (int) $matches !== 1 ) { return 0; }

        // No legacy schema is created, repaired, or migrated by this resolver.
        $table = 'bh_users';
        $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
        if ( $exists !== $table ) { return 0; }
        $status = $wpdb->get_var( $wpdb->prepare(
            "SELECT status FROM `bh_users` WHERE id = %d LIMIT 1",
            $legacy_id
        ) );
        return $status === 'active' ? $legacy_id : 0;
    }
}
