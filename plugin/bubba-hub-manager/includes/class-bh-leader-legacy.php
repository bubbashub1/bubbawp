<?php
/** Read-only bridge to legacy BH listings. Never equate WP and legacy user IDs. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class BH_Leader_Legacy_3 {
    public static function organiser_id() {
        if ( ! BH_Leader_Access_3::is_staging() || ! is_user_logged_in() || ! current_user_can( 'read' ) ) { return 0; }
        // Explicitly assigned by an administrator after verifying a claim.
        return absint( get_user_meta( get_current_user_id(), 'bh3_legacy_organiser_id', true ) );
    }
    public static function records( $search = '' ) {
        $id = self::organiser_id();
        if ( ! $id ) { return array( 'state' => 'unlinked', 'name' => '', 'rows' => array() ); }
        global $wpdb;
        foreach ( array( 'bh_organisers', 'bh_activities' ) as $table ) {
            if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) {
                return array( 'state' => 'missing', 'name' => '', 'rows' => array() );
            }
        }
        // Legacy organiser display column is organisation_name, unlike BH3's name.
        $name = $wpdb->get_var( $wpdb->prepare( 'SELECT organisation_name FROM bh_organisers WHERE id = %d LIMIT 1', $id ) );
        if ( ! $name ) { return array( 'state' => 'invalid', 'name' => '', 'rows' => array() ); }
        $like = '%' . $wpdb->esc_like( substr( $search, 0, 100 ) ) . '%';
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, title, status FROM bh_activities WHERE organiser_id = %d AND title LIKE %s ORDER BY title ASC LIMIT 100",
            $id, $like
        ), ARRAY_A );
        return array( 'state' => 'ready', 'name' => (string) $name, 'rows' => is_array( $rows ) ? $rows : array() );
    }
}
