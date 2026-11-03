<?php
/** Explicit activity-to-venue assignments for the fresh BH3 schema. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class BH_Leader_Venues_3 {
    private static function allowed() {
        return is_user_logged_in() && current_user_can( 'read' ) && strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) === 'staging.bubbahub.co.uk';
    }
    public static function register() {
        add_submenu_page( null, 'My Activity Venues', 'My Venues', 'read', 'bh3-leader-venues', array( __CLASS__, 'render' ) );
        add_action( 'admin_post_bh3_leader_attach_venue', array( __CLASS__, 'attach' ) );
        add_action( 'admin_post_bh3_leader_detach_venue', array( __CLASS__, 'detach' ) );
    }
    private static function tables_ready() {
        global $wpdb;
        foreach ( array( 'activities', 'venues', 'activity_venues', 'sessions' ) as $name ) {
            $table = $wpdb->prefix . $name;
            if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) { return false; }
        }
        return true;
    }
    private static function activity( $id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'activities';
        $organiser = absint( get_user_meta( get_current_user_id(), 'bh3_organiser_id', true ) );
        if ( ! $organiser ) { return null; }
        return $wpdb->get_row( $wpdb->prepare( "SELECT id, title, status FROM `{$table}` WHERE id = %d AND organiser_id = %d AND status = 'draft' LIMIT 1", $id, $organiser ), ARRAY_A );
    }
    private static function redirect( $id, $message ) {
        wp_safe_redirect( add_query_arg( array( 'page' => 'bh3-leader-venues', 'activity_id' => $id, 'message' => $message ), admin_url( 'admin.php' ) ) );
        exit;
    }
    public static function render() {
        if ( ! self::allowed() ) { wp_die( 'Staging administrator access required.', '', array( 'response' => 403 ) ); }
        if ( ! self::tables_ready() ) { wp_die( 'Required activity and venue tables are missing.', '', array( 'response' => 409 ) ); }
        global $wpdb;
        $id = absint( $_GET['activity_id'] ?? 0 );
        $activity = self::activity( $id );
        if ( ! $activity ) { wp_die( 'Activity not found.', '', array( 'response' => 404 ) ); }
        $venues = $wpdb->prefix . 'venues';
        $links = $wpdb->prefix . 'activity_venues';
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT v.id, v.name, v.town FROM `{$venues}` v INNER JOIN `{$links}` av ON av.venue_id = v.id WHERE av.activity_id = %d ORDER BY v.name LIMIT 100", $id
        ), ARRAY_A );
        echo '<div class="wrap"><h1>Venues for ' . esc_html( $activity['title'] ) . '</h1>';
        echo '<p><a href="' . esc_url( add_query_arg( array( 'page' => 'bh3-leader-edit', 'activity_id' => $id ), admin_url( 'admin.php' ) ) ) . '">← Back to activity</a> · <a href="' . esc_url( add_query_arg( array( 'page' => 'bh3-leader-sessions', 'activity_id' => $id ), admin_url( 'admin.php' ) ) ) . '">Manage timetable</a></p>';
        if ( isset( $_GET['message'] ) ) { echo '<div class="notice notice-info"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['message'] ) ) ) . '</p></div>'; }
        echo '<h2>Assigned venues</h2><table class="widefat striped"><thead><tr><th>Venue</th><th>Town</th><th>Action</th></tr></thead><tbody>';
        foreach ( $rows as $row ) {
            echo '<tr><td>' . esc_html( $row['name'] ) . '</td><td>' . esc_html( (string) $row['town'] ) . '</td><td>';
            if ( $activity['status'] === 'draft' ) {
                echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
                echo '<input type="hidden" name="action" value="bh3_leader_detach_venue"><input type="hidden" name="activity_id" value="' . esc_attr( (string) $id ) . '"><input type="hidden" name="venue_id" value="' . esc_attr( (string) $row['id'] ) . '">';
                wp_nonce_field( 'bh3_leader_detach_venue_' . $id . '_' . $row['id'] );
                submit_button( 'Remove assignment', 'secondary small', '', false );
                echo '</form>';
            } else { echo 'Read-only'; }
            echo '</td></tr>';
        }
        if ( ! $rows ) { echo '<tr><td colspan="3">No venues assigned.</td></tr>'; }
        echo '</tbody></table>';
        if ( $activity['status'] !== 'draft' ) { echo '<p>Only draft activities can change venue assignments.</p></div>'; return; }
        $term = substr( sanitize_text_field( wp_unslash( $_GET['venue_search'] ?? '' ) ), 0, 100 );
        echo '<h2>Find an existing venue</h2><form method="get"><input type="hidden" name="page" value="bh3-leader-venues"><input type="hidden" name="activity_id" value="' . esc_attr( (string) $id ) . '">';
        echo '<input type="search" name="venue_search" value="' . esc_attr( $term ) . '" placeholder="Venue name or town"> ';
        submit_button( 'Find venues', 'secondary', '', false );
        echo '</form>';
        if ( strlen( trim( $term ) ) >= 2 ) {
            $like = '%' . $wpdb->esc_like( $term ) . '%';
            $matches = $wpdb->get_results( $wpdb->prepare(
                "SELECT id, name, town FROM `{$venues}` WHERE name LIKE %s OR town LIKE %s ORDER BY name LIMIT 30", $like, $like
            ), ARRAY_A );
            echo '<table class="widefat striped"><thead><tr><th>Venue</th><th>Town</th><th>Action</th></tr></thead><tbody>';
            foreach ( $matches as $row ) {
                echo '<tr><td>' . esc_html( $row['name'] ) . '</td><td>' . esc_html( (string) $row['town'] ) . '</td><td>';
                echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
                echo '<input type="hidden" name="action" value="bh3_leader_attach_venue"><input type="hidden" name="activity_id" value="' . esc_attr( (string) $id ) . '"><input type="hidden" name="venue_id" value="' . esc_attr( (string) $row['id'] ) . '">';
                wp_nonce_field( 'bh3_leader_attach_venue_' . $id . '_' . $row['id'] );
                submit_button( 'Assign venue', 'secondary small', '', false );
                echo '</form></td></tr>';
            }
            if ( ! $matches ) { echo '<tr><td colspan="3">No matching venues. Create one in Venues Manager first.</td></tr>'; }
            echo '</tbody></table>';
        }
        echo '</div>';
    }
    public static function attach() {
        if ( ! self::allowed() || ! self::tables_ready() ) { wp_die( 'Not permitted.', '', array( 'response' => 403 ) ); }
        $id = absint( $_POST['activity_id'] ?? 0 );
        $venue_id = absint( $_POST['venue_id'] ?? 0 );
        check_admin_referer( 'bh3_leader_attach_venue_' . $id . '_' . $venue_id );
        $activity = self::activity( $id );
        if ( ! $activity || $activity['status'] !== 'draft' || ! $venue_id ) { wp_die( 'Draft activity required.', '', array( 'response' => 409 ) ); }
        global $wpdb;
        $venues = $wpdb->prefix . 'venues';
        if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$venues}` WHERE id = %d", $venue_id ) ) ) { wp_die( 'Venue not found.', '', array( 'response' => 404 ) ); }
        if ( ! self::activity( $id ) ) { wp_die( 'Activity ownership changed.', '', array( 'response' => 403 ) ); }
        $links = $wpdb->prefix . 'activity_venues';
        $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$links}` WHERE activity_id = %d AND venue_id = %d", $id, $venue_id ) );
        if ( $existing ) { self::redirect( $id, 'Venue already assigned.' ); }
        if ( false === $wpdb->insert( $links, array( 'activity_id' => $id, 'venue_id' => $venue_id ), array( '%d', '%d' ) ) ) { wp_die( 'Unable to assign venue.', '', array( 'response' => 500 ) ); }
        self::redirect( $id, 'Venue assigned.' );
    }
    public static function detach() {
        if ( ! self::allowed() || ! self::tables_ready() ) { wp_die( 'Not permitted.', '', array( 'response' => 403 ) ); }
        $id = absint( $_POST['activity_id'] ?? 0 );
        $venue_id = absint( $_POST['venue_id'] ?? 0 );
        check_admin_referer( 'bh3_leader_detach_venue_' . $id . '_' . $venue_id );
        $activity = self::activity( $id );
        if ( ! $activity || $activity['status'] !== 'draft' || ! $venue_id ) { wp_die( 'Draft activity required.', '', array( 'response' => 409 ) ); }
        global $wpdb;
        $sessions = $wpdb->prefix . 'sessions';
        $count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$sessions}` WHERE activity_id = %d AND venue_id = %d", $id, $venue_id ) );
        if ( $count ) { wp_die( 'Cannot remove venue while sessions use it.', '', array( 'response' => 409 ) ); }
        if ( ! self::activity( $id ) ) { wp_die( 'Activity ownership changed.', '', array( 'response' => 403 ) ); }
        $links = $wpdb->prefix . 'activity_venues';
        $deleted = $wpdb->delete( $links, array( 'activity_id' => $id, 'venue_id' => $venue_id ), array( '%d', '%d' ) );
        if ( $deleted === false ) { wp_die( 'Unable to remove assignment.', '', array( 'response' => 500 ) ); }
        self::redirect( $id, 'Venue assignment removed.' );
    }
}
