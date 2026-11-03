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
        echo '<div class="wrap bh3-my-listings"><style>.bh3-my-listings{max-width:1120px}.bh3-my-listings table{border-radius:10px;overflow:hidden;border:1px solid #dce5de;box-shadow:none}.bh3-my-listings th,.bh3-my-listings td{padding:15px 16px;vertical-align:middle}.bh3-my-listings .button{border-radius:7px}.bh3-my-listings .bh3-status{background:#edf4ef;color:#27583b;border-radius:20px;padding:5px 11px;display:inline-block;text-transform:capitalize;font-weight:600;font-size:12px}@media(max-width:600px){.bh3-my-listings{overflow-x:auto}.bh3-my-listings th,.bh3-my-listings td{padding:10px}}</style><h1>My Listings</h1>';
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
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, title, status, category FROM `{$activities}` WHERE organiser_id = %d ORDER BY id DESC LIMIT 100", $organiser_id
        ), ARRAY_A );
        echo '<table class="widefat striped"><thead><tr><th>Activity</th><th>Category</th><th>Status</th><th>Action</th></tr></thead><tbody>';
        foreach ( $rows as $row ) {
            echo '<tr><td>' . esc_html( $row['title'] ) . '</td><td>' . esc_html( (string) $row['category'] ) .
                '</td><td><span class="bh3-status">' . esc_html( $row['status'] ) . '</span></td><td>' . ( $row['status'] === 'draft' ? '<a class="button" href="' . esc_url( add_query_arg( array( 'page' => 'bh3-leader-edit', 'activity_id' => absint( $row['id'] ) ), admin_url( 'admin.php' ) ) ) . '">Edit</a>' : 'View only' ) . '</td></tr>';
        }
        if ( ! $rows ) { echo '<tr><td colspan="4">No activities assigned to this organiser.</td></tr>'; }
        echo '</tbody></table></div>';
    }
}
