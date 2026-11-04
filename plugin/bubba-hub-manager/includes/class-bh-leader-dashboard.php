<?php
/** Staging-only, read-only leader overview. Uses the WordPress login session (including FluentAuth). */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class BH_Leader_Dashboard_3 {
    public static function register() {
        add_shortcode( 'bh3_leader_dashboard', array( __CLASS__, 'shortcode' ) );
        add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
    }
    public static function menu() {
        if ( self::staging() ) {
            add_submenu_page( null, 'Leader Dashboard', 'Leader Dashboard', 'read', 'bh3-leader-dashboard', array( __CLASS__, 'admin_page' ) );
        }
    }
    private static function staging() {
        return BH_Leader_Access_3::is_staging();
    }
    public static function admin_page() {
        if ( ! current_user_can( 'read' ) ) { wp_die( 'Sign in required.', '', array( 'response' => 403 ) ); }
        echo '<div class="wrap">';
        echo self::shortcode();
        echo '</div>';
    }
    public static function shortcode() {
        if ( ! self::staging() ) { return ''; }
        if ( ! is_user_logged_in() ) {
            return '<p>Please <a href="' . esc_url( wp_login_url( get_permalink() ?: home_url( '/' ) ) ) . '">sign in</a> to manage your activities.</p>';
        }
        $organiser = BH_Leader_Access_3::organiser_id();
        if ( ! $organiser ) { return '<section class="bh3-leader"><h2>Leader Hub</h2><p>No organiser is linked to this account yet. Contact Bubba Hub support to verify your organiser claim.</p></section>'; }
        global $wpdb;
        $table = $wpdb->prefix . 'activities';
        if ( ! BH_Leader_Access_3::table_exists( 'activities' ) || ! BH_Leader_Access_3::table_exists( 'organisers' ) ) {
            return '<section class="bh3-leader"><h2>Leader Hub</h2><p>The staging activity tables are not ready yet.</p></section>';
        }
        $organiser_table = $wpdb->prefix . 'organisers';
        $organiser_name = $wpdb->get_var( $wpdb->prepare( "SELECT name FROM `{$organiser_table}` WHERE id = %d LIMIT 1", $organiser ) );
        if ( ! $organiser_name ) { return '<section class="bh3-leader"><h2>Leader Hub</h2><p>Your linked organiser could not be verified. Contact Bubba Hub support.</p></section>'; }
        $search = isset( $_GET['bh3_leader_search'] ) ? substr( sanitize_text_field( wp_unslash( $_GET['bh3_leader_search'] ) ), 0, 100 ) : '';
        $like = '%' . $wpdb->esc_like( $search ) . '%';
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, title, status FROM `{$table}` WHERE organiser_id = %d AND title LIKE %s ORDER BY title ASC LIMIT 100",
            $organiser, $like
        ), ARRAY_A );
        $base = admin_url( 'admin.php' );
        $out = '<style>.bh3-leader{max-width:1100px;margin:24px auto;color:#263b33;font-family:inherit}.bh3-leader h1{font-size:clamp(25px,4vw,36px);margin:0 0 8px}.bh3-leader .bh3-sub{color:#52665e}.bh3-leader .bh3-panel{background:#fff;border:1px solid #dce8e0;border-radius:16px;padding:20px;margin:20px 0;box-shadow:0 4px 18px rgba(20,60,40,.05)}.bh3-leader .bh3-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px}.bh3-leader .bh3-tile{padding:18px;border:1px solid #dce8e0;border-radius:12px;background:#f7faf8}.bh3-leader .bh3-btn{display:inline-block;padding:9px 13px;border-radius:9px;background:#245e4a;color:white;text-decoration:none;margin:3px}.bh3-leader .bh3-btn:focus,.bh3-leader .bh3-btn:hover{background:#174331;color:#fff}.bh3-leader input[type=search]{width:min(100%,360px);padding:10px;border:1px solid #c7d8cd;border-radius:8px}.bh3-leader .bh3-list{list-style:none;margin:0;padding:0}.bh3-leader .bh3-list li{padding:14px 0;border-bottom:1px solid #e7eee9}.bh3-leader .bh3-list li:last-child{border:0}.bh3-leader .bh3-state{display:inline-block;margin-left:8px;color:#52665e;font-size:13px}@media(max-width:600px){.bh3-leader .bh3-panel{padding:14px}.bh3-leader .bh3-btn{margin:5px 4px 0 0}}</style>';
        $out .= '<section class="bh3-leader"><h1>My Leader Hub</h1><p class="bh3-sub">' . esc_html( $organiser_name ) . ' · Manage your Bubba Hub activity listings and weekly schedules.</p>';
        if ( isset( $_GET['closed'] ) ) { $out .= '<p role="status">Listing unpublished. It is now a draft.</p>'; }
        $out .= '<div class="bh3-grid"><div class="bh3-tile"><strong>My Activities</strong><p>Search and edit your listings.</p></div><div class="bh3-tile"><strong>Schedules</strong><p>Update weekly session times.</p></div><div class="bh3-tile"><strong>My Venues</strong><p>Link existing venue records.</p></div></div>';
        $out .= '<div class="bh3-panel"><h2>Your activities</h2><form method="get">' . ( is_admin() ? '<input type="hidden" name="page" value="bh3-leader-dashboard">' : '' ) . '<label for="bh3-leader-search">Search activities</label><p><input id="bh3-leader-search" type="search" name="bh3_leader_search" value="' . esc_attr( $search ) . '" placeholder="Activity name"> <button type="submit">Search</button></p></form><ul class="bh3-list">';
        foreach ( $rows as $row ) {
            $id = absint( $row['id'] );
            $out .= '<li><strong>' . esc_html( $row['title'] ) . '</strong><span class="bh3-state">' . esc_html( ucfirst( $row['status'] ) ) . '</span><div>';
            if ( $row['status'] === 'draft' ) {
                foreach ( array( 'bh3-leader-edit' => 'Edit', 'bh3-leader-sessions' => 'Schedule', 'bh3-leader-venues' => 'Venues' ) as $page => $label ) {
                    $out .= '<a class="bh3-btn" href="' . esc_url( add_query_arg( array( 'page' => $page, 'activity_id' => $id ), $base ) ) . '">' . esc_html( $label ) . '</a>';
                }
            } elseif ( $row['status'] === 'published' ) {
                $out .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
                $out .= '<input type="hidden" name="action" value="bh3_leader_close"><input type="hidden" name="activity_id" value="' . esc_attr( (string) $id ) . '">';
                $out .= wp_nonce_field( 'bh3_close_' . $id, '_wpnonce', true, false );
                $out .= '<label><input type="checkbox" name="confirm_close" value="yes" required> Confirm unpublish</label> ';
                $out .= '<button class="bh3-btn" type="submit">Close listing</button></form>';
            } else {
                $out .= '<p class="bh3-sub">This listing is not editable in the staging leader dashboard.</p>';
            }
            $out .= '</div></li>';
        }
        if ( ! $rows ) { $out .= '<li>No matching activities found for your linked organiser.</li>'; }
        $out .= '</ul></div><p class="bh3-sub">Staging preview · No bookings or changes are made from this overview.</p></section>';
        return $out;
    }
}
