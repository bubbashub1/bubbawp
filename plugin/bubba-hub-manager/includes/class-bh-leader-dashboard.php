<?php
/** Read-only leader overview, restricted to staging and the linked organiser. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class BH_Leader_Dashboard_3 {
    public static function register() {
        if ( strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk' ) { return; }
        add_menu_page( 'Leader Dashboard', 'Leader Dashboard', 'read', 'bh3-leader-dashboard', array( __CLASS__, 'render' ), 'dashicons-groups', 28 );
    }
    public static function render() {
        if ( ! is_user_logged_in() || ! current_user_can( 'read' ) || strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk' ) {
            wp_die( 'Staging sign-in required.', '', array( 'response' => 403 ) );
        }
        global $wpdb;
        $organiser_id = absint( get_user_meta( get_current_user_id(), 'bh3_organiser_id', true ) );
        echo '<div class="wrap bh3-leader-dashboard"><h1>Leader Dashboard</h1>';
        echo '<p>Manage your activities, venues and weekly timetables in one place.</p>';
        if ( ! $organiser_id ) { echo '<div class="notice notice-warning"><p>Your account is not linked to an organiser. Ask an administrator to connect your account.</p></div></div>'; return; }
        $organisers = $wpdb->prefix . 'organisers';
        $activities = $wpdb->prefix . 'activities';
        foreach ( array( $organisers, $activities ) as $table ) {
            if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) {
                echo '<p>Leader data tables are not installed yet.</p></div>'; return;
            }
        }
        $name = $wpdb->get_var( $wpdb->prepare( "SELECT name FROM `{$organisers}` WHERE id=%d LIMIT 1", $organiser_id ) );
        if ( ! $name ) { echo '<p>Linked organiser not found. Contact an administrator.</p></div>'; return; }
        $counts = $wpdb->get_results( $wpdb->prepare( "SELECT status, COUNT(*) AS total FROM `{$activities}` WHERE organiser_id=%d GROUP BY status", $organiser_id ), ARRAY_A );
        $by_status = array();
        $total = 0;
        foreach ( $counts as $count ) { $by_status[$count['status']] = (int) $count['total']; $total += (int) $count['total']; }
        echo '<p><strong>' . esc_html( $name ) . '</strong></p>';
        echo '<style>.bh3-leader-dashboard{max-width:1100px}.bh3-leader-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,230px),1fr));gap:16px;margin:20px 0}.bh3-leader-card{background:#fff;border:1px solid #dbe5df;border-radius:12px;padding:20px}.bh3-leader-card h2{font-size:18px;margin:0 0 10px}.bh3-leader-card p{line-height:1.5}.bh3-leader-count{font-size:32px;font-weight:700;color:#245e4a;margin:4px 0 12px}.bh3-leader-card .button{margin-top:6px}</style>';
        echo '<div class="bh3-leader-grid">';
        $cards = array(
            array( 'My Activities', $total . ' total · ' . (int) ( $by_status['draft'] ?? 0 ) . ' drafts · ' . (int) ( $by_status['published'] ?? 0 ) . ' published', 'Search, review and edit your own draft listings.', 'My Listings', admin_url( 'admin.php?page=bh3-my-listings' ) ),
            array( 'My Venues', 'Assigned to activities', 'Open a draft activity to manage its assigned venues.', 'Open activities', admin_url( 'admin.php?page=bh3-my-listings&listing_status=draft' ) ),
            array( 'My Timetables', 'Weekly sessions', 'Open a draft activity to manage its session times.', 'Open drafts', admin_url( 'admin.php?page=bh3-my-listings&listing_status=draft' ) ),
            array( 'Bookings', 'Coming later', 'Bookings and enrolments are not enabled in this staging build.', '', '' ),
            array( 'My Business', 'Coming later', 'Business profile and account tools will be added after permissions testing.', '', '' ),
            array( 'Help & Support', 'Coming later', 'Leader guidance and support resources are being developed.', '', '' ),
        );
        foreach ( $cards as $card ) {
            echo '<section class="bh3-leader-card"><h2>' . esc_html( $card[0] ) . '</h2><p class="bh3-leader-count">' . esc_html( $card[1] ) . '</p><p>' . esc_html( $card[2] ) . '</p>';
            if ( $card[4] ) { echo '<a class="button button-primary" href="' . esc_url( $card[4] ) . '">' . esc_html( $card[3] ) . '</a>'; }
            echo '</section>';
        }
        echo '</div><p>Only organiser-owned draft activities can be changed. Publishing and deletion remain administrator-controlled.</p></div>';
    }
}
