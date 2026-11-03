<?php
/** Staging-only, read-only organiser-owned activity view. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class BH_My_Listings_3 {
    public static function register() {
        if ( strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk' ) { return; }
        add_menu_page( 'My Listings', 'My Listings', 'read', 'bh3-my-listings', array( __CLASS__, 'render' ), 'dashicons-list-view', 27 );
    }
    public static function render() {
        if ( ! is_user_logged_in() || ! current_user_can( 'read' ) ||
            strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk' ) {
            wp_die( 'Staging sign-in required.', '', array( 'response' => 403 ) );
        }
        global $wpdb;
        $organiser_id = absint( get_user_meta( get_current_user_id(), 'bh3_organiser_id', true ) );
        echo '<div class="wrap"><h1>My Listings</h1>';
        if ( ! $organiser_id ) { echo '<p>Your account is not linked to an organiser. Ask an administrator to link it.</p></div>'; return; }
        $organisers = $wpdb->prefix . 'organisers';
        $activities = $wpdb->prefix . 'activities';
        foreach ( array( $organisers, $activities ) as $table ) {
            if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) {
                echo '<p>The new listing tables are not available.</p></div>'; return;
            }
        }
        $name = $wpdb->get_var( $wpdb->prepare( "SELECT name FROM `{$organisers}` WHERE id = %d LIMIT 1", $organiser_id ) );
        if ( ! $name ) { echo '<p>Linked organiser was not found. Contact an administrator.</p></div>'; return; }
        echo '<p>Organiser: <strong>' . esc_html( $name ) . '</strong></p>';
        echo '<p>Edit your own draft listings. Publishing and deletion remain administrator-controlled.</p>';
        echo '<p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=bh3-leader-create' ) ) . '">+ Add new class</a></p>';
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, title, status, category FROM `{$activities}` WHERE organiser_id = %d ORDER BY id DESC LIMIT 100", $organiser_id
        ), ARRAY_A );
        echo '<table class="widefat striped"><thead><tr><th>Activity</th><th>Category</th><th>Status</th><th>Action</th></tr></thead><tbody>';
        foreach ( $rows as $row ) {
            echo '<tr><td>' . esc_html( $row['title'] ) . '</td><td>' . esc_html( (string) $row['category'] ) .
                '</td><td>' . esc_html( $row['status'] ) . '</td><td>' . ( $row['status'] === 'draft' ? '<a class="button" href="' . esc_url( add_query_arg( array( 'page' => 'bh3-leader-edit', 'activity_id' => absint( $row['id'] ) ), admin_url( 'admin.php' ) ) ) . '">Edit</a>' : 'View only' ) . '</td></tr>';
        }
        if ( ! $rows ) { echo '<tr><td colspan="4">No activities assigned to this organiser.</td></tr>'; }
        echo '</tbody></table></div>';
    }
}
