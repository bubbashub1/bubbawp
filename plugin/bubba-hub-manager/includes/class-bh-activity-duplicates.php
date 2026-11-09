<?php
/**
 * Directory-wide duplicate checks for activity listings.
 * Exact title + venue matches are blocking; title-only matches are warnings.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class BH_Activity_Duplicates_3 {
    public static function register() {
        add_action( 'wp_ajax_bh3_check_activity_duplicates', array( __CLASS__, 'check_ajax' ) );
    }

    private static function allowed() {
        if ( strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk' ) { return false; }
        return current_user_can( 'manage_options' ) || ( is_user_logged_in() && current_user_can( 'read' ) && absint( get_user_meta( get_current_user_id(), 'bh3_organiser_id', true ) ) > 0 );
    }

    /**
     * Match title case-insensitively after trimming whitespace, then compare
     * selected venue IDs with each candidate's linked venues.
     */
    public static function find_matches( $title, $venue_ids = array(), $exclude_id = 0 ) {
        global $wpdb;
        $activities = $wpdb->prefix . 'activities';
        $links      = $wpdb->prefix . 'activity_venues';
        $venues     = $wpdb->prefix . 'venues';

        foreach ( array( $activities, $links, $venues ) as $table ) {
            if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) {
                return new WP_Error( 'duplicate_check_unavailable', 'The directory duplicate checker is temporarily unavailable. Please try again.' );
            }
        }

        $title = trim( (string) $title );
        if ( $title === '' ) { return array( 'exact' => array(), 'potential' => array() ); }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, title, status FROM `{$activities}`
                 WHERE LOWER(TRIM(title)) = LOWER(TRIM(%s)) AND id <> %d
                 ORDER BY id DESC LIMIT 25",
                $title,
                absint( $exclude_id )
            ),
            ARRAY_A
        );
        $selected = array_values( array_unique( array_filter( array_map( 'absint', (array) $venue_ids ) ) ) );
        $matches = array( 'exact' => array(), 'potential' => array() );

        foreach ( (array) $rows as $row ) {
            $candidate_id = absint( $row['id'] );
            $candidate_venues = array_map(
                'intval',
                (array) $wpdb->get_col( $wpdb->prepare(
                    "SELECT v.id FROM `{$venues}` v
                     INNER JOIN `{$links}` av ON av.venue_id = v.id
                     WHERE av.activity_id = %d ORDER BY v.name ASC",
                    $candidate_id
                ) )
            );
            $overlap = array_values( array_intersect( $selected, $candidate_venues ) );
            $venue_names = array();
            if ( $candidate_venues ) {
                $placeholders = implode( ',', array_fill( 0, count( $candidate_venues ), '%d' ) );
                $venue_names = (array) $wpdb->get_col( $wpdb->prepare(
                    "SELECT name FROM `{$venues}` WHERE id IN ({$placeholders}) ORDER BY name ASC",
                    $candidate_venues
                ) );
            }
            $item = array(
                'id' => $candidate_id,
                'title' => (string) $row['title'],
                'status' => (string) $row['status'],
                'venues' => array_values( array_map( 'strval', $venue_names ) ),
            );
            if ( $overlap ) {
                $matches['exact'][] = $item;
            } else {
                $matches['potential'][] = $item;
            }
        }
        return $matches;
    }

    public static function check_ajax() {
        if ( ! self::allowed() ) {
            wp_send_json_error( array( 'message' => 'You do not have permission to check directory listings.' ), 403 );
        }
        check_ajax_referer( 'bh3_activity_duplicate_check', 'nonce' );
        $title = isset( $_POST['title'] ) && is_string( $_POST['title'] )
            ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
        $venue_ids = isset( $_POST['venue_ids'] ) && is_array( $_POST['venue_ids'] )
            ? array_map( 'absint', wp_unslash( $_POST['venue_ids'] ) ) : array();
        $exclude_id = absint( $_POST['activity_id'] ?? 0 );
        $matches = self::find_matches( $title, $venue_ids, $exclude_id );
        if ( is_wp_error( $matches ) ) {
            wp_send_json_error( array( 'message' => $matches->get_error_message() ), 503 );
        }
        wp_send_json_success( $matches );
    }
}
