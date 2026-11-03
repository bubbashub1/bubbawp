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
        echo '<p>Manage your class listings. Editing for class leaders is being tested; administrators can edit drafts.</p>';
        $term = isset( $_GET['listing_search'] ) && is_string( $_GET['listing_search'] )
            ? substr( sanitize_text_field( wp_unslash( $_GET['listing_search'] ) ), 0, 100 ) : '';
        $where = $term === '' ? '' : $wpdb->prepare( ' AND title LIKE %s', '%' . $wpdb->esc_like( $term ) . '%' );
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, title, status, category FROM `{$activities}` WHERE organiser_id = %d {$where} ORDER BY id DESC LIMIT 100", $organiser_id
        ), ARRAY_A );
        echo '<style>.bh3-listing-tools{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin:18px 0}.bh3-listing-tools input{min-width:240px}.bh3-listing-table td{vertical-align:middle;padding:14px 10px}.bh3-listing-status{display:inline-block;border-radius:20px;padding:4px 10px;background:#e7f0ec;color:#07563e;font-weight:600}.bh3-listing-table .button{margin-right:5px}</style>';
        echo '<form class="bh3-listing-tools" method="get"><input type="hidden" name="page" value="bh3-my-listings">';
        echo '<label for="listing_search">Search classes</label><input id="listing_search" type="search" name="listing_search" value="' . esc_attr( $term ) . '" placeholder="Activity name">';
        submit_button( 'Search', 'secondary', '', false );
        if ( $term !== '' ) { echo '<a href="' . esc_url( admin_url( 'admin.php?page=bh3-my-listings' ) ) . '">Clear</a>'; }
        echo '</form>';
        echo '<p><strong>' . esc_html( (string) count( $rows ) ) . '</strong> matching listings' . ( count( $rows ) === 100 ? ' (showing first 100)' : '' ) . '</p>';
        echo '<table class="widefat striped bh3-listing-table"><thead><tr><th>Activity</th><th>Category</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
        foreach ( $rows as $row ) {
            echo '<tr><td><strong>' . esc_html( $row['title'] ) . '</strong></td><td>' . esc_html( (string) $row['category'] ) .
                '</td><td><span class="bh3-listing-status">' . esc_html( ucfirst( $row['status'] ) ) . '</span></td><td>';
            if ( current_user_can( 'manage_options' ) && $row['status'] === 'draft' ) {
                $url = add_query_arg( array( 'page' => 'bh3-edit-activity', 'activity_id' => absint( $row['id'] ) ), admin_url( 'admin.php' ) );
                echo '<a class="button button-secondary" href="' . esc_url( $url ) . '">Edit draft</a>';
            } else { echo '<span class="description">View only</span>'; }
            echo '</td></tr>';
        }
        if ( ! $rows ) { echo '<tr><td colspan="4">No matching activities. ' . ( $term !== '' ? 'Try clearing the search.' : 'Ask an administrator to assign a class to your organiser.' ) . '</td></tr>'; }
        echo '</tbody></table></div>';
    }
}
