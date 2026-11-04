<?php
/** Shared, fail-closed leader identity and ownership checks. No schema changes. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class BH_Leader_Access_3 {
    public static function is_staging() {
        return strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) === 'staging.bubbahub.co.uk';
    }
    public static function organiser_id() {
        if ( ! self::is_staging() || ! is_user_logged_in() || ! current_user_can( 'read' ) ) { return 0; }
        return absint( get_user_meta( get_current_user_id(), 'bh3_organiser_id', true ) );
    }
    public static function table_exists( $suffix ) {
        global $wpdb;
        if ( ! in_array( $suffix, array( 'activities', 'organisers', 'venues', 'sessions', 'activity_venues' ), true ) ) { return false; }
        $table = $wpdb->prefix . $suffix;
        return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) === $table;
    }
    public static function owns_activity( $activity_id, $draft_only = false ) {
        $organiser = self::organiser_id();
        if ( ! $organiser || ! absint( $activity_id ) || ! self::table_exists( 'activities' ) ) { return false; }
        global $wpdb;
        $table = $wpdb->prefix . 'activities';
        $sql = "SELECT id FROM `{$table}` WHERE id = %d AND organiser_id = %d";
        if ( $draft_only ) { $sql .= " AND status = 'draft'"; }
        $sql .= ' LIMIT 1';
        return (bool) $wpdb->get_var( $wpdb->prepare( $sql, absint( $activity_id ), $organiser ) );
    }
    public static function owns_session( $session_id, $draft_only = false ) {
        if ( ! absint( $session_id ) || ! self::table_exists( 'sessions' ) || ! self::table_exists( 'activities' ) ) { return false; }
        global $wpdb;
        $sessions = $wpdb->prefix . 'sessions';
        $activities = $wpdb->prefix . 'activities';
        $organiser = self::organiser_id();
        if ( ! $organiser ) { return false; }
        $sql = "SELECT s.id FROM `{$sessions}` s INNER JOIN `{$activities}` a ON a.id = s.activity_id WHERE s.id = %d AND a.organiser_id = %d";
        if ( $draft_only ) { $sql .= " AND a.status = 'draft'"; }
        return (bool) $wpdb->get_var( $wpdb->prepare( $sql . ' LIMIT 1', absint( $session_id ), $organiser ) );
    }
}
