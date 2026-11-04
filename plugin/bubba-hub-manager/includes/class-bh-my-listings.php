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
        $search = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) );
        $status = sanitize_key( wp_unslash( $_GET['listing_status'] ?? '' ) );
        if ( ! in_array( $status, array( '', 'draft', 'published', 'pending', 'archived' ), true ) ) { $status = ''; }
        $page_num = max( 1, absint( $_GET['paged'] ?? 1 ) );
        $where = 'organiser_id = %d';
        $args = array( $organiser_id );
        if ( $search !== '' ) { $where .= ' AND title LIKE %s'; $args[] = '%' . $wpdb->esc_like( $search ) . '%'; }
        if ( $status !== '' ) { $where .= ' AND status = %s'; $args[] = $status; }
        $total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$activities}` WHERE {$where}", $args ) );
        $pages = max( 1, (int) ceil( $total / 20 ) );
        $page_num = min( $page_num, $pages );
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT id,title,status,category FROM `{$activities}` WHERE {$where} ORDER BY id DESC LIMIT %d OFFSET %d", array_merge( $args, array( 20, ( $page_num - 1 ) * 20 ) ) ), ARRAY_A );
        echo '<form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin:18px 0"><input type="hidden" name="page" value="bh3-my-listings">';
        echo '<label class="screen-reader-text" for="bh3-listing-search">Search activities</label><input id="bh3-listing-search" type="search" name="s" placeholder="Search my activities" value="' . esc_attr( $search ) . '">';
        echo '<label class="screen-reader-text" for="bh3-listing-status">Listing status</label><select id="bh3-listing-status" name="listing_status">';
        foreach ( array( '' => 'All statuses', 'draft' => 'Draft', 'published' => 'Published', 'pending' => 'Pending', 'archived' => 'Archived' ) as $value => $label ) {
            echo '<option value="' . esc_attr( $value ) . '"' . selected( $status, $value, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select><button class="button button-primary" type="submit">Filter listings</button> <a class="button" href="' . esc_url( admin_url( 'admin.php?page=bh3-my-listings' ) ) . '">Clear</a></form>';
        echo '<p aria-live="polite">' . esc_html( number_format_i18n( $total ) ) . ' matching activities</p>';
        echo '<table class="widefat striped"><thead><tr><th>Activity</th><th>Category</th><th>Status</th><th>Action</th></tr></thead><tbody>';
        foreach ( $rows as $row ) {
            echo '<tr><td>' . esc_html( $row['title'] ) . '</td><td>' . esc_html( (string) $row['category'] ) .
                '</td><td><span class="bh3-status">' . esc_html( $row['status'] ) . '</span></td><td>' . ( $row['status'] === 'draft' ? '<a class="button" href="' . esc_url( add_query_arg( array( 'page' => 'bh3-leader-edit', 'activity_id' => absint( $row['id'] ) ), admin_url( 'admin.php' ) ) ) . '">Edit</a>' : 'View only' ) . '</td></tr>';
        }
        if ( ! $rows ) { echo '<tr><td colspan="4">No matching activities.</td></tr>'; }
        echo '</tbody></table>';
        if ( $pages > 1 ) {
            echo '<nav aria-label="Listing pages" style="margin:18px 0">';
            echo wp_kses_post( paginate_links( array( 'base' => add_query_arg( array( 'page' => 'bh3-my-listings', 's' => $search, 'listing_status' => $status, 'paged' => '%#%' ), admin_url( 'admin.php' ) ), 'format' => '', 'current' => $page_num, 'total' => $pages, 'prev_text' => '← Previous', 'next_text' => 'Next →' ) ) );
            echo '</nav>';
        }
        echo '</div>';

    }
}
